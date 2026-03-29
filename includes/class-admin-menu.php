<?php
/**
 * Menü-Beschränkungslogik: Entfernt nicht erlaubte Menüpunkte für nicht-Admin-Rollen.
 * Schützt auch vor direktem URL-Zugriff auf verbotene Seiten.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WP_UserRights_Admin_Menu {

	public function __construct() {
		// Priorität 9999: Nach allen anderen Plugins/Themes ausführen
		add_action( 'admin_menu', array( $this, 'enforce_permissions' ), 9999 );
		// Direktzugriff-Schutz: prüft auch ohne Menü-Klick ob eine Seite erlaubt ist
		add_action( 'admin_init', array( $this, 'check_direct_access' ) );
		// Hinweis anzeigen wenn jemand auf das Dashboard umgeleitet wurde
		add_action( 'admin_notices', array( $this, 'show_access_denied_notice' ) );
		// Login-Weiterleitung: direkt zur ersten erlaubten Seite
		add_filter( 'login_redirect', array( $this, 'login_redirect' ), 10, 3 );
		// Admin-Bar: nicht erlaubte Knoten ausblenden
		add_action( 'admin_bar_menu', array( $this, 'filter_admin_bar' ), 999 );
	}

	// -------------------------------------------------------------------------
	// Menü-Enforcement (Punkt 1: Multi-Rollen-Union, Punkt 3: Dashboard immer sichtbar)
	// -------------------------------------------------------------------------

	public function enforce_permissions() {
		$user = wp_get_current_user();

		// Admins sehen alles
		if ( in_array( 'administrator', (array) $user->roles, true ) ) {
			return;
		}

		$allowed_slugs = $this->get_allowed_slugs_for_user( $user );

		global $menu, $submenu;

		// Top-Level-Menüpunkte prüfen und entfernen
		foreach ( $menu as $position => $item ) {
			$slug = $item[2];

			if ( strpos( $slug, 'separator' ) === 0 ) {
				continue;
			}

			if ( ! $this->is_slug_allowed( $slug, $allowed_slugs ) ) {
				remove_menu_page( $slug );
			}
		}

		// Untermenüpunkte prüfen und entfernen
		foreach ( $submenu as $parent_slug => $items ) {
			foreach ( $items as $sub_item ) {
				$sub_slug = $sub_item[2];

				if ( ! $this->is_slug_allowed( $sub_slug, $allowed_slugs ) ) {
					remove_submenu_page( $parent_slug, $sub_slug );
				}
			}
		}

		$this->remove_orphan_separators();
	}

	// -------------------------------------------------------------------------
	// Direktzugriff-Schutz (Punkt 1)
	// -------------------------------------------------------------------------

	/**
	 * Prüft ob der aktuelle Admin-Seitenaufruf für den User erlaubt ist.
	 * Leitet bei Verstoß auf das Dashboard um.
	 */
	public function check_direct_access() {
		global $pagenow;

		// Interne WordPress-Endpunkte nie blockieren
		$skip = array( 'admin-ajax.php', 'admin-post.php', 'async-upload.php' );
		if ( in_array( $pagenow, $skip, true ) ) {
			return;
		}

		$user = wp_get_current_user();

		// Kein eingeloggter User oder Admin → überspringen
		if ( ! $user->exists() || in_array( 'administrator', (array) $user->roles, true ) ) {
			return;
		}

		// Reine Abonnenten komplett aus dem Backend aussperren
		$user_roles = array_values( (array) $user->roles );
		sort( $user_roles );
		if ( $user_roles === array( 'subscriber' ) ) {
			wp_safe_redirect( home_url() );
			exit;
		}

		$allowed_slugs = $this->get_allowed_slugs_for_user( $user );

		if ( ! $this->is_current_page_allowed( $pagenow, $allowed_slugs ) ) {
			wp_safe_redirect( add_query_arg( 'wp_userrights_denied', '1', admin_url( 'index.php' ) ) );
			exit;
		}
	}

	/**
	 * Zeigt einen Admin-Hinweis wenn der User auf eine verbotene Seite zugegriffen hat
	 * und zum Dashboard weitergeleitet wurde.
	 */
	public function show_access_denied_notice() {
		if ( empty( $_GET['wp_userrights_denied'] ) ) {
			return;
		}
		?>
		<div class="notice notice-warning is-dismissible">
			<p><?php esc_html_e( 'Sie haben keinen Zugriff auf die angeforderte Seite.', 'wp-userrights' ); ?></p>
		</div>
		<?php
	}

	/**
	 * Prüft ob die aktuell aufgerufene Admin-Seite in den erlaubten Slugs enthalten ist.
	 */
	private function is_current_page_allowed( $pagenow, array $allowed_slugs ) {
		// Dashboard und eigenes Profil sind immer erlaubt
		$always_allowed = array( 'index.php', 'profile.php' );
		if ( in_array( $pagenow, $always_allowed, true ) ) {
			return true;
		}

		// Plugin-Einstellungsseite ist für Admins via 'manage_options' gesichert – überspringen
		if ( 'admin.php' === $pagenow
			&& isset( $_GET['page'] )
			&& 'wp-userrights' === $_GET['page']
		) {
			return true; // Admins-only durch WP-Capability, nicht durch dieses Plugin
		}

		// Direkter Slug-Treffer (z.B. 'edit.php', 'upload.php')
		if ( in_array( $pagenow, $allowed_slugs, true ) ) {
			return true;
		}

		// Slug mit post_type-Parameter (z.B. 'edit.php?post_type=page')
		if ( ! empty( $_GET['post_type'] ) ) {
			$slug_with_type = $pagenow . '?post_type=' . sanitize_key( $_GET['post_type'] );
			if ( in_array( $slug_with_type, $allowed_slugs, true ) ) {
				return true;
			}
		}

		// Plugin-Seiten (admin.php?page=my-plugin)
		if ( 'admin.php' === $pagenow && ! empty( $_GET['page'] ) ) {
			if ( in_array( sanitize_key( $_GET['page'] ), $allowed_slugs, true ) ) {
				return true;
			}
		}

		// post.php: Bearbeiten eines Eintrags erlaubt wenn die Listenansicht erlaubt ist
		if ( 'post.php' === $pagenow ) {
			// Post-Typ aus dem Post selbst ermitteln (zuverlässiger als $_GET['post_type'])
			if ( ! empty( $_GET['post'] ) ) {
				$post = get_post( absint( $_GET['post'] ) );
				if ( $post ) {
					$type = $post->post_type;
					$list_slug = ( 'post' === $type )
						? 'edit.php'
						: 'edit.php?post_type=' . $type;
					if ( in_array( $list_slug, $allowed_slugs, true ) ) {
						return true;
					}
				}
			}
			// Fallback: post_type aus GET
			$type = isset( $_GET['post_type'] ) ? sanitize_key( $_GET['post_type'] ) : 'post';
			$list_slug = ( 'post' === $type || '' === $type )
				? 'edit.php'
				: 'edit.php?post_type=' . $type;
			if ( in_array( $list_slug, $allowed_slugs, true ) ) {
				return true;
			}
		}

		return false;
	}

	// -------------------------------------------------------------------------
	// Hilfsmethoden
	// -------------------------------------------------------------------------

	/**
	 * Gibt die vereinigten erlaubten Slugs für alle Rollen eines Users zurück.
	 * Dashboard (index.php) ist immer enthalten (Punkt 3).
	 * Unterstützt mehrere Rollen pro User (Punkt 5: Multi-Rollen-Union).
	 */
	public function get_allowed_slugs_for_user( $user ) {
		$permissions = get_option( WP_USERRIGHTS_OPTION, array() );

		// Dashboard ist immer erlaubt – verhindert Login-Redirect-Schleifen
		$allowed_slugs = array( 'index.php' );

		foreach ( (array) $user->roles as $role ) {
			$role_perms = isset( $permissions[ $role ] ) ? $permissions[ $role ] : array();
			$role_slugs = isset( $role_perms['menu_slugs'] ) ? (array) $role_perms['menu_slugs'] : array();
			$allowed_slugs = array_unique( array_merge( $allowed_slugs, $role_slugs ) );
		}

		return $allowed_slugs;
	}

	/**
	 * Prüft ob ein Slug erlaubt ist (exakter Vergleich).
	 */
	private function is_slug_allowed( $slug, array $allowed_slugs ) {
		return in_array( $slug, $allowed_slugs, true );
	}

	/**
	 * Filtert die Admin-Bar für Nicht-Admins: entfernt alle Knoten,
	 * auf die der Benutzer keinen Zugriff hat.
	 *
	 * @param WP_Admin_Bar $wp_admin_bar
	 */
	public function filter_admin_bar( $wp_admin_bar ) {
		if ( ! is_user_logged_in() ) {
			return;
		}
		if ( current_user_can( 'manage_options' ) ) {
			return;
		}

		$user          = wp_get_current_user();
		$allowed_slugs = $this->get_allowed_slugs_for_user( $user );

		// Immer entfernen: WordPress-Logo-Menü (Docs, About, Updates) und Suche
		$wp_admin_bar->remove_node( 'wp-logo' );
		$wp_admin_bar->remove_node( 'search' );

		// Updates-Icon: nur bei Zugriff auf update-core.php anzeigen
		if ( ! in_array( 'update-core.php', $allowed_slugs, true ) ) {
			$wp_admin_bar->remove_node( 'updates' );
		}

		// Kommentar-Glocke: nur bei Zugriff auf edit-comments.php anzeigen
		if ( ! in_array( 'edit-comments.php', $allowed_slugs, true ) ) {
			$wp_admin_bar->remove_node( 'comments' );
		}

		// Site-Name-Untermenü: Admin-Links herausfiltern wenn kein Zugriff
		$site_subnodes = array(
			'themes'      => 'themes.php',
			'widgets'     => 'widgets.php',
			'menus'       => 'nav-menus.php',
			'customize'   => null,   // komplexe Preview-URL, immer entfernen
			'background'  => null,
			'header'      => null,
			'site-editor' => null,
		);
		foreach ( $site_subnodes as $node_id => $slug ) {
			if ( null === $slug || ! in_array( $slug, $allowed_slugs, true ) ) {
				$wp_admin_bar->remove_node( $node_id );
			}
		}

		// "+Neu"-Menü: Einträge nach erlaubten Slugs filtern
		$new_item_slugs = array(
			'new-post'  => 'post-new.php',
			'new-media' => 'media-new.php',
			'new-user'  => 'user-new.php',
			'new-link'  => null,   // veraltet
		);

		// Seiten und Custom Post Types dynamisch ergänzen
		foreach ( get_post_types( array( 'show_in_admin_bar' => true ), 'names' ) as $cpt ) {
			if ( 'post' !== $cpt ) {
				$new_item_slugs[ 'new-' . $cpt ] = 'post-new.php?post_type=' . $cpt;
			}
		}

		$has_new_item = false;
		foreach ( $new_item_slugs as $node_id => $slug ) {
			if ( null === $slug || ! in_array( $slug, $allowed_slugs, true ) ) {
				$wp_admin_bar->remove_node( $node_id );
			} else {
				$has_new_item = true;
			}
		}

		// "+Neu"-Parent entfernen wenn keine Einträge übrig
		if ( ! $has_new_item ) {
			$wp_admin_bar->remove_node( 'new-content' );
		}
	}

	/**
	 * Leitet den User nach dem Login direkt zur ersten erlaubten Admin-Seite weiter.
	 */
	public function login_redirect( $redirect_to, $requested_redirect_to, $user ) {
		if ( is_wp_error( $user ) || ! ( $user instanceof WP_User ) ) {
			return $redirect_to;
		}

		// Admins → Standard-Verhalten
		if ( in_array( 'administrator', (array) $user->roles, true ) ) {
			return $redirect_to;
		}

		// Reine Abonnenten → Frontend
		$user_roles = array_values( (array) $user->roles );
		sort( $user_roles );
		if ( $user_roles === array( 'subscriber' ) ) {
			return home_url();
		}

		$allowed_slugs = $this->get_allowed_slugs_for_user( $user );

		// Ersten nicht-Dashboard-Slug als Ziel nehmen
		foreach ( $allowed_slugs as $slug ) {
			if ( 'index.php' === $slug ) {
				continue;
			}
			return admin_url( $slug );
		}

		// Fallback: Dashboard
		return admin_url( 'index.php' );
	}

	/**
	 * Entfernt verwaiste Separator-Einträge (keine sichtbaren Nachbarn mehr).
	 */
	private function remove_orphan_separators() {
		global $menu;

		if ( empty( $menu ) ) {
			return;
		}

		ksort( $menu );
		$keys  = array_keys( $menu );
		$total = count( $keys );

		for ( $i = 0; $i < $total; $i++ ) {
			$pos  = $keys[ $i ];
			$item = $menu[ $pos ];

			if ( strpos( $item[2], 'separator' ) !== 0 ) {
				continue;
			}

			$has_visible_after = false;
			for ( $j = $i + 1; $j < $total; $j++ ) {
				$next = $menu[ $keys[ $j ] ];
				if ( strpos( $next[2], 'separator' ) === 0 ) {
					break;
				}
				$has_visible_after = true;
				break;
			}

			if ( ! $has_visible_after ) {
				unset( $menu[ $pos ] );
			}
		}
	}
}
