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

    const PROD_URL = 'https://pg.getwaave.co/waavepay/checkout';
    const SANDBOX_URL = 'https://staging-pg.getwaave.co/waavepay/checkout';

    protected $logger;

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
        $this->url          = self::PROD_URL;
        $this->access_key   = $this->get_option( 'access_key' );

        if ( 'yes' === $this->get_option( 'testmode' ) ) {
            $this->url = self::SANDBOX_URL;
        }

        // Waave values
        $this->venue_id     = $this->get_option( 'venue_id' );
        $this->callback_url = add_query_arg( 'wc-api', 'WC_Gateway_Waave', home_url( '/' ) );

        // Common fields
        $this->title        = 'Waave Payment';
        $this->description  = 'Waave payment gateway.';
      
        // Actions
        add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
        add_action( 'woocommerce_receipt_waave_gateway', array( $this, 'receipt_page' ) );
        add_action( 'woocommerce_api_wc_gateway_waave', array( $this, 'check_waave_response' ) );
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

            'private_key' => array(
                'title'       => __( 'Private Key', 'wc-gateway-waave' ),
                'type'        => 'text',
                'description' => __( 'This is an private key for Waave connection.', 'wc-gateway-waave' ),
                'desc_tip'    => true
            ),

            'venue_id' => array(
                'title'       => __( 'Venue ID', 'wc-gateway-waave' ),
                'type'        => 'text',
                'description' => __( 'This is a venue id for Waave connection.', 'wc-gateway-waave' ),
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
        $order      = wc_get_order( $order_id );

        // Construct variables for post
        $data_to_send = array(
            // Waave details
            'access_key'   => $this->access_key,
            'return_url'   => $this->get_return_url( $order ),
            'cancel_url'   => $order->get_cancel_order_url(),
            'callback_url' => $this->callback_url,

            // Order details
            'amount'       => $order->get_total(),
            'reference_id' => $order->get_order_key(),
            'currency'     => $order->get_currency(),

            // Inconsitent data
            'venue_id'     => $this->venue_id
        );

        $waave_args_array = array();
        foreach ( $data_to_send as $key => $value ) {
            $waave_args_array[] = '<input type="hidden" name="' . esc_attr( $key ) . '" value="' . esc_attr( $value ) . '" />';
        }

        return '<form action="' . esc_url( $this->url ) . '" method="get" id="waave_payment_form">
                ' . implode( '', $waave_args_array ) . '
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

    /**
     * Check Waave response.
     *
     * @since 1.0.0
     */
    public function check_waave_response() {
        $json = file_get_contents('php://input');
        $data = json_decode($json, true);

        $this->handle_waave_request( stripslashes_deep( $data ) );

        // Notify Waave that information has been received
        ob_end_clean();
        die('OK');
    }

    /**
     * Handle waave request
     *
     * @since 1.0.0
     */
    public function handle_waave_request( $data ) {
        $this->log( PHP_EOL
            . '----------'
            . PHP_EOL . 'Waave call received'
            . PHP_EOL . '----------'
        );
        $this->log( 'Get waave data' );
        $this->log( 'Waave Data: ' . print_r( $data, true ) );

        $error = false;

        $valid = $this->validate_signature( $data );
        if ( ! $valid ) {
            $error = true;
        }

        if ( ! $error ) {
            $valid = $this->validate_response_data( $data );
            if ( ! $valid ) {
                $error = true;
            }
        }

        if ( $error ) {
            $this->log( 'Waave validation has error.' );
            return;
        }

        $this->log( 'Check status and update order' );

        $order_id = absint( $data['reference_id'] );
        $order    = wc_get_order( $order_id );

        $status = $data['status'];
        if ( 'completed' === $status ) {
            $this->handle_payment_completed( $order );
        } elseif ( 'pending' === $status ) {
            $this->handle_payment_pending( $order );
        } elseif ( 'cancelled' === $status ) {
            $this->handle_payment_cancelled( $order );
        }

        $this->log( 'Waave request is done.' );
    }

    /**
     * Validate signature
     * 
     * @param array $data
     */
    private function validate_signature( $data ) {
        $secretKey  = $this->get_option( 'private_key' );
        $uri        = $this->callback_url;
        $body       = json_encode($data);

        $signature        = hash( "sha256", $secretKey . $uri . $body );
        $header_signature = isset( $_SERVER['HTTP_X_API_SIGNATURE'] ) ? $_SERVER['HTTP_X_API_SIGNATURE'] : '';

        if ($signature === $header_signature) {
            $this->log( 'Signature is valid.' );
            return true;
        }

        $this->log( 'Signature is invalid.' );
        $this->log( 'Woo signature: ' . $signature );
        $this->log( 'Woo secret key: ' . $secretKey );
        $this->log( 'Woo uri: ' . $uri );
        $this->log( 'Woo body: ' . $body );
        return false;
    }


    /**
     * Validate reponse data
     *
     * @param array $data
     */
    private function validate_response_data( $data ) {
        $order_id = absint( $data['reference_id'] );
        $order    = wc_get_order( $order_id );

        if ( ! $order ) {
            $this->log( 'Order is not exist.' );
            return false;
        }

        $amount = $order->get_total();
        if ($amount != $data['amount']) {
            $this->log( 'Amount is invalid.' );
            return false;
        }

        if ( 'completed' == $order->get_status() ) {
            $this->log( 'Order has already been processed' );
            return false;
        }

        $this->log( 'Response data is valid.' );
        return true;
    }

    
    /**
     * Handle payment completed
     */
    private function handle_payment_completed( $order ) {
        $this->log( '- Completed' );
        $order->add_order_note( __( 'Waave payment completed', 'woocommerce-gateway-waave' ) );
        $order->payment_complete();
    }

    /**
     * Handle payment pending
     */
    private function handle_payment_pending( $order ) {
        $this->log( '- Pending' );
        $order->update_status( 'on-hold', __( 'This payment is pending via Waave.', 'woocommerce-gateway-waave' ) );
    }

    /**
     * Handle payment cancelled
     */
    private function handle_payment_cancelled( $order ) {
        $this->log( '- Cancelled' );
        $order->update_status( 'cancelled', __( 'This payment has cancelled via Waave.', 'woocommerce-gateway-waave' ) );
    }

    /**
     * Log system processes.
     * @since 1.0.0
     */
    private function log( $message ) {
        if ( empty( $this->logger ) ) {
            $this->logger = new WC_Logger();
        }

        $this->logger->add( 'waave', $message );
    }
}