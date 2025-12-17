<?php
/**
 * PHPUnit Bootstrap File
 *
 * @package ICT_Platform
 */

declare(strict_types=1);

// Define test constants
define('ICT_TESTING', true);
define('ICT_PLATFORM_TESTS_DIR', __DIR__);
define('ICT_PLATFORM_PLUGIN_DIR', dirname(__DIR__, 2) . '/');

// Load Composer autoloader
$composer_autoloader = ICT_PLATFORM_PLUGIN_DIR . 'vendor/autoload.php';
if (file_exists($composer_autoloader)) {
    require_once $composer_autoloader;
}

// Set up WordPress testing environment constants
define('WP_TESTS_CONFIG_PATH', __DIR__ . '/wp-tests-config.php');

// Check if WordPress test library is available
$wp_tests_dir = getenv('WP_TESTS_DIR') ?: '/tmp/wordpress-tests-lib';

if (file_exists($wp_tests_dir . '/includes/functions.php')) {
    // WordPress testing framework is available
    require_once $wp_tests_dir . '/includes/functions.php';

    // Load the plugin before WordPress boots
    tests_add_filter('muplugins_loaded', function () {
        require ICT_PLATFORM_PLUGIN_DIR . 'ict-platform.php';
    });

    // Start up the WP testing environment
    require_once $wp_tests_dir . '/includes/bootstrap.php';
} else {
    // Standalone testing without WordPress
    // Mock WordPress functions for unit testing

    // Load the legacy autoloader
    require_once ICT_PLATFORM_PLUGIN_DIR . 'includes/class-ict-autoloader.php';
    ICT_Autoloader::register();

    // Mock essential WordPress functions
    if (!function_exists('plugin_dir_path')) {
        function plugin_dir_path($file): string
        {
            return dirname($file) . '/';
        }
    }

    if (!function_exists('plugin_dir_url')) {
        function plugin_dir_url($file): string
        {
            return 'http://localhost/wp-content/plugins/' . basename(dirname($file)) . '/';
        }
    }

    if (!function_exists('__')) {
        function __(string $text, string $domain = 'default'): string
        {
            return $text;
        }
    }

    if (!function_exists('_e')) {
        function _e(string $text, string $domain = 'default'): void
        {
            echo $text;
        }
    }

    if (!function_exists('esc_html')) {
        function esc_html(string $text): string
        {
            return htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
        }
    }

    if (!function_exists('esc_attr')) {
        function esc_attr(string $text): string
        {
            return htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
        }
    }

    if (!function_exists('wp_kses')) {
        function wp_kses(string $string, $allowed_html, array $allowed_protocols = []): string
        {
            return strip_tags($string);
        }
    }

    if (!function_exists('sanitize_text_field')) {
        function sanitize_text_field(string $str): string
        {
            return trim(strip_tags($str));
        }
    }

    if (!function_exists('absint')) {
        function absint($maybeint): int
        {
            return abs((int)$maybeint);
        }
    }

    if (!function_exists('wp_json_encode')) {
        function wp_json_encode($data, int $options = 0, int $depth = 512)
        {
            return json_encode($data, $options, $depth);
        }
    }

    if (!function_exists('add_action')) {
        function add_action(string $hook, callable $callback, int $priority = 10, int $accepted_args = 1): bool
        {
            return true;
        }
    }

    if (!function_exists('add_filter')) {
        function add_filter(string $hook, callable $callback, int $priority = 10, int $accepted_args = 1): bool
        {
            return true;
        }
    }

    if (!function_exists('apply_filters')) {
        function apply_filters(string $hook, $value, ...$args)
        {
            return $value;
        }
    }

    if (!function_exists('do_action')) {
        function do_action(string $hook, ...$args): void
        {
            // No-op
        }
    }
}

echo "ICT Platform Test Bootstrap loaded.\n";
