<?php
/**
 * Plugin Name: WP User Rights
 * Plugin URI:  https://loheide.eu
 * Description: Steuert die Sichtbarkeit von WordPress-Backend-Menüpunkten pro Benutzerrolle. Admins sehen alles, alle anderen nur explizit freigegebene Menüpunkte.
 * Version:     1.0.0
 * Author:      Friederich Loheide
 * Author URI:  https://loheide.eu
 * Text Domain: wp-userrights
 * Domain Path: /languages
 * Requires at least: 5.8
 * Requires PHP: 7.4
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'WP_USERRIGHTS_VERSION', '1.0.0' );
define( 'WP_USERRIGHTS_DIR', plugin_dir_path( __FILE__ ) );
define( 'WP_USERRIGHTS_URL', plugin_dir_url( __FILE__ ) );
define( 'WP_USERRIGHTS_OPTION', 'wp_userrights_permissions' );

register_activation_hook( __FILE__, 'wp_userrights_activate' );
function wp_userrights_activate() {
	add_option( WP_USERRIGHTS_OPTION, array() );
}

add_action( 'plugins_loaded', 'wp_userrights_init' );
function wp_userrights_init() {
	// Punkt 4: Übersetzungen laden
	load_plugin_textdomain(
		'wp-userrights',
		false,
		dirname( plugin_basename( __FILE__ ) ) . '/languages'
	);

	if ( ! is_admin() ) {
		return;
	}

	require_once WP_USERRIGHTS_DIR . 'includes/class-admin-menu.php';
	require_once WP_USERRIGHTS_DIR . 'includes/class-settings.php';
	require_once WP_USERRIGHTS_DIR . 'includes/class-content-filter.php';
	require_once WP_USERRIGHTS_DIR . 'includes/class-role-manager.php';

	new WP_UserRights_Admin_Menu();
	new WP_UserRights_Settings();
	new WP_UserRights_Content_Filter();
	new WP_UserRights_Role_Manager();
}
