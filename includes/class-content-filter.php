<?php
/**
 * Inhaltsfilter: Schränkt Admin-Listen und REST API für Beiträge/Seiten ein.
 * Gilt für klassische Admin-Listenansichten und den Gutenberg/REST-API-Kontext.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WP_UserRights_Content_Filter {

	public function __construct() {
		// Klassische Admin-Listenansicht
		add_action( 'pre_get_posts', array( $this, 'filter_content' ) );
	}

	public function filter_content( $query ) {
		$is_rest = defined( 'REST_REQUEST' ) && REST_REQUEST;

		// Nur im Admin-Bereich oder bei REST-API-Anfragen anwenden
		if ( ! is_admin() && ! $is_rest ) {
			return;
		}

		// Nur Haupt-Query
		if ( ! $query->is_main_query() ) {
			return;
		}

		$user = wp_get_current_user();

		if ( ! $user->exists() || in_array( 'administrator', (array) $user->roles, true ) ) {
			return;
		}

		$permissions = get_option( WP_USERRIGHTS_OPTION, array() );

		// Multi-Rollen-Union: Sammle erlaubte Kategorien und Seiten über alle Rollen des Users.
		// Logik: Nur einschränken wenn ALLE Rollen des Users einen Filter konfiguriert haben.
		// Hat eine Rolle keinen Filter, darf der User alles sehen (Vereinigung = unbegrenzt).
		$allowed_cats          = array();
		$allowed_pages         = array();
		$all_have_cat_filter   = true;
		$all_have_page_filter  = true;

		foreach ( (array) $user->roles as $role ) {
			$role_perms = isset( $permissions[ $role ] ) ? $permissions[ $role ] : array();

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
		}

		$post_type = $query->get( 'post_type' );

		// Beiträge nach Kategorie filtern
		if ( ( 'post' === $post_type || '' === $post_type ) && $all_have_cat_filter && ! empty( $allowed_cats ) ) {
			$query->set( 'tax_query', array(
				array(
					'taxonomy' => 'category',
					'field'    => 'slug',
					'terms'    => $allowed_cats,
				),
			) );
		}

		// Seiten nach Slug filtern
		if ( 'page' === $post_type && $all_have_page_filter && ! empty( $allowed_pages ) ) {
			$page_ids = $this->get_page_ids_by_slugs( $allowed_pages );
			$query->set( 'post__in', ! empty( $page_ids ) ? $page_ids : array( 0 ) );
		}
	}

	/**
	 * Gibt Seiten-IDs für eine Liste von Slugs zurück.
	 * Unterstützt verschachtelte Slugs (z.B. "eltern/kind").
	 *
	 * @param array $slugs Array von Seiten-Slugs
	 * @return array       Array von Post-IDs
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
