<?php
/**
 * Multi-Currency Support
 *
 * Provides comprehensive multi-currency functionality including:
 * - Multiple currency definitions with symbols and formats
 * - Real-time exchange rate updates from multiple providers
 * - Automatic currency conversion for quotes, invoices, and reports
 * - Currency rounding rules and precision settings
 * - Historical exchange rate tracking
 * - Per-client default currency settings
 * - Multi-currency financial reporting
 *
 * @package    ICT_Platform
 * @subpackage Features
 * @since      1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Class ICT_Multi_Currency
 *
 * Handles all multi-currency operations for the ICT Platform.
 */
class ICT_Multi_Currency {

    /**
     * Singleton instance.
     *
     * @var ICT_Multi_Currency
     */
    private static $instance = null;

    /**
     * Table names.
     *
     * @var array
     */
    private $tables = array();

    /**
     * Base currency.
     *
     * @var string
     */
    private $base_currency = 'USD';

    /**
     * Exchange rate providers.
     *
     * @var array
     */
    private $rate_providers = array(
        'exchangerate-api' => array(
            'name'     => 'ExchangeRate-API',
            'url'      => 'https://v6.exchangerate-api.com/v6/{api_key}/latest/{base}',
            'free'     => true,
        ),
        'openexchangerates' => array(
            'name'     => 'Open Exchange Rates',
            'url'      => 'https://openexchangerates.org/api/latest.json?app_id={api_key}&base={base}',
            'free'     => true,
        ),
        'fixer' => array(
            'name'     => 'Fixer.io',
            'url'      => 'http://data.fixer.io/api/latest?access_key={api_key}&base={base}',
            'free'     => false,
        ),
        'currencylayer' => array(
            'name'     => 'CurrencyLayer',
            'url'      => 'http://api.currencylayer.com/live?access_key={api_key}&source={base}',
            'free'     => true,
        ),
    );

    /**
     * Get singleton instance.
     *
     * @return ICT_Multi_Currency
     */
    public static function get_instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructor.
     */
    private function __construct() {
        global $wpdb;

        $this->tables = array(
            'currencies'     => $wpdb->prefix . 'ict_currencies',
            'exchange_rates' => $wpdb->prefix . 'ict_exchange_rates',
            'rate_history'   => $wpdb->prefix . 'ict_rate_history',
            'conversions'    => $wpdb->prefix . 'ict_currency_conversions',
        );

        $this->base_currency = get_option( 'ict_base_currency', 'USD' );

        $this->init_hooks();
    }

    /**
     * Initialize hooks.
     */
    private function init_hooks() {
        // Database setup.
        register_activation_hook( ICT_PLUGIN_FILE, array( $this, 'maybe_create_tables' ) );
        add_action( 'plugins_loaded', array( $this, 'maybe_create_tables' ) );

        // REST API.
        add_action( 'rest_api_init', array( $this, 'register_rest_routes' ) );

        // Scheduled rate updates.
        add_action( 'ict_update_exchange_rates', array( $this, 'scheduled_rate_update' ) );
        add_action( 'init', array( $this, 'schedule_rate_updates' ) );

        // Currency display filters.
        add_filter( 'ict_format_amount', array( $this, 'format_amount' ), 10, 3 );
        add_filter( 'ict_convert_amount', array( $this, 'convert_amount' ), 10, 4 );

        // Seed default currencies.
        add_action( 'admin_init', array( $this, 'maybe_seed_currencies' ) );
    }

    /**
     * Create database tables.
     */
    public function maybe_create_tables() {
        global $wpdb;

        $charset_collate = $wpdb->get_charset_collate();

        $sql = array();

        // Currencies table.
        $sql[] = "CREATE TABLE IF NOT EXISTS {$this->tables['currencies']} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            code varchar(3) NOT NULL,
            name varchar(100) NOT NULL,
            symbol varchar(10) NOT NULL,
            symbol_position enum('before','after') DEFAULT 'before',
            decimal_separator varchar(5) DEFAULT '.',
            thousand_separator varchar(5) DEFAULT ',',
            decimals tinyint(1) DEFAULT 2,
            rounding_increment decimal(10,4) DEFAULT 0.01,
            is_active tinyint(1) DEFAULT 1,
            is_base tinyint(1) DEFAULT 0,
            sort_order int(11) DEFAULT 0,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY code (code),
            KEY is_active (is_active)
        ) $charset_collate;";

        // Exchange rates table.
        $sql[] = "CREATE TABLE IF NOT EXISTS {$this->tables['exchange_rates']} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            from_currency varchar(3) NOT NULL,
            to_currency varchar(3) NOT NULL,
            rate decimal(20,10) NOT NULL,
            provider varchar(50) DEFAULT NULL,
            fetched_at datetime DEFAULT CURRENT_TIMESTAMP,
            expires_at datetime DEFAULT NULL,
            is_manual tinyint(1) DEFAULT 0,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY currency_pair (from_currency, to_currency),
            KEY expires_at (expires_at)
        ) $charset_collate;";

        // Rate history table.
        $sql[] = "CREATE TABLE IF NOT EXISTS {$this->tables['rate_history']} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            from_currency varchar(3) NOT NULL,
            to_currency varchar(3) NOT NULL,
            rate decimal(20,10) NOT NULL,
            provider varchar(50) DEFAULT NULL,
            recorded_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY currency_pair_date (from_currency, to_currency, recorded_at),
            KEY recorded_at (recorded_at)
        ) $charset_collate;";

        // Conversions log table.
        $sql[] = "CREATE TABLE IF NOT EXISTS {$this->tables['conversions']} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            entity_type varchar(50) NOT NULL,
            entity_id bigint(20) unsigned NOT NULL,
            original_currency varchar(3) NOT NULL,
            original_amount decimal(20,4) NOT NULL,
            converted_currency varchar(3) NOT NULL,
            converted_amount decimal(20,4) NOT NULL,
            exchange_rate decimal(20,10) NOT NULL,
            rate_date datetime NOT NULL,
            user_id bigint(20) unsigned DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY entity (entity_type, entity_id),
            KEY currencies (original_currency, converted_currency),
            KEY created_at (created_at)
        ) $charset_collate;";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        foreach ( $sql as $query ) {
            dbDelta( $query );
        }
    }

    /**
     * Schedule exchange rate updates.
     */
    public function schedule_rate_updates() {
        if ( ! wp_next_scheduled( 'ict_update_exchange_rates' ) ) {
            $frequency = get_option( 'ict_rate_update_frequency', 'hourly' );
            wp_schedule_event( time(), $frequency, 'ict_update_exchange_rates' );
        }
    }

    /**
     * Seed default currencies.
     */
    public function maybe_seed_currencies() {
        global $wpdb;

        $count = $wpdb->get_var( "SELECT COUNT(*) FROM {$this->tables['currencies']}" );

        if ( $count > 0 ) {
            return;
        }

        $currencies = array(
            array( 'USD', 'US Dollar', '$', 'before', '.', ',', 2, 0.01, 1 ),
            array( 'EUR', 'Euro', "\u{20AC}", 'before', ',', '.', 2, 0.01, 0 ),
            array( 'GBP', 'British Pound', "\u{00A3}", 'before', '.', ',', 2, 0.01, 0 ),
            array( 'CAD', 'Canadian Dollar', 'C$', 'before', '.', ',', 2, 0.01, 0 ),
            array( 'AUD', 'Australian Dollar', 'A$', 'before', '.', ',', 2, 0.01, 0 ),
            array( 'NZD', 'New Zealand Dollar', 'NZ$', 'before', '.', ',', 2, 0.01, 0 ),
            array( 'JPY', 'Japanese Yen', "\u{00A5}", 'before', '.', ',', 0, 1, 0 ),
            array( 'CHF', 'Swiss Franc', 'CHF', 'before', '.', "'", 2, 0.05, 0 ),
            array( 'CNY', 'Chinese Yuan', "\u{00A5}", 'before', '.', ',', 2, 0.01, 0 ),
            array( 'INR', 'Indian Rupee', "\u{20B9}", 'before', '.', ',', 2, 0.01, 0 ),
            array( 'MXN', 'Mexican Peso', 'MX$', 'before', '.', ',', 2, 0.01, 0 ),
            array( 'BRL', 'Brazilian Real', 'R$', 'before', ',', '.', 2, 0.01, 0 ),
            array( 'ZAR', 'South African Rand', 'R', 'before', '.', ',', 2, 0.01, 0 ),
            array( 'SGD', 'Singapore Dollar', 'S$', 'before', '.', ',', 2, 0.01, 0 ),
            array( 'HKD', 'Hong Kong Dollar', 'HK$', 'before', '.', ',', 2, 0.01, 0 ),
            array( 'SEK', 'Swedish Krona', 'kr', 'after', ',', ' ', 2, 0.01, 0 ),
            array( 'NOK', 'Norwegian Krone', 'kr', 'after', ',', ' ', 2, 0.01, 0 ),
            array( 'DKK', 'Danish Krone', 'kr', 'after', ',', '.', 2, 0.01, 0 ),
            array( 'PLN', 'Polish Zloty', "z\u{0142}", 'after', ',', ' ', 2, 0.01, 0 ),
            array( 'KRW', 'South Korean Won', "\u{20A9}", 'before', '.', ',', 0, 1, 0 ),
        );

        foreach ( $currencies as $index => $currency ) {
            $wpdb->insert(
                $this->tables['currencies'],
                array(
                    'code'               => $currency[0],
                    'name'               => $currency[1],
                    'symbol'             => $currency[2],
                    'symbol_position'    => $currency[3],
                    'decimal_separator'  => $currency[4],
                    'thousand_separator' => $currency[5],
                    'decimals'           => $currency[6],
                    'rounding_increment' => $currency[7],
                    'is_base'            => $currency[8],
                    'is_active'          => 1,
                    'sort_order'         => $index,
                ),
                array( '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%f', '%d', '%d', '%d' )
            );
        }
    }

    /**
     * Register REST API routes.
     */
    public function register_rest_routes() {
        $namespace = 'ict/v1';

        // Currencies.
        register_rest_route(
            $namespace,
            '/currencies',
            array(
                array(
                    'methods'             => WP_REST_Server::READABLE,
                    'callback'            => array( $this, 'rest_get_currencies' ),
                    'permission_callback' => array( $this, 'check_read_permission' ),
                ),
                array(
                    'methods'             => WP_REST_Server::CREATABLE,
                    'callback'            => array( $this, 'rest_create_currency' ),
                    'permission_callback' => array( $this, 'check_manage_permission' ),
                ),
            )
        );

        register_rest_route(
            $namespace,
            '/currencies/(?P<id>\d+)',
            array(
                array(
                    'methods'             => WP_REST_Server::READABLE,
                    'callback'            => array( $this, 'rest_get_currency' ),
                    'permission_callback' => array( $this, 'check_read_permission' ),
                ),
                array(
                    'methods'             => WP_REST_Server::EDITABLE,
                    'callback'            => array( $this, 'rest_update_currency' ),
                    'permission_callback' => array( $this, 'check_manage_permission' ),
                ),
                array(
                    'methods'             => WP_REST_Server::DELETABLE,
                    'callback'            => array( $this, 'rest_delete_currency' ),
                    'permission_callback' => array( $this, 'check_manage_permission' ),
                ),
            )
        );

        // Exchange rates.
        register_rest_route(
            $namespace,
            '/exchange-rates',
            array(
                array(
                    'methods'             => WP_REST_Server::READABLE,
                    'callback'            => array( $this, 'rest_get_exchange_rates' ),
                    'permission_callback' => array( $this, 'check_read_permission' ),
                ),
                array(
                    'methods'             => WP_REST_Server::CREATABLE,
                    'callback'            => array( $this, 'rest_set_exchange_rate' ),
                    'permission_callback' => array( $this, 'check_manage_permission' ),
                ),
            )
        );

        register_rest_route(
            $namespace,
            '/exchange-rates/refresh',
            array(
                'methods'             => WP_REST_Server::CREATABLE,
                'callback'            => array( $this, 'rest_refresh_rates' ),
                'permission_callback' => array( $this, 'check_manage_permission' ),
            )
        );

        register_rest_route(
            $namespace,
            '/exchange-rates/history',
            array(
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => array( $this, 'rest_get_rate_history' ),
                'permission_callback' => array( $this, 'check_read_permission' ),
            )
        );

        // Conversion.
        register_rest_route(
            $namespace,
            '/convert',
            array(
                'methods'             => WP_REST_Server::CREATABLE,
                'callback'            => array( $this, 'rest_convert_amount' ),
                'permission_callback' => array( $this, 'check_read_permission' ),
            )
        );

        // Base currency.
        register_rest_route(
            $namespace,
            '/base-currency',
            array(
                array(
                    'methods'             => WP_REST_Server::READABLE,
                    'callback'            => array( $this, 'rest_get_base_currency' ),
                    'permission_callback' => array( $this, 'check_read_permission' ),
                ),
                array(
                    'methods'             => WP_REST_Server::EDITABLE,
                    'callback'            => array( $this, 'rest_set_base_currency' ),
                    'permission_callback' => array( $this, 'check_manage_permission' ),
                ),
            )
        );

        // Rate providers.
        register_rest_route(
            $namespace,
            '/rate-providers',
            array(
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => array( $this, 'rest_get_rate_providers' ),
                'permission_callback' => array( $this, 'check_read_permission' ),
            )
        );
    }

    /**
     * Check read permission.
     *
     * @return bool
     */
    public function check_read_permission() {
        return current_user_can( 'read' );
    }

    /**
     * Check manage permission.
     *
     * @return bool
     */
    public function check_manage_permission() {
        return current_user_can( 'manage_options' );
    }

    /**
     * Get all currencies.
     *
     * @param WP_REST_Request $request Request object.
     * @return WP_REST_Response
     */
    public function rest_get_currencies( $request ) {
        global $wpdb;

        $active_only = $request->get_param( 'active_only' );

        $sql = "SELECT * FROM {$this->tables['currencies']}";
        if ( $active_only ) {
            $sql .= " WHERE is_active = 1";
        }
        $sql .= " ORDER BY sort_order ASC, code ASC";

        $currencies = $wpdb->get_results( $sql );

        return rest_ensure_response( array(
            'success'    => true,
            'currencies' => $currencies,
        ) );
    }

    /**
     * Get single currency.
     *
     * @param WP_REST_Request $request Request object.
     * @return WP_REST_Response
     */
    public function rest_get_currency( $request ) {
        global $wpdb;

        $id = intval( $request->get_param( 'id' ) );

        $currency = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$this->tables['currencies']} WHERE id = %d",
                $id
            )
        );

        if ( ! $currency ) {
            return new WP_Error( 'not_found', 'Currency not found', array( 'status' => 404 ) );
        }

        return rest_ensure_response( array(
            'success'  => true,
            'currency' => $currency,
        ) );
    }

    /**
     * Create currency.
     *
     * @param WP_REST_Request $request Request object.
     * @return WP_REST_Response
     */
    public function rest_create_currency( $request ) {
        global $wpdb;

        $data = array(
            'code'               => strtoupper( sanitize_text_field( $request->get_param( 'code' ) ) ),
            'name'               => sanitize_text_field( $request->get_param( 'name' ) ),
            'symbol'             => sanitize_text_field( $request->get_param( 'symbol' ) ),
            'symbol_position'    => $request->get_param( 'symbol_position' ) ?: 'before',
            'decimal_separator'  => sanitize_text_field( $request->get_param( 'decimal_separator' ) ) ?: '.',
            'thousand_separator' => sanitize_text_field( $request->get_param( 'thousand_separator' ) ) ?: ',',
            'decimals'           => intval( $request->get_param( 'decimals' ) ) ?: 2,
            'rounding_increment' => floatval( $request->get_param( 'rounding_increment' ) ) ?: 0.01,
            'is_active'          => $request->get_param( 'is_active' ) !== false ? 1 : 0,
        );

        // Validate required fields.
        if ( empty( $data['code'] ) || strlen( $data['code'] ) !== 3 ) {
            return new WP_Error( 'invalid_code', 'Currency code must be 3 characters', array( 'status' => 400 ) );
        }

        // Check for duplicate.
        $existing = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT id FROM {$this->tables['currencies']} WHERE code = %s",
                $data['code']
            )
        );

        if ( $existing ) {
            return new WP_Error( 'duplicate', 'Currency code already exists', array( 'status' => 409 ) );
        }

        $wpdb->insert(
            $this->tables['currencies'],
            $data,
            array( '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%f', '%d' )
        );

        $currency_id = $wpdb->insert_id;

        return rest_ensure_response( array(
            'success'     => true,
            'currency_id' => $currency_id,
            'message'     => 'Currency created successfully',
        ) );
    }

    /**
     * Update currency.
     *
     * @param WP_REST_Request $request Request object.
     * @return WP_REST_Response
     */
    public function rest_update_currency( $request ) {
        global $wpdb;

        $id = intval( $request->get_param( 'id' ) );

        $currency = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$this->tables['currencies']} WHERE id = %d",
                $id
            )
        );

        if ( ! $currency ) {
            return new WP_Error( 'not_found', 'Currency not found', array( 'status' => 404 ) );
        }

        $data = array();
        $format = array();

        $fields = array(
            'name'               => '%s',
            'symbol'             => '%s',
            'symbol_position'    => '%s',
            'decimal_separator'  => '%s',
            'thousand_separator' => '%s',
            'decimals'           => '%d',
            'rounding_increment' => '%f',
            'is_active'          => '%d',
            'sort_order'         => '%d',
        );

        foreach ( $fields as $field => $field_format ) {
            $value = $request->get_param( $field );
            if ( $value !== null ) {
                $data[ $field ] = $value;
                $format[] = $field_format;
            }
        }

        if ( ! empty( $data ) ) {
            $wpdb->update(
                $this->tables['currencies'],
                $data,
                array( 'id' => $id ),
                $format,
                array( '%d' )
            );
        }

        return rest_ensure_response( array(
            'success' => true,
            'message' => 'Currency updated successfully',
        ) );
    }

    /**
     * Delete currency.
     *
     * @param WP_REST_Request $request Request object.
     * @return WP_REST_Response
     */
    public function rest_delete_currency( $request ) {
        global $wpdb;

        $id = intval( $request->get_param( 'id' ) );

        $currency = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$this->tables['currencies']} WHERE id = %d",
                $id
            )
        );

        if ( ! $currency ) {
            return new WP_Error( 'not_found', 'Currency not found', array( 'status' => 404 ) );
        }

        // Don't delete base currency.
        if ( $currency->is_base ) {
            return new WP_Error( 'cannot_delete_base', 'Cannot delete base currency', array( 'status' => 400 ) );
        }

        $wpdb->delete(
            $this->tables['currencies'],
            array( 'id' => $id ),
            array( '%d' )
        );

        // Clean up exchange rates.
        $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM {$this->tables['exchange_rates']}
                WHERE from_currency = %s OR to_currency = %s",
                $currency->code,
                $currency->code
            )
        );

        return rest_ensure_response( array(
            'success' => true,
            'message' => 'Currency deleted successfully',
        ) );
    }

    /**
     * Get exchange rates.
     *
     * @param WP_REST_Request $request Request object.
     * @return WP_REST_Response
     */
    public function rest_get_exchange_rates( $request ) {
        global $wpdb;

        $from_currency = $request->get_param( 'from' );
        $to_currency = $request->get_param( 'to' );

        $sql = "SELECT * FROM {$this->tables['exchange_rates']} WHERE 1=1";
        $args = array();

        if ( $from_currency ) {
            $sql .= " AND from_currency = %s";
            $args[] = $from_currency;
        }

        if ( $to_currency ) {
            $sql .= " AND to_currency = %s";
            $args[] = $to_currency;
        }

        $sql .= " ORDER BY from_currency, to_currency";

        if ( ! empty( $args ) ) {
            $rates = $wpdb->get_results( $wpdb->prepare( $sql, $args ) );
        } else {
            $rates = $wpdb->get_results( $sql );
        }

        return rest_ensure_response( array(
            'success' => true,
            'rates'   => $rates,
        ) );
    }

    /**
     * Set manual exchange rate.
     *
     * @param WP_REST_Request $request Request object.
     * @return WP_REST_Response
     */
    public function rest_set_exchange_rate( $request ) {
        global $wpdb;

        $from_currency = strtoupper( sanitize_text_field( $request->get_param( 'from_currency' ) ) );
        $to_currency = strtoupper( sanitize_text_field( $request->get_param( 'to_currency' ) ) );
        $rate = floatval( $request->get_param( 'rate' ) );

        if ( ! $from_currency || ! $to_currency || $rate <= 0 ) {
            return new WP_Error( 'invalid_data', 'Invalid currency pair or rate', array( 'status' => 400 ) );
        }

        // Upsert rate.
        $existing = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT id FROM {$this->tables['exchange_rates']}
                WHERE from_currency = %s AND to_currency = %s",
                $from_currency,
                $to_currency
            )
        );

        $data = array(
            'from_currency' => $from_currency,
            'to_currency'   => $to_currency,
            'rate'          => $rate,
            'is_manual'     => 1,
            'fetched_at'    => current_time( 'mysql' ),
        );

        if ( $existing ) {
            $wpdb->update(
                $this->tables['exchange_rates'],
                $data,
                array( 'id' => $existing ),
                array( '%s', '%s', '%f', '%d', '%s' ),
                array( '%d' )
            );
        } else {
            $wpdb->insert(
                $this->tables['exchange_rates'],
                $data,
                array( '%s', '%s', '%f', '%d', '%s' )
            );
        }

        // Log history.
        $this->log_rate_history( $from_currency, $to_currency, $rate, 'manual' );

        return rest_ensure_response( array(
            'success' => true,
            'message' => 'Exchange rate set successfully',
        ) );
    }

    /**
     * Refresh exchange rates.
     *
     * @param WP_REST_Request $request Request object.
     * @return WP_REST_Response
     */
    public function rest_refresh_rates( $request ) {
        $result = $this->fetch_exchange_rates();

        if ( is_wp_error( $result ) ) {
            return $result;
        }

        return rest_ensure_response( array(
            'success'       => true,
            'rates_updated' => $result,
            'message'       => 'Exchange rates refreshed successfully',
        ) );
    }

    /**
     * Get rate history.
     *
     * @param WP_REST_Request $request Request object.
     * @return WP_REST_Response
     */
    public function rest_get_rate_history( $request ) {
        global $wpdb;

        $from_currency = $request->get_param( 'from' ) ?: $this->base_currency;
        $to_currency = $request->get_param( 'to' );
        $days = intval( $request->get_param( 'days' ) ) ?: 30;

        $sql = $wpdb->prepare(
            "SELECT * FROM {$this->tables['rate_history']}
            WHERE from_currency = %s
            AND recorded_at >= DATE_SUB(NOW(), INTERVAL %d DAY)",
            $from_currency,
            $days
        );

        if ( $to_currency ) {
            $sql = $wpdb->prepare(
                "SELECT * FROM {$this->tables['rate_history']}
                WHERE from_currency = %s AND to_currency = %s
                AND recorded_at >= DATE_SUB(NOW(), INTERVAL %d DAY)
                ORDER BY recorded_at ASC",
                $from_currency,
                $to_currency,
                $days
            );
        } else {
            $sql .= " ORDER BY to_currency, recorded_at ASC";
        }

        $history = $wpdb->get_results( $sql );

        return rest_ensure_response( array(
            'success' => true,
            'history' => $history,
        ) );
    }

    /**
     * Convert amount.
     *
     * @param WP_REST_Request $request Request object.
     * @return WP_REST_Response
     */
    public function rest_convert_amount( $request ) {
        $amount = floatval( $request->get_param( 'amount' ) );
        $from = strtoupper( sanitize_text_field( $request->get_param( 'from' ) ) );
        $to = strtoupper( sanitize_text_field( $request->get_param( 'to' ) ) );
        $log_conversion = $request->get_param( 'log_conversion' );
        $entity_type = sanitize_text_field( $request->get_param( 'entity_type' ) );
        $entity_id = intval( $request->get_param( 'entity_id' ) );

        if ( $amount <= 0 || ! $from || ! $to ) {
            return new WP_Error( 'invalid_data', 'Invalid amount or currencies', array( 'status' => 400 ) );
        }

        $result = $this->convert( $amount, $from, $to );

        if ( is_wp_error( $result ) ) {
            return $result;
        }

        // Log conversion if requested.
        if ( $log_conversion && $entity_type && $entity_id ) {
            $this->log_conversion( $entity_type, $entity_id, $from, $amount, $to, $result['converted_amount'], $result['rate'] );
        }

        return rest_ensure_response( array(
            'success'          => true,
            'original_amount'  => $amount,
            'original_currency' => $from,
            'converted_amount' => $result['converted_amount'],
            'converted_currency' => $to,
            'exchange_rate'    => $result['rate'],
            'formatted_original' => $this->format( $amount, $from ),
            'formatted_converted' => $this->format( $result['converted_amount'], $to ),
        ) );
    }

    /**
     * Get base currency.
     *
     * @param WP_REST_Request $request Request object.
     * @return WP_REST_Response
     */
    public function rest_get_base_currency( $request ) {
        global $wpdb;

        $currency = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$this->tables['currencies']} WHERE code = %s",
                $this->base_currency
            )
        );

        return rest_ensure_response( array(
            'success'       => true,
            'base_currency' => $currency,
        ) );
    }

    /**
     * Set base currency.
     *
     * @param WP_REST_Request $request Request object.
     * @return WP_REST_Response
     */
    public function rest_set_base_currency( $request ) {
        global $wpdb;

        $code = strtoupper( sanitize_text_field( $request->get_param( 'code' ) ) );

        // Validate currency exists.
        $currency = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$this->tables['currencies']} WHERE code = %s",
                $code
            )
        );

        if ( ! $currency ) {
            return new WP_Error( 'not_found', 'Currency not found', array( 'status' => 404 ) );
        }

        // Clear old base.
        $wpdb->update(
            $this->tables['currencies'],
            array( 'is_base' => 0 ),
            array( 'is_base' => 1 ),
            array( '%d' ),
            array( '%d' )
        );

        // Set new base.
        $wpdb->update(
            $this->tables['currencies'],
            array( 'is_base' => 1 ),
            array( 'id' => $currency->id ),
            array( '%d' ),
            array( '%d' )
        );

        // Update option.
        update_option( 'ict_base_currency', $code );
        $this->base_currency = $code;

        // Recalculate all exchange rates.
        $this->recalculate_rates_for_base( $code );

        return rest_ensure_response( array(
            'success' => true,
            'message' => 'Base currency updated successfully',
        ) );
    }

    /**
     * Get rate providers.
     *
     * @param WP_REST_Request $request Request object.
     * @return WP_REST_Response
     */
    public function rest_get_rate_providers( $request ) {
        $providers = array();
        $active_provider = get_option( 'ict_rate_provider', 'exchangerate-api' );

        foreach ( $this->rate_providers as $key => $provider ) {
            $providers[] = array(
                'key'       => $key,
                'name'      => $provider['name'],
                'free'      => $provider['free'],
                'is_active' => $key === $active_provider,
            );
        }

        return rest_ensure_response( array(
            'success'   => true,
            'providers' => $providers,
        ) );
    }

    /**
     * Convert amount between currencies.
     *
     * @param float  $amount Amount to convert.
     * @param string $from   Source currency code.
     * @param string $to     Target currency code.
     * @return array|WP_Error Conversion result or error.
     */
    public function convert( $amount, $from, $to ) {
        global $wpdb;

        if ( $from === $to ) {
            return array(
                'converted_amount' => $amount,
                'rate'             => 1,
            );
        }

        // Get rate.
        $rate = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT rate FROM {$this->tables['exchange_rates']}
                WHERE from_currency = %s AND to_currency = %s",
                $from,
                $to
            )
        );

        if ( ! $rate ) {
            // Try inverse.
            $inverse_rate = $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT rate FROM {$this->tables['exchange_rates']}
                    WHERE from_currency = %s AND to_currency = %s",
                    $to,
                    $from
                )
            );

            if ( $inverse_rate ) {
                $rate = 1 / $inverse_rate;
            }
        }

        if ( ! $rate ) {
            // Try cross rate through base currency.
            $from_to_base = $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT rate FROM {$this->tables['exchange_rates']}
                    WHERE from_currency = %s AND to_currency = %s",
                    $from,
                    $this->base_currency
                )
            );

            $base_to_target = $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT rate FROM {$this->tables['exchange_rates']}
                    WHERE from_currency = %s AND to_currency = %s",
                    $this->base_currency,
                    $to
                )
            );

            if ( $from_to_base && $base_to_target ) {
                $rate = $from_to_base * $base_to_target;
            }
        }

        if ( ! $rate ) {
            return new WP_Error( 'no_rate', 'Exchange rate not available for this currency pair', array( 'status' => 400 ) );
        }

        // Get rounding increment for target currency.
        $to_currency = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT decimals, rounding_increment FROM {$this->tables['currencies']} WHERE code = %s",
                $to
            )
        );

        $converted = $amount * $rate;

        // Apply rounding.
        if ( $to_currency && $to_currency->rounding_increment > 0 ) {
            $converted = round( $converted / $to_currency->rounding_increment ) * $to_currency->rounding_increment;
        }

        $decimals = $to_currency ? $to_currency->decimals : 2;
        $converted = round( $converted, $decimals );

        return array(
            'converted_amount' => $converted,
            'rate'             => $rate,
        );
    }

    /**
     * Format amount in currency.
     *
     * @param float  $amount        Amount to format.
     * @param string $currency_code Currency code.
     * @return string Formatted amount.
     */
    public function format( $amount, $currency_code = null ) {
        global $wpdb;

        if ( ! $currency_code ) {
            $currency_code = $this->base_currency;
        }

        $currency = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$this->tables['currencies']} WHERE code = %s",
                $currency_code
            )
        );

        if ( ! $currency ) {
            return number_format( $amount, 2 ) . ' ' . $currency_code;
        }

        $formatted = number_format(
            $amount,
            $currency->decimals,
            $currency->decimal_separator,
            $currency->thousand_separator
        );

        if ( $currency->symbol_position === 'before' ) {
            return $currency->symbol . $formatted;
        } else {
            return $formatted . ' ' . $currency->symbol;
        }
    }

    /**
     * Filter hook for formatting amounts.
     *
     * @param float  $amount        Amount to format.
     * @param string $currency_code Currency code.
     * @param bool   $include_code  Include currency code.
     * @return string
     */
    public function format_amount( $amount, $currency_code = null, $include_code = false ) {
        $formatted = $this->format( $amount, $currency_code );

        if ( $include_code && $currency_code ) {
            $formatted .= ' ' . $currency_code;
        }

        return $formatted;
    }

    /**
     * Filter hook for converting amounts.
     *
     * @param float  $amount      Amount to convert.
     * @param string $from        Source currency.
     * @param string $to          Target currency.
     * @param bool   $format      Whether to format result.
     * @return mixed
     */
    public function convert_amount( $amount, $from, $to, $format = false ) {
        $result = $this->convert( $amount, $from, $to );

        if ( is_wp_error( $result ) ) {
            return $amount;
        }

        if ( $format ) {
            return $this->format( $result['converted_amount'], $to );
        }

        return $result['converted_amount'];
    }

    /**
     * Fetch exchange rates from provider.
     *
     * @return int|WP_Error Number of rates updated or error.
     */
    public function fetch_exchange_rates() {
        global $wpdb;

        $provider_key = get_option( 'ict_rate_provider', 'exchangerate-api' );
        $api_key = get_option( 'ict_rate_api_key', '' );

        if ( ! isset( $this->rate_providers[ $provider_key ] ) ) {
            return new WP_Error( 'invalid_provider', 'Invalid rate provider' );
        }

        $provider = $this->rate_providers[ $provider_key ];

        // Build URL.
        $url = str_replace(
            array( '{api_key}', '{base}' ),
            array( $api_key, $this->base_currency ),
            $provider['url']
        );

        // Fetch rates.
        $response = wp_remote_get( $url, array( 'timeout' => 30 ) );

        if ( is_wp_error( $response ) ) {
            return $response;
        }

        $body = json_decode( wp_remote_retrieve_body( $response ), true );

        if ( ! $body ) {
            return new WP_Error( 'invalid_response', 'Invalid API response' );
        }

        // Parse rates based on provider.
        $rates = array();

        switch ( $provider_key ) {
            case 'exchangerate-api':
                if ( isset( $body['conversion_rates'] ) ) {
                    $rates = $body['conversion_rates'];
                }
                break;

            case 'openexchangerates':
            case 'fixer':
                if ( isset( $body['rates'] ) ) {
                    $rates = $body['rates'];
                }
                break;

            case 'currencylayer':
                if ( isset( $body['quotes'] ) ) {
                    foreach ( $body['quotes'] as $pair => $rate ) {
                        $to_currency = substr( $pair, 3 );
                        $rates[ $to_currency ] = $rate;
                    }
                }
                break;
        }

        if ( empty( $rates ) ) {
            return new WP_Error( 'no_rates', 'No rates returned from provider' );
        }

        // Get active currencies.
        $active_currencies = $wpdb->get_col(
            "SELECT code FROM {$this->tables['currencies']} WHERE is_active = 1"
        );

        $updated = 0;
        $now = current_time( 'mysql' );
        $expires = date( 'Y-m-d H:i:s', strtotime( '+1 hour' ) );

        foreach ( $rates as $currency_code => $rate ) {
            if ( ! in_array( $currency_code, $active_currencies ) ) {
                continue;
            }

            if ( $currency_code === $this->base_currency ) {
                continue;
            }

            // Upsert rate.
            $existing = $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT id FROM {$this->tables['exchange_rates']}
                    WHERE from_currency = %s AND to_currency = %s AND is_manual = 0",
                    $this->base_currency,
                    $currency_code
                )
            );

            $data = array(
                'from_currency' => $this->base_currency,
                'to_currency'   => $currency_code,
                'rate'          => $rate,
                'provider'      => $provider_key,
                'fetched_at'    => $now,
                'expires_at'    => $expires,
                'is_manual'     => 0,
            );

            if ( $existing ) {
                $wpdb->update(
                    $this->tables['exchange_rates'],
                    $data,
                    array( 'id' => $existing ),
                    array( '%s', '%s', '%f', '%s', '%s', '%s', '%d' ),
                    array( '%d' )
                );
            } else {
                $wpdb->insert(
                    $this->tables['exchange_rates'],
                    $data,
                    array( '%s', '%s', '%f', '%s', '%s', '%s', '%d' )
                );
            }

            // Log history.
            $this->log_rate_history( $this->base_currency, $currency_code, $rate, $provider_key );

            $updated++;
        }

        return $updated;
    }

    /**
     * Scheduled rate update handler.
     */
    public function scheduled_rate_update() {
        $this->fetch_exchange_rates();
    }

    /**
     * Log rate history.
     *
     * @param string $from_currency From currency.
     * @param string $to_currency   To currency.
     * @param float  $rate          Exchange rate.
     * @param string $provider      Rate provider.
     */
    private function log_rate_history( $from_currency, $to_currency, $rate, $provider ) {
        global $wpdb;

        $wpdb->insert(
            $this->tables['rate_history'],
            array(
                'from_currency' => $from_currency,
                'to_currency'   => $to_currency,
                'rate'          => $rate,
                'provider'      => $provider,
                'recorded_at'   => current_time( 'mysql' ),
            ),
            array( '%s', '%s', '%f', '%s', '%s' )
        );
    }

    /**
     * Log conversion.
     *
     * @param string $entity_type      Entity type.
     * @param int    $entity_id        Entity ID.
     * @param string $original_currency Original currency.
     * @param float  $original_amount   Original amount.
     * @param string $converted_currency Converted currency.
     * @param float  $converted_amount  Converted amount.
     * @param float  $rate             Exchange rate.
     */
    private function log_conversion( $entity_type, $entity_id, $original_currency, $original_amount, $converted_currency, $converted_amount, $rate ) {
        global $wpdb;

        $wpdb->insert(
            $this->tables['conversions'],
            array(
                'entity_type'        => $entity_type,
                'entity_id'          => $entity_id,
                'original_currency'  => $original_currency,
                'original_amount'    => $original_amount,
                'converted_currency' => $converted_currency,
                'converted_amount'   => $converted_amount,
                'exchange_rate'      => $rate,
                'rate_date'          => current_time( 'mysql' ),
                'user_id'            => get_current_user_id(),
            ),
            array( '%s', '%d', '%s', '%f', '%s', '%f', '%f', '%s', '%d' )
        );
    }

    /**
     * Recalculate rates when base currency changes.
     *
     * @param string $new_base New base currency code.
     */
    private function recalculate_rates_for_base( $new_base ) {
        global $wpdb;

        // Get all current rates with old base.
        $old_rates = $wpdb->get_results(
            "SELECT * FROM {$this->tables['exchange_rates']} WHERE is_manual = 0"
        );

        if ( empty( $old_rates ) ) {
            return;
        }

        // Find rate from old base to new base.
        $old_base = null;
        $conversion_rate = null;

        foreach ( $old_rates as $rate ) {
            if ( $rate->to_currency === $new_base ) {
                $old_base = $rate->from_currency;
                $conversion_rate = floatval( $rate->rate );
                break;
            }
        }

        if ( ! $conversion_rate ) {
            return;
        }

        // Recalculate all rates.
        $now = current_time( 'mysql' );

        foreach ( $old_rates as $rate ) {
            if ( $rate->from_currency === $new_base ) {
                continue;
            }

            $old_rate = floatval( $rate->rate );
            $new_rate = $old_rate / $conversion_rate;

            $wpdb->update(
                $this->tables['exchange_rates'],
                array(
                    'from_currency' => $new_base,
                    'rate'          => $new_rate,
                    'fetched_at'    => $now,
                ),
                array( 'id' => $rate->id ),
                array( '%s', '%f', '%s' ),
                array( '%d' )
            );
        }

        // Remove the old base currency rate.
        $wpdb->delete(
            $this->tables['exchange_rates'],
            array( 'to_currency' => $new_base ),
            array( '%s' )
        );
    }

    /**
     * Get currency by code.
     *
     * @param string $code Currency code.
     * @return object|null Currency object or null.
     */
    public function get_currency( $code ) {
        global $wpdb;

        return $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$this->tables['currencies']} WHERE code = %s",
                $code
            )
        );
    }

    /**
     * Get active currencies.
     *
     * @return array List of active currencies.
     */
    public function get_active_currencies() {
        global $wpdb;

        return $wpdb->get_results(
            "SELECT * FROM {$this->tables['currencies']}
            WHERE is_active = 1
            ORDER BY sort_order ASC, code ASC"
        );
    }

    /**
     * Get exchange rate.
     *
     * @param string $from From currency code.
     * @param string $to   To currency code.
     * @return float|null Exchange rate or null.
     */
    public function get_rate( $from, $to ) {
        global $wpdb;

        return $wpdb->get_var(
            $wpdb->prepare(
                "SELECT rate FROM {$this->tables['exchange_rates']}
                WHERE from_currency = %s AND to_currency = %s",
                $from,
                $to
            )
        );
    }
}
