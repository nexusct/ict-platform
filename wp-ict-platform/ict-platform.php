<?php
/**
 * Plugin Name:       ICT Platform
 * Plugin URI:        https://github.com/yourusername/ict-platform
 * Description:       Complete ICT/electrical contracting operations platform with Zoho integration (CRM, FSM, Books, People, Desk). Manages projects, time tracking, resources, inventory, and procurement.
 * Version:           1.0.0
 * Requires at least: 6.4
 * Requires PHP:      8.1
 * Author:            Your Name
 * Author URI:        https://yourwebsite.com
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       ict-platform
 * Domain Path:       /languages
 *
 * @package ICT_Platform
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Current plugin version.
 * Start at version 1.0.0 and use SemVer - https://semver.org
 */
define( 'ICT_PLATFORM_VERSION', '1.0.0' );

/**
 * Plugin constants
 */
define( 'ICT_PLATFORM_PLUGIN_FILE', __FILE__ );
define( 'ICT_PLATFORM_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'ICT_PLATFORM_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'ICT_PLATFORM_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );

/**
 * Database table name constants
 */
global $wpdb;
define( 'ICT_PROJECTS_TABLE', $wpdb->prefix . 'ict_projects' );
define( 'ICT_TIME_ENTRIES_TABLE', $wpdb->prefix . 'ict_time_entries' );
define( 'ICT_INVENTORY_ITEMS_TABLE', $wpdb->prefix . 'ict_inventory_items' );
define( 'ICT_PURCHASE_ORDERS_TABLE', $wpdb->prefix . 'ict_purchase_orders' );
define( 'ICT_PROJECT_RESOURCES_TABLE', $wpdb->prefix . 'ict_project_resources' );
define( 'ICT_SYNC_QUEUE_TABLE', $wpdb->prefix . 'ict_sync_queue' );
define( 'ICT_SYNC_LOG_TABLE', $wpdb->prefix . 'ict_sync_log' );
define( 'ICT_SIGNATURES_TABLE', $wpdb->prefix . 'ict_signatures' );

/**
 * Autoloader for plugin classes
 */
require_once ICT_PLATFORM_PLUGIN_DIR . 'includes/class-ict-autoloader.php';
ICT_Autoloader::register();

/**
 * The code that runs during plugin activation.
 */
function activate_ict_platform() {
	require_once ICT_PLATFORM_PLUGIN_DIR . 'includes/class-ict-activator.php';
	ICT_Activator::activate();
}

/**
 * The code that runs during plugin deactivation.
 */
function deactivate_ict_platform() {
	require_once ICT_PLATFORM_PLUGIN_DIR . 'includes/class-ict-deactivator.php';
	ICT_Deactivator::deactivate();
}

/**
 * The code that runs during plugin uninstallation.
 */
function uninstall_ict_platform() {
	require_once ICT_PLATFORM_PLUGIN_DIR . 'includes/class-ict-uninstaller.php';
	ICT_Uninstaller::uninstall();
}

register_activation_hook( __FILE__, 'activate_ict_platform' );
register_deactivation_hook( __FILE__, 'deactivate_ict_platform' );
register_uninstall_hook( __FILE__, 'uninstall_ict_platform' );

/**
 * Begins execution of the plugin.
 *
 * Since everything within the plugin is registered via hooks,
 * then kicking off the plugin from this point in the file does
 * not affect the page life cycle.
 *
 * @since    1.0.0
 */
function run_ict_platform() {
	$plugin = new ICT_Core();
	$plugin->run();
}

run_ict_platform();
