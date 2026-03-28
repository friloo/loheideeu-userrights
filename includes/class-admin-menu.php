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

		$allowed_slugs = $this->get_allowed_slugs_for_user( $user );

		if ( ! $this->is_current_page_allowed( $pagenow, $allowed_slugs ) ) {
			wp_safe_redirect( admin_url( 'index.php' ) );
			exit;
		}
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
