<?php
/**
 * Menü-Beschränkungslogik: Entfernt nicht erlaubte Menüpunkte für nicht-Admin-Rollen.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WP_UserRights_Admin_Menu {

	public function __construct() {
		// Priorität 9999: Nach allen anderen Plugins/Themes ausführen
		add_action( 'admin_menu', array( $this, 'enforce_permissions' ), 9999 );
	}

	public function enforce_permissions() {
		$user = wp_get_current_user();

		// Admins sehen alles
		if ( in_array( 'administrator', (array) $user->roles, true ) ) {
			return;
		}

		$permissions = get_option( WP_USERRIGHTS_OPTION, array() );

		// Primäre Rolle des Users
		$role = ! empty( $user->roles ) ? reset( $user->roles ) : '';

		$role_perms = isset( $permissions[ $role ] ) ? $permissions[ $role ] : array();
		$allowed_slugs = isset( $role_perms['menu_slugs'] ) ? (array) $role_perms['menu_slugs'] : array();

		global $menu, $submenu;

		// Top-Level-Menüpunkte prüfen und entfernen
		foreach ( $menu as $position => $item ) {
			$slug = $item[2];

			// Trennlinien (Separatoren) immer entfernen, wenn kein Zugriff auf Nachbar
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

		// Jetzt Separatoren bereinigen: Separatoren ohne benachbarte sichtbare Einträge entfernen
		$this->remove_orphan_separators();
	}

	/**
	 * Prüft ob ein Slug erlaubt ist.
	 * Vergleich ist exakt (URL-Parameter inklusive).
	 */
	private function is_slug_allowed( $slug, array $allowed_slugs ) {
		return in_array( $slug, $allowed_slugs, true );
	}

	/**
	 * Entfernt Separator-Einträge, die keine sichtbaren Menüpunkte mehr daneben haben.
	 */
	private function remove_orphan_separators() {
		global $menu;

		if ( empty( $menu ) ) {
			return;
		}

		ksort( $menu );
		$keys       = array_keys( $menu );
		$total      = count( $keys );

		for ( $i = 0; $i < $total; $i++ ) {
			$pos  = $keys[ $i ];
			$item = $menu[ $pos ];

			if ( strpos( $item[2], 'separator' ) !== 0 ) {
				continue;
			}

			// Prüfe ob es nach diesem Separator noch sichtbare Einträge gibt
			// (bis zum nächsten Separator oder Ende)
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
