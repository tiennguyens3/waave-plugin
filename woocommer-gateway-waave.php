<?php

/**
 * Plugin Name: WooCommerce Waave Gateway
 * Description: Take credit card payments on your store using Waave.
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


// Make sure WooCommerce is active
if ( ! in_array( 'woocommerce/woocommerce.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ) ) ) {
    return;
}


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


/**
 * Waave Payment Gateway
 *
 * Provides an Waave Payment Gateway;
 * We load it later to ensure WC is loaded first since we're extending it.
 *
 * @class       WC_Gateway_Waave
 * @extends     WC_Payment_Gateway
 * @version     1.0.0
 * @package     WooCommerce/Classes/Payment
 */
add_action( 'plugins_loaded', 'wc_waave_gateway_init', 11 );

function wc_waave_gateway_init() {

    class WC_Gateway_Waave extends WC_Payment_Gateway {

        /**
         * Constructor for the gateway.
         */
        public function __construct() {
      
            $this->id                 = 'waave_gateway';
            $this->icon               = apply_filters('woocommerce_waave_icon', '');
            $this->has_fields         = false;
            $this->method_title       = __( 'Waave', 'wc-gateway-waave' );
            $this->method_description = __( 'Allows waave payments.', 'wc-gateway-waave' );
          
            // Load the settings.
            $this->init_form_fields();
            $this->init_settings();
          
            // Define user set variables
            $this->title        = 'Waave Payment';
            $this->description  = 'Waave payment gateway.';
            $this->instructions = 'This is instructions.';
          
            // Actions
            add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
            add_action( 'woocommerce_thankyou_' . $this->id, array( $this, 'thankyou_page' ) );
          
            // Customer Emails
            add_action( 'woocommerce_email_before_order_table', array( $this, 'email_instructions' ), 10, 3 );
        }
    
    
        /**
         * Initialize Gateway Settings Form Fields
         */
        public function init_form_fields() {
      
            $this->form_fields = apply_filters( 'wc_waave_form_fields', array(
          
                'enabled' => array(
                    'title'   => __( 'Enable/Disable', 'wc-gateway-waave' ),
                    'type'    => 'checkbox',
                    'label'   => __( 'Enable Waave Payment', 'wc-gateway-waave' ),
                    'default' => 'yes'
                ),

                'is_sandbox' => array(
                    'title'   => __( 'Use Sandbox', 'wc-gateway-waave' ),
                    'type'    => 'checkbox',
                    'label'   => __( 'Use Sandbox', 'wc-gateway-waave' ),
                    'default' => 'yes'
                ),
                
                'access_key' => array(
                    'title'       => __( 'Access Key', 'wc-gateway-waave' ),
                    'type'        => 'text',
                    'description' => __( 'This is an access key for Waave connection.', 'wc-gateway-waave' ),
                    'desc_tip'    => true
                ),

                'venue_id' => array(
                    'title'       => __( 'Venue ID', 'wc-gateway-waave' ),
                    'type'        => 'text',
                    'description' => __( 'This is a venue id for Waave connection.', 'wc-gateway-waave' ),
                    'desc_tip'    => true
                ),

                'return_url' => array(
                    'title'       => __( 'Return URl', 'wc-gateway-waave' ),
                    'type'        => 'text',
                    'description' => __( 'This is a callback url when success.', 'wc-gateway-waave' ),
                    'desc_tip'    => true
                ),

                'cancel_url' => array(
                    'title'       => __( 'Cancel Url', 'wc-gateway-waave' ),
                    'type'        => 'text',
                    'description' => __( 'This is a callback url when user cancle or error happened.', 'wc-gateway-waave' ),
                    'desc_tip'    => true
                )
            ) );
        }
    
    
        /**
         * Output for the order received page.
         */
        public function thankyou_page() {
            if ( $this->instructions ) {
                echo wpautop( wptexturize( $this->instructions ) );
            }
        }
    
    
        /**
         * Add content to the WC emails.
         *
         * @access public
         * @param WC_Order $order
         * @param bool $sent_to_admin
         * @param bool $plain_text
         */
        public function email_instructions( $order, $sent_to_admin, $plain_text = false ) {
        
            if ( $this->instructions && ! $sent_to_admin && $this->id === $order->payment_method && $order->has_status( 'on-hold' ) ) {
                echo wpautop( wptexturize( $this->instructions ) ) . PHP_EOL;
            }
        }
    
    
        /**
         * Process the payment and return the result
         *
         * @param int $order_id
         * @return array
         */
        public function process_payment( $order_id ) {
    
            $order = wc_get_order( $order_id );
            
            // Mark as on-hold (we're awaiting the payment)
            $order->update_status( 'on-hold', __( 'Awaiting waave payment', 'wc-gateway-waave' ) );
            
            // Reduce stock levels
            $order->reduce_order_stock();
            
            // Remove cart
            WC()->cart->empty_cart();
            
            // Return thankyou redirect
            return array(
                'result'    => 'success',
                'redirect'  => $this->get_return_url( $order )
            );
        }
    
  }
}