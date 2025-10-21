<?php
/**
 * Autoloader for ICT Platform classes
 *
 * @package ICT_Platform
 * @since   1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class ICT_Autoloader
 *
 * PSR-4 compliant autoloader for plugin classes.
 */
class ICT_Autoloader {

	/**
	 * Register the autoloader.
	 *
	 * @return void
	 */
	public static function register() {
		spl_autoload_register( array( __CLASS__, 'autoload' ) );
	}

	/**
	 * Autoload classes.
	 *
	 * @param string $class The fully-qualified class name.
	 * @return void
	 */
	public static function autoload( $class ) {
		// Project-specific namespace prefix
		$prefix = 'ICT_';

		// Base directory for the namespace prefix
		$base_dir = ICT_PLATFORM_PLUGIN_DIR . 'includes/';

		// Does the class use the namespace prefix?
		$len = strlen( $prefix );
		if ( strncmp( $prefix, $class, $len ) !== 0 ) {
			// No, move to the next registered autoloader
			return;
		}

		// Get the relative class name
		$relative_class = substr( $class, $len );

		// Convert class name to filename
		// ICT_Admin_Settings -> class-ict-admin-settings.php
		$file_name = 'class-' . strtolower( str_replace( '_', '-', $class ) ) . '.php';

		// Map class prefixes to directories
		$class_maps = array(
			'Admin_'       => 'admin/',
			'Public_'      => 'public/',
			'API_'         => 'api/',
			'Model_'       => 'models/',
			'PostType_'    => 'post-types/',
			'Taxonomy_'    => 'taxonomies/',
			'Integration_' => 'integrations/',
			'Zoho_'        => 'integrations/zoho/',
			'REST_'        => 'api/rest/',
			'Webhook_'     => 'api/webhooks/',
			'Database_'    => 'database/',
			'Sync_'        => 'sync/',
		);

		// Determine subdirectory based on class name
		$subdir = '';
		foreach ( $class_maps as $class_prefix => $directory ) {
			if ( strpos( $relative_class, $class_prefix ) === 0 ) {
				$subdir = $directory;
				break;
			}
		}

		// Build the file path
		$file = $base_dir . $subdir . $file_name;

		// If the file exists, require it
		if ( file_exists( $file ) ) {
			require_once $file;
		}
	}
}
