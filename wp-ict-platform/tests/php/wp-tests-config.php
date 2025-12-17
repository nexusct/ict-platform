<?php
/**
 * WordPress Test Configuration
 *
 * @package ICT_Platform
 */

// Path to WordPress test installation
define('ABSPATH', '/tmp/wordpress/');

// Test database settings
define('DB_NAME', getenv('DB_NAME') ?: 'wordpress_test');
define('DB_USER', getenv('DB_USER') ?: 'root');
define('DB_PASSWORD', getenv('DB_PASSWORD') ?: '');
define('DB_HOST', getenv('DB_HOST') ?: 'localhost');
define('DB_CHARSET', 'utf8');
define('DB_COLLATE', '');

// Table prefix
$table_prefix = 'wptests_';

// WordPress debugging
define('WP_DEBUG', true);

// Domain settings
define('WP_TESTS_DOMAIN', 'example.org');
define('WP_TESTS_EMAIL', 'admin@example.org');
define('WP_TESTS_TITLE', 'Test Blog');

// PHP settings
define('WP_PHP_BINARY', 'php');

// Locale
define('WPLANG', '');
