<?php
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

/**
 * WooCommerce Compatibility Utility Class
 *
 * The unfortunate purpose of this class is to provide a single point of
 * compatibility functions for dealing with supporting multiple versions
 * of WooCommerce.
 *
 * Current Compatibility: 2.0.x - 2.1
 **/

class WC_PayPalAdv_Plugin_Compatibility {

    private static $version_2_1;

    /**
     * function to set the WC version
     *
     * @param string $message The text to display in the notice.
     * @param string $notice_type The singular name of the notice type - either error, success or notice. [optional]
     */

    public static function check_version() {

        // WOOCOMMERCE_VERSION is now WC_VERSION, though WOOCOMMERCE_VERSION is still available for backwards compatibility, we'll disregard it on 2.1+
        if ( defined( 'WC_VERSION' ) && WC_VERSION )
            $version = WC_VERSION;
        if ( defined( 'WOOCOMMERCE_VERSION' ) && WOOCOMMERCE_VERSION )
            $version = WOOCOMMERCE_VERSION;

        self::$version_2_1 = version_compare( $version, '2.0.20', '>' );
    }

    /**
     * function to get the configuration, used in admin section
     *
     * @param string $gateway_cls_name Gateway Class Name
     * @return string configuration URL
     */

    public static function get_configuration_url($gateway_cls_name) {

        if ( self::$version_2_1 ) {
            return admin_url( 'admin.php?page=wc-settings&tab=checkout&section=' . strtolower( $gateway_cls_name ) );
        } else {
            return admin_url( 'admin.php?page=woocommerce_settings&tab=payment_gateways&section=' . $gateway_cls_name );
        }
    }

    /**
     * Compatibility function to get order total
     *
     * @param WC_Order $order
     * @return string order total
     */

    public static function get_order_total(&$order) {

        if ( self::$version_2_1 ) {
            return $order->get_total();
        } else {
            return $order->get_order_total();
        }
    }

    /**
     * Compatibility function to get shipping total
     *
     * @param WC_Order $order
     * @return string shipping total
     */

    public static function get_shipping_total(&$order) {

        if ( self::$version_2_1 ) {
            return $order->get_total_shipping();
        } else {
            return $order->get_shipping();
        }
    }



    /**
     * Compatibility function to add and store a notice
     *
     * @param string $message The text to display in the notice.
     * @param string $notice_type The singular name of the notice type - either error, success or notice. [optional]
     */

    public static function wc_add_notice( $message, $notice_type = 'success' ) {

        if ( self::$version_2_1 ) {
            wc_add_notice( $message, $notice_type );
        } else {
            global $woocommerce;

            if ( 'error' == $notice_type ) {
                $woocommerce->add_error( $message );
            } else {
                $woocommerce->add_message( $message );
            }

        }

    }

    /**
     * Prints messages and errors which are stored in the session, then clears them.
     *
     */
    public static function wc_print_notices() {

        if ( self::$version_2_1 ) {
            wc_print_notices();
        } else {
            global $woocommerce;
            $woocommerce->show_messages();
        }
    }


    /**
     * Returns a new instance of the woocommerce logger
     *
     * @return object logger
     */

    public static function new_wc_logger() {

        if ( self::$version_2_1 ) {
            return new WC_Logger();
        } else {
            global $woocommerce;
            return $woocommerce->logger();
        }
    }

    /**
     * Sets WooCommerce messages
     *
     */

    public static function set_messages() {

        if ( self::$version_2_1 ) {
            // no-op in WC 2.1+
        } else {
            global $woocommerce;
            $woocommerce->set_messages();
        }
    }

    /**
     * Returns the WooCommerce instance
     *
     * @return WooCommerce woocommerce instance
     */

    public static function WC() {

        if ( self::is_wc_version_gte_2_1() ) {
            return WC();
        } else {
            global $woocommerce;
            return $woocommerce;
        }
    }
}
?>