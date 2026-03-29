<?php
/**
 * Rollenverwaltung: Erstellen/Löschen eigener Rollen und Zuweisen an Benutzer per AJAX.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WP_UserRights_Role_Manager {

	const ROLES_OPTION = 'wp_userrights_managed_roles';

	public function __construct() {
		add_action( 'admin_post_wpur_create_role',      array( $this, 'create_role' ) );
		add_action( 'admin_post_wpur_delete_role',      array( $this, 'delete_role' ) );
		add_action( 'wp_ajax_wpur_toggle_user_role',    array( $this, 'ajax_toggle_user_role' ) );
	}

	// -------------------------------------------------------------------------
	// Lesezugriff (statisch, nutzbar von uninstall.php)
	// -------------------------------------------------------------------------

	public static function get_managed_roles() {
		return (array) get_option( self::ROLES_OPTION, array() );
	}

	// -------------------------------------------------------------------------
	// Rolle erstellen
	// -------------------------------------------------------------------------

	public function create_role() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Keine Berechtigung.', 'wp-userrights' ) );
		}

		if ( ! wp_verify_nonce(
			isset( $_POST['wpur_nonce'] ) ? $_POST['wpur_nonce'] : '',
			'wpur_create_role'
		) ) {
			wp_die( esc_html__( 'Sicherheitsprüfung fehlgeschlagen.', 'wp-userrights' ) );
		}

		$name  = sanitize_text_field( isset( $_POST['role_name'] ) ? $_POST['role_name'] : '' );
		$slug  = sanitize_key( isset( $_POST['role_slug'] ) ? $_POST['role_slug'] : '' );
		$error = '';

		if ( empty( $name ) || empty( $slug ) ) {
			$error = 'empty';
		} elseif ( wp_roles()->is_role( $slug ) ) {
			$error = 'exists';
		} else {
			$result = add_role( $slug, $name, array( 'read' => true ) );
			if ( $result ) {
				$managed   = self::get_managed_roles();
				$managed[] = $slug;
				update_option( self::ROLES_OPTION, array_unique( $managed ) );
			} else {
				$error = 'failed';
			}
		}

		$args = array( 'page' => 'wp-userrights', 'tab' => 'rollen' );
		if ( $error ) {
			$args['role_error'] = $error;
		} else {
			$args['role_created'] = '1';
		}

		wp_safe_redirect( add_query_arg( $args, admin_url( 'admin.php' ) ) );
		exit;
	}

	// -------------------------------------------------------------------------
	// Rolle löschen
	// -------------------------------------------------------------------------

	public function delete_role() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Keine Berechtigung.', 'wp-userrights' ) );
		}

		if ( ! wp_verify_nonce(
			isset( $_POST['wpur_nonce'] ) ? $_POST['wpur_nonce'] : '',
			'wpur_delete_role'
		) ) {
			wp_die( esc_html__( 'Sicherheitsprüfung fehlgeschlagen.', 'wp-userrights' ) );
		}

		$slug    = sanitize_key( isset( $_POST['role_slug'] ) ? $_POST['role_slug'] : '' );
		$managed = self::get_managed_roles();

		if ( ! in_array( $slug, $managed, true ) ) {
			wp_die( esc_html__( 'Diese Rolle wird nicht von diesem Plugin verwaltet.', 'wp-userrights' ) );
		}

		// Rolle von allen Benutzern entfernen
		foreach ( get_users( array( 'role' => $slug ) ) as $user ) {
			$user->remove_role( $slug );
		}

		// Rolle aus WordPress entfernen
		remove_role( $slug );

		// Aus verwalteter Liste entfernen
		update_option( self::ROLES_OPTION, array_values( array_diff( $managed, array( $slug ) ) ) );

		// Berechtigungen für diese Rolle bereinigen
		$permissions = get_option( WP_USERRIGHTS_OPTION, array() );
		unset( $permissions[ $slug ] );
		update_option( WP_USERRIGHTS_OPTION, $permissions );

		wp_safe_redirect( add_query_arg(
			array( 'page' => 'wp-userrights', 'tab' => 'rollen', 'role_deleted' => '1' ),
			admin_url( 'admin.php' )
		) );
		exit;
	}

	// -------------------------------------------------------------------------
	// AJAX: Rolle einem Benutzer hinzufügen oder entfernen
	// -------------------------------------------------------------------------

	public function ajax_toggle_user_role() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Keine Berechtigung.', 'wp-userrights' ) ), 403 );
		}

		if ( ! wp_verify_nonce(
			isset( $_POST['nonce'] ) ? $_POST['nonce'] : '',
			'wpur_toggle_user_role'
		) ) {
			wp_send_json_error( array( 'message' => __( 'Nonce ungültig.', 'wp-userrights' ) ), 403 );
		}

		$user_id  = absint( isset( $_POST['user_id'] ) ? $_POST['user_id'] : 0 );
		$role     = sanitize_key( isset( $_POST['role'] ) ? $_POST['role'] : '' );
		$assigned = filter_var( isset( $_POST['assigned'] ) ? $_POST['assigned'] : false, FILTER_VALIDATE_BOOLEAN );

		// Nur plugin-verwaltete Rollen dürfen geändert werden
		if ( ! in_array( $role, self::get_managed_roles(), true ) ) {
			wp_send_json_error( array( 'message' => __( 'Rolle nicht erlaubt.', 'wp-userrights' ) ), 400 );
		}

		$user = get_userdata( $user_id );
		if ( ! $user ) {
			wp_send_json_error( array( 'message' => __( 'Benutzer nicht gefunden.', 'wp-userrights' ) ), 404 );
		}

		// Administrator darf keine eigenen Rollen bekommen
		if ( in_array( 'administrator', (array) $user->roles, true ) ) {
			wp_send_json_error( array( 'message' => __( 'Administratoren können nicht geändert werden.', 'wp-userrights' ) ), 400 );
		}

		if ( $assigned ) {
			$user->add_role( $role );
		} else {
			$user->remove_role( $role );
		}

		wp_send_json_success( array(
			'user_id'  => $user_id,
			'role'     => $role,
			'assigned' => $assigned,
		) );
	}
}
