<?php

/**
 * Plugin Name: WooCommerce Waave Gateway
 * Description: Receive payments using Waave.
 * Author: WooCommerce
 * Author URI: https://woocommerce.com/
 * Version: 1.0.1
 * Requires at least: 4.6
 * WC requires at least: 3.0
 * WC tested up to: 4.3
 * Text Domain: wc-gateway-waave
 * Domain Path: /i18n/languages/
 *
 */

defined( 'ABSPATH' ) or exit;

define( 'WC_WAAVE_PLUGIN_URL', untrailingslashit( plugins_url( basename( plugin_dir_path( __FILE__ ) ), basename( __FILE__ ) ) ) );
define( 'WC_WAAVE_PLUGIN_PATH', untrailingslashit( plugin_dir_path( __FILE__ ) ) );


// Make sure WooCommerce is active
if ( ! in_array( 'woocommerce/woocommerce.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ) ) ) {
    return;
}

/**
 * Initialize the gateway.
 * @since 1.0.0
 */
function wc_waave_gateway_init() {
    if ( ! class_exists( 'WC_Payment_Gateway' ) ) {
        return;
    }

    require_once( plugin_basename( 'includes/class-wc-gateway-waave.php' ) );
}
add_action( 'plugins_loaded', 'wc_waave_gateway_init', 11 );

/**
 * Add the gateway to WC Available Gateways
 * 
 * @since 1.0.0
 * @param array $gateways all available WC gateways
 * @return array $gateways all WC gateways + waave gateway
 */
function wc_waave_add_to_gateways( $gateways ) {
    $gateways[] = 'WC_Gateway_Waave';
    return $gateways;
}
add_filter( 'woocommerce_payment_gateways', 'wc_waave_add_to_gateways' );


/**
 * Adds plugin page links
 * 
 * @since 1.0.0
 * @param array $links all plugin links
 * @return array $links all plugin links + our custom links (i.e., "Settings")
 */
function wc_waave_gateway_plugin_links( $links ) {

    $plugin_links = array(
        '<a href="' . admin_url( 'admin.php?page=wc-settings&tab=checkout&section=waave_gateway' ) . '">' . __( 'Configure', 'wc-gateway-waave' ) . '</a>'
    );

    return array_merge( $plugin_links, $links );
}
add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), 'wc_waave_gateway_plugin_links' );