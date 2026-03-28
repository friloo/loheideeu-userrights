<?php
/**
 * Inhaltsfilter: Schränkt Admin-Listen für Beiträge und Seiten per pre_get_posts ein.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WP_UserRights_Content_Filter {

	public function __construct() {
		add_action( 'pre_get_posts', array( $this, 'filter_content' ) );
	}

	public function filter_content( $query ) {
		// Nur im Admin-Bereich
		if ( ! is_admin() ) {
			return;
		}

		// Nur Haupt-Query der Admin-Liste
		if ( ! $query->is_main_query() ) {
			return;
		}

		$user = wp_get_current_user();

		// Admins nicht einschränken
		if ( in_array( 'administrator', (array) $user->roles, true ) ) {
			return;
		}

		$permissions = get_option( WP_USERRIGHTS_OPTION, array() );
		$role        = ! empty( $user->roles ) ? reset( $user->roles ) : '';
		$role_perms  = isset( $permissions[ $role ] ) ? $permissions[ $role ] : array();

		$post_type = $query->get( 'post_type' );

		// Beiträge (posts) nach Kategorie filtern
		if ( 'post' === $post_type || ( empty( $post_type ) && is_admin() ) ) {
			$allowed_cats = isset( $role_perms['allowed_categories'] ) ? (array) $role_perms['allowed_categories'] : array();

			if ( ! empty( $allowed_cats ) ) {
				// tax_query verwenden für exakte Kategorie-Filterung
				$query->set( 'tax_query', array(
					array(
						'taxonomy' => 'category',
						'field'    => 'slug',
						'terms'    => $allowed_cats,
					),
				) );
			}
		}

		// Seiten nach Slug filtern
		if ( 'page' === $post_type ) {
			$allowed_page_slugs = isset( $role_perms['allowed_page_slugs'] ) ? (array) $role_perms['allowed_page_slugs'] : array();

			if ( ! empty( $allowed_page_slugs ) ) {
				$page_ids = $this->get_page_ids_by_slugs( $allowed_page_slugs );

				if ( ! empty( $page_ids ) ) {
					$query->set( 'post__in', $page_ids );
				} else {
					// Keine passenden Seiten → nichts anzeigen
					$query->set( 'post__in', array( 0 ) );
				}
			}
		}
	}

	/**
	 * Gibt Seiten-IDs für eine Liste von Slugs zurück.
	 * Unterstützt auch verschachtelte Slugs (z.B. "eltern/kind").
	 *
	 * @param array $slugs Array von Seiten-Slugs
	 * @return array Array von Post-IDs
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
