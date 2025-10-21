<?php
/**
 * Define the internationalization functionality
 *
 * @package ICT_Platform
 * @since   1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class ICT_i18n
 *
 * Loads and defines the internationalization files for this plugin
 * so that it is ready for translation.
 */
class ICT_i18n {

	/**
	 * Load the plugin text domain for translation.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function load_plugin_textdomain() {
		load_plugin_textdomain(
			'ict-platform',
			false,
			dirname( dirname( plugin_basename( __FILE__ ) ) ) . '/languages/'
		);
	}
}
