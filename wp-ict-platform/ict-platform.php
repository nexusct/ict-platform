<?php

declare(strict_types=1);

/**
 * Plugin Name:       ICT Platform
 * Plugin URI:        https://github.com/yourusername/ict-platform
 * Description:       Complete ICT/electrical contracting operations platform with Zoho integration (CRM, FSM, Books, People, Desk). Manages projects, time tracking, resources, inventory, and procurement.
 * Version:           2.0.0
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
if (!defined('WPINC')) {
    die;
}

/**
 * Current plugin version.
 * Start at version 2.0.0 and use SemVer - https://semver.org
 * v2.0.0 - PSR-4 refactoring with namespaces and DI container
 */
define('ICT_PLATFORM_VERSION', '2.0.0');

/**
 * Plugin constants
 */
define('ICT_PLATFORM_PLUGIN_FILE', __FILE__);
define('ICT_PLATFORM_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('ICT_PLATFORM_PLUGIN_URL', plugin_dir_url(__FILE__));
define('ICT_PLATFORM_PLUGIN_BASENAME', plugin_basename(__FILE__));

/**
 * Database table name constants
 */
global $wpdb;
define('ICT_PROJECTS_TABLE', $wpdb->prefix . 'ict_projects');
define('ICT_TIME_ENTRIES_TABLE', $wpdb->prefix . 'ict_time_entries');
define('ICT_INVENTORY_ITEMS_TABLE', $wpdb->prefix . 'ict_inventory_items');
define('ICT_PURCHASE_ORDERS_TABLE', $wpdb->prefix . 'ict_purchase_orders');
define('ICT_PROJECT_RESOURCES_TABLE', $wpdb->prefix . 'ict_project_resources');
define('ICT_SYNC_QUEUE_TABLE', $wpdb->prefix . 'ict_sync_queue');
define('ICT_SYNC_LOG_TABLE', $wpdb->prefix . 'ict_sync_log');

/**
 * Load Composer autoloader for PSR-4 namespaced classes
 */
$composer_autoloader = ICT_PLATFORM_PLUGIN_DIR . 'vendor/autoload.php';
if (file_exists($composer_autoloader)) {
    require_once $composer_autoloader;
} else {
    // Fallback: Manual PSR-4 autoloader for src/ directory
    spl_autoload_register(function (string $class): void {
        $prefix = 'ICT_Platform\\';
        $base_dir = ICT_PLATFORM_PLUGIN_DIR . 'src/';

        $len = strlen($prefix);
        if (strncmp($prefix, $class, $len) !== 0) {
            return;
        }

        $relative_class = substr($class, $len);
        $file = $base_dir . str_replace('\\', '/', $relative_class) . '.php';

        if (file_exists($file)) {
            require $file;
        }
    });
}

/**
 * Legacy autoloader for backward compatibility with non-namespaced classes
 */
require_once ICT_PLATFORM_PLUGIN_DIR . 'includes/class-ict-autoloader.php';
ICT_Autoloader::register();

/**
 * The code that runs during plugin activation.
 */
function activate_ict_platform(): void
{
    require_once ICT_PLATFORM_PLUGIN_DIR . 'includes/class-ict-activator.php';
    ICT_Activator::activate();
}

/**
 * The code that runs during plugin deactivation.
 */
function deactivate_ict_platform(): void
{
    require_once ICT_PLATFORM_PLUGIN_DIR . 'includes/class-ict-deactivator.php';
    ICT_Deactivator::deactivate();
}

/**
 * The code that runs during plugin uninstallation.
 */
function uninstall_ict_platform(): void
{
    require_once ICT_PLATFORM_PLUGIN_DIR . 'includes/class-ict-uninstaller.php';
    ICT_Uninstaller::uninstall();
}

register_activation_hook(__FILE__, 'activate_ict_platform');
register_deactivation_hook(__FILE__, 'deactivate_ict_platform');
register_uninstall_hook(__FILE__, 'uninstall_ict_platform');

/**
 * Begins execution of the plugin.
 *
 * Initializes both the legacy architecture (ICT_Core) and the new
 * PSR-4 namespaced architecture (Application) for gradual migration.
 *
 * @since 1.0.0
 * @since 2.0.0 Added PSR-4 Application bootstrap
 */
function run_ict_platform(): void
{
    // Boot the new PSR-4 Application
    $application = new ICT_Platform\Core\Application('ict-platform', ICT_PLATFORM_VERSION);
    $application->boot();

    // Run legacy plugin architecture for backward compatibility
    $plugin = new ICT_Core();
    $plugin->run();

    // Allow other plugins/themes to access the application
    do_action('ict_platform_loaded', $application);
}

/**
 * Get the Application instance
 *
 * @return ICT_Platform\Core\Application|null
 */
function ict_platform(): ?ICT_Platform\Core\Application
{
    static $instance = null;

    if ($instance === null) {
        $instance = ICT_Platform\Container\Container::getInstance()->resolve(
            ICT_Platform\Core\Application::class
        );
    }

    return $instance;
}

run_ict_platform();
