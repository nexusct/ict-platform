<?php
/**
 * Microsoft Teams Integration (Incoming Webhook)
 *
 * @package ICT_Platform
 * @since   1.1.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class ICT_Teams_Integration {
    /**
     * Bootstrap hooks.
     */
    public static function bootstrap() {
        add_action( 'ict_low_stock_detected', array( __CLASS__, 'notify_low_stock' ), 10, 1 );
    }

    /**
     * Send a low-stock summary to Teams.
     *
     * @param array $items Low stock items.
     */
    public static function notify_low_stock( $items ) {
        $webhook = get_option( 'ict_teams_webhook_url' );
        if ( empty( $webhook ) || empty( $items ) ) {
            return;
        }
        $lines = array();
        foreach ( $items as $i ) {
            $lines[] = sprintf( '%s (SKU: %s) - Qty: %s', $i->item_name ?? 'Item', $i->sku ?? '-', $i->quantity_available ?? '-' );
        }
        $text = "Low stock items:\n" . implode( "\n", array_slice( $lines, 0, 10 ) );
        self::post_message( $webhook, 'Low Stock Alert', $text );
    }

    /**
     * Post a simple message to Teams webhook.
     */
    public static function post_message( $webhook_url, $title, $text ) {
        $payload = array(
            'text' => "**" . $title . "**\n" . $text,
        );
        $args = array(
            'headers' => array( 'Content-Type' => 'application/json' ),
            'body'    => wp_json_encode( $payload ),
            'timeout' => 10,
        );
        wp_remote_post( esc_url_raw( $webhook_url ), $args );
    }
}

