<?php
/**
 * Inhaltsfilter: Schränkt Admin-Listen und REST API für Beiträge/Seiten/Medien ein.
 * Gilt für klassische Admin-Listenansichten und den Gutenberg/REST-API-Kontext.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WP_UserRights_Content_Filter {

	public function __construct() {
		// Listenansichten und REST API
		add_action( 'pre_get_posts', array( $this, 'filter_content' ) );
		// Kategorie-Auswahl: direkt am Term-Query-Objekt (klassisch + Gutenberg + REST)
		add_action( 'pre_get_terms', array( $this, 'restrict_category_query' ) );
		// Kategorien erzwingen: klassischer Editor (save_post feuert nach wp_set_post_terms)
		add_action( 'save_post_post', array( $this, 'enforce_category_on_save' ), 20, 2 );
		// Kategorien erzwingen: Gutenberg REST API (handle_terms läuft NACH save_post,
		// deshalb eigener Hook der nach dem vollständigen Speichern feuert)
		add_action( 'rest_after_insert_post', array( $this, 'enforce_category_after_rest_save' ) );
		// Medien-Modal (Gutenberg + klassischer Editor)
		add_filter( 'ajax_query_attachments_args', array( $this, 'filter_media_modal' ) );
	}

	// =========================================================================
	// Listenansichten + REST API
	// =========================================================================

	public function filter_content( $query ) {
		$is_rest = defined( 'REST_REQUEST' ) && REST_REQUEST;

		if ( ! is_admin() && ! $is_rest ) {
			return;
		}
		if ( ! $query->is_main_query() ) {
			return;
		}

		$user = wp_get_current_user();
		if ( ! $user->exists() || in_array( 'administrator', (array) $user->roles, true ) ) {
			return;
		}

		$r         = $this->get_user_content_restrictions( $user );
		$post_type = $query->get( 'post_type' );

		// Beiträge nach Kategorie filtern
		if ( ( 'post' === $post_type || '' === $post_type ) && $r['restrict_cats'] ) {
			$query->set( 'tax_query', array(
				array(
					'taxonomy' => 'category',
					'field'    => 'slug',
					'terms'    => $r['cats'],
				),
			) );
		}

		// Seiten nach Slug filtern
		if ( 'page' === $post_type && $r['restrict_pages'] ) {
			$page_ids = $this->get_page_ids_by_slugs( $r['pages'] );
			$query->set( 'post__in', ! empty( $page_ids ) ? $page_ids : array( 0 ) );
		}

		// Mediathek: nur eigene Dateien
		if ( 'attachment' === $post_type && $r['restrict_media'] ) {
			$query->set( 'author', $user->ID );
		}
	}

	// =========================================================================
	// Kategorie-Auswahl einschränken
	// =========================================================================

	/**
	 * Schränkt WP_Term_Query direkt am Query-Objekt ein — greift für klassischen
	 * Editor, Gutenberg (REST API) und alle anderen Term-Query-Kontexte.
	 * Wird als Action aufgerufen, modifiziert das Objekt in-place.
	 *
	 * @param WP_Term_Query $query
	 */
	public function restrict_category_query( $query ) {
		$taxonomies = (array) $query->query_vars['taxonomy'];
		if ( ! in_array( 'category', $taxonomies, true ) ) {
			return;
		}

		$user = wp_get_current_user();
		if ( ! $user->exists() || current_user_can( 'manage_options' ) ) {
			return;
		}

		$r = $this->get_user_content_restrictions( $user );
		if ( ! $r['restrict_cats'] ) {
			return;
		}

		// Slug-Liste direkt in den Query schreiben.
		// Bereits gesetzte Slugs werden auf die Schnittmenge reduziert.
		if ( ! empty( $query->query_vars['slug'] ) ) {
			$query->query_vars['slug'] = array_values( array_intersect(
				(array) $query->query_vars['slug'],
				$r['cats']
			) );
		} else {
			$query->query_vars['slug'] = $r['cats'];
		}
	}

	// =========================================================================
	// Kategorien beim Speichern erzwingen
	// =========================================================================

	/**
	 * Klassischer Editor: save_post_post feuert nachdem wp_insert_post()
	 * die Kategorien bereits gesetzt hat — Timing stimmt hier.
	 */
	public function enforce_category_on_save( $post_id, $post ) {
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}
		if ( wp_is_post_revision( $post_id ) ) {
			return;
		}
		// REST-Saves werden von rest_after_insert_post behandelt
		if ( defined( 'REST_REQUEST' ) && REST_REQUEST ) {
			return;
		}

		$user = wp_get_current_user();
		if ( ! $user->exists() || current_user_can( 'manage_options' ) ) {
			return;
		}

		$r = $this->get_user_content_restrictions( $user );
		if ( ! $r['restrict_cats'] ) {
			return;
		}

		$this->enforce_allowed_categories( $post_id, $r['cats'] );
	}

	/**
	 * Gutenberg / REST API: rest_after_insert_post feuert nachdem AUCH
	 * handle_terms() die Kategorien gesetzt hat — einziger zuverlässiger
	 * Zeitpunkt für REST-basierte Saves.
	 *
	 * @param WP_Post $post
	 */
	public function enforce_category_after_rest_save( $post ) {
		$user = wp_get_current_user();
		if ( ! $user->exists() || current_user_can( 'manage_options' ) ) {
			return;
		}

		$r = $this->get_user_content_restrictions( $user );
		if ( ! $r['restrict_cats'] ) {
			return;
		}

		$this->enforce_allowed_categories( $post->ID, $r['cats'] );
	}

	/**
	 * Korrigiert die Kategorie-Zuweisung eines Beitrags auf die erlaubten Slugs.
	 *
	 * @param int   $post_id
	 * @param array $allowed_slugs
	 */
	private function enforce_allowed_categories( $post_id, array $allowed_slugs ) {
		$current_terms = wp_get_post_terms( $post_id, 'category' );
		if ( is_wp_error( $current_terms ) ) {
			return;
		}

		$valid_ids = array();
		foreach ( $current_terms as $term ) {
			if ( in_array( $term->slug, $allowed_slugs, true ) ) {
				$valid_ids[] = $term->term_id;
			}
		}

		// Keine erlaubte Kategorie gesetzt → erste erlaubte als Fallback
		if ( empty( $valid_ids ) ) {
			$fallback = get_term_by( 'slug', $allowed_slugs[0], 'category' );
			if ( $fallback ) {
				$valid_ids = array( $fallback->term_id );
			}
		}

		if ( ! empty( $valid_ids ) ) {
			remove_action( 'rest_after_insert_post', array( $this, 'enforce_category_after_rest_save' ) );
			remove_action( 'save_post_post', array( $this, 'enforce_category_on_save' ), 20 );
			wp_set_post_terms( $post_id, $valid_ids, 'category' );
			add_action( 'save_post_post', array( $this, 'enforce_category_on_save' ), 20, 2 );
			add_action( 'rest_after_insert_post', array( $this, 'enforce_category_after_rest_save' ) );
		}
	}

	// =========================================================================
	// Medien-Modal (AJAX)
	// =========================================================================

	public function filter_media_modal( $args ) {
		$user = wp_get_current_user();
		if ( ! $user->exists() || current_user_can( 'manage_options' ) ) {
			return $args;
		}

		$r = $this->get_user_content_restrictions( $user );
		if ( $r['restrict_media'] ) {
			$args['author'] = $user->ID;
		}
		return $args;
	}

	// =========================================================================
	// Hilfsmethoden
	// =========================================================================

	/**
	 * Berechnet die Inhalts-Einschränkungen für einen User.
	 *
	 * Rollen ohne Plugin-Konfiguration (z. B. Abonnent als Basis-Rolle) werden
	 * ignoriert — sie heben die Einschränkungen konfigurierter Rollen nicht auf.
	 */
	private function get_user_content_restrictions( $user ) {
		$permissions          = get_option( WP_USERRIGHTS_OPTION, array() );
		$allowed_cats         = array();
		$allowed_pages        = array();
		$restrict_media       = false;
		$all_have_cat_filter  = true;
		$all_have_page_filter = true;
		$has_configured_role  = false;

		foreach ( (array) $user->roles as $role ) {
			$role_perms = isset( $permissions[ $role ] ) ? $permissions[ $role ] : array();

			// Rollen ohne Plugin-Konfiguration überspringen
			if ( empty( $role_perms ) || empty( $role_perms['menu_slugs'] ) ) {
				continue;
			}

			$has_configured_role = true;

			$role_cats  = isset( $role_perms['allowed_categories'] ) ? (array) $role_perms['allowed_categories'] : array();
			$role_pages = isset( $role_perms['allowed_page_slugs'] ) ? (array) $role_perms['allowed_page_slugs'] : array();

			if ( empty( $role_cats ) ) {
				$all_have_cat_filter = false;
			} else {
				$allowed_cats = array_unique( array_merge( $allowed_cats, $role_cats ) );
			}

			if ( empty( $role_pages ) ) {
				$all_have_page_filter = false;
			} else {
				$allowed_pages = array_unique( array_merge( $allowed_pages, $role_pages ) );
			}

			if ( ! empty( $role_perms['restrict_media'] ) ) {
				$restrict_media = true;
			}
		}

		return array(
			'cats'           => $allowed_cats,
			'pages'          => $allowed_pages,
			'restrict_cats'  => $has_configured_role && $all_have_cat_filter && ! empty( $allowed_cats ),
			'restrict_pages' => $has_configured_role && $all_have_page_filter && ! empty( $allowed_pages ),
			'restrict_media' => $restrict_media,
		);
	}

	/**
	 * Gibt Seiten-IDs für eine Liste von Slugs zurück.
	 */
	private function get_page_ids_by_slugs( array $slugs ) {
		$ids = array();
		foreach ( $slugs as $slug ) {
			$slug = trim( $slug );
			if ( empty( $slug ) ) {
				continue;
			}
			$page = get_page_by_path( $slug, OBJECT, 'page' );
			if ( $page ) {
				$ids[] = $page->ID;
			}
		}
		return array_unique( $ids );
	}
}
