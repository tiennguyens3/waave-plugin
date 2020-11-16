<?php

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
class WC_Gateway_Waave extends WC_Payment_Gateway {

    const PROD_URL = 'https://staging-pg.getwaave.co/waavepay/checkout';
    const SANDBOX_URL = 'https://staging-pg.getwaave.co/waavepay/checkout';

    protected $data_to_send;

    /**
     * Constructor for the gateway.
     */
    public function __construct() {
  
        $this->id                 = 'waave_gateway';
        $this->method_title       = __( 'Waave', 'wc-gateway-waave' );
        $this->method_description = __( 'Allows waave payments.', 'wc-gateway-waave' );
      
        // Load the settings.
        $this->init_form_fields();
        $this->init_settings();
      
        // Define user set variables
        $this->url = self::PROD_URL;
        $this->access_key = $this->get_option( 'access_key' );

        // Waave values
        $this->venue_id = $this->get_option( 'venue_id' );
        $this->reference_id = 1;
        $this->currency = 'USD';

        if ( 'yes' === $this->get_option( 'testmode' ) ) {
            $this->url = self::SANDBOX_URL;
        }

        // Common fields
        $this->title        = 'Waave Payment';
        $this->description  = 'Waave payment gateway.';
      
        // Actions
        add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
        add_action( 'woocommerce_receipt_waave_gateway', array( $this, 'receipt_page' ) );
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

            'testmode' => array(
                'title'       => __( 'Waave Sandbox', 'wc-gateway-waave' ),
                'type'        => 'checkbox',
                'label'       => __( 'Waave Sandbox', 'wc-gateway-waave' ),
                'description' => __( 'Place the payment gateway in the development mode.' ),
                'default'     => 'yes'
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
     * Get_icon function.
     *
     * @since 1.0.0
     * @version 4.0.0
     * @return string
     */
    public function get_icon() {
        $icons_str = '<img src="' . WC_WAAVE_PLUGIN_URL . '/assets/images/logo.png" class="" alt="Waave" />';

        return apply_filters( 'woocommerce_gateway_icon', $icons_str, $this->id );
    }

    /**
     * Reciept page.
     *
     * Display text and a button to direct the user to Waave.
     *
     * @since 1.0.0
     */
    public function receipt_page( $order ) {
        echo '<p>' . __( 'Thank you for your order, please click the button below to pay with Waave.', 'woocommerce-gateway-waave' ) . '</p>';
        echo $this->generate_waave_form( $order );
    }

    /**
     * Generate the Waave button link.
     *
     * @since 1.0.0
     */
    public function generate_waave_form( $order_id ) {
        $order         = wc_get_order( $order_id );
        // Construct variables for post
        $this->data_to_send = array(
            // Merchant details
            'access_key'   => $this->access_key,
            'return_url'   => $this->get_return_url( $order ),
            'cancel_url'   => $order->get_cancel_order_url(),

            // Inconsitent data
            'venue_id'     => $this->venue_id,
            'currency' => 'USD',

            // Item details
            'amount'   => $order->get_total(),
            'reference_id' => $order->get_order_key()
        );

        $payfast_args_array = array();
        foreach ( $this->data_to_send as $key => $value ) {
            $payfast_args_array[] = '<input type="hidden" name="' . esc_attr( $key ) . '" value="' . esc_attr( $value ) . '" />';
        }

        return '<form action="' . esc_url( $this->url ) . '" method="get" id="waave_payment_form">
                ' . implode( '', $payfast_args_array ) . '
                <input type="submit" class="button-alt" id="submit_waave_payment_form" value="' . __( 'Pay via Waave', 'woocommerce-gateway-waave' ) . '" /> <a class="button cancel" href="' . $order->get_cancel_order_url() . '">' . __( 'Cancel order &amp; restore cart', 'woocommerce-gateway-waave' ) . '</a>
                <script type="text/javascript">
                    jQuery(function(){
                        jQuery("body").block(
                            {
                                message: "' . __( 'Thank you for your order. We are now redirecting you to Waave to make payment.', 'woocommerce-gateway-waave' ) . '",
                                overlayCSS:
                                {
                                    background: "#fff",
                                    opacity: 0.6
                                },
                                css: {
                                    padding:        20,
                                    textAlign:      "center",
                                    color:          "#555",
                                    border:         "3px solid #aaa",
                                    backgroundColor:"#fff",
                                    cursor:         "wait"
                                }
                            });
                        jQuery( "#submit_waave_payment_form" ).click();
                    });
                </script>
            </form>';
    }


    /**
     * Process the payment and return the result
     *
     * @param int $order_id
     * @return array
     */
    public function process_payment( $order_id ) {

        $order = wc_get_order( $order_id );

        // Return thankyou redirect
        return array(
            'result'    => 'success',
            'redirect'  => $order->get_checkout_payment_url( true )
        );
    }
}