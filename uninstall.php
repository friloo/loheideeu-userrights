<?php
/**
 * Uninstall-Hook: Wird beim Löschen des Plugins ausgeführt.
 * Entfernt alle gespeicherten Optionen und alle durch das Plugin vergebenen Capabilities.
 */

// Sicherheitscheck: Nur ausführen wenn WordPress den Uninstall-Prozess gestartet hat
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

$permissions = get_option( 'wp_userrights_permissions', array() );

// Alle durch dieses Plugin verwalteten Capabilities von den Rollen entfernen
foreach ( $permissions as $role_key => $role_data ) {
	$managed_caps = isset( $role_data['managed_caps'] ) ? (array) $role_data['managed_caps'] : array();

	if ( empty( $managed_caps ) ) {
		continue;
	}

	$role_obj = get_role( $role_key );
	if ( ! $role_obj ) {
		continue;
	}

	foreach ( $managed_caps as $cap ) {
		$role_obj->remove_cap( $cap );
	}
}

// Plugin-Option aus der Datenbank entfernen
delete_option( 'wp_userrights_permissions' );

// Plugin-erstellte Rollen von allen Benutzern entfernen und Rollen-Definitionen löschen
$managed_roles = (array) get_option( 'wp_userrights_managed_roles', array() );
foreach ( $managed_roles as $role_slug ) {
	foreach ( get_users( array( 'role' => $role_slug ) ) as $user ) {
		$user->remove_role( $role_slug );
	}
	remove_role( $role_slug );
}
delete_option( 'wp_userrights_managed_roles' );
