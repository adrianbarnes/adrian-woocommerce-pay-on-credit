<?php
/*
 * Plugin Name: WooCommerce Pay On Credit Gateway
 * Description: Take credit payments on your store.
 * Author: Adrian Koomson-Barnes
 * Author URI: https://www.linkedin.com/in/adrienkbarnes/
 * Version: 1.0.0
 *
 * 
 */

function nat_card_load_scripts() {
    wp_enqueue_script('national-id-js', plugin_dir_url( __FILE__ ) . 'js/upload.js', array('jquery'), '0.1.0', true);

    $data = array(
        'upload_url' => admin_url('async-upload.php'),
        'ajax_url'   => admin_url('admin-ajax.php'),
        'nonce'      => wp_create_nonce('media-form')
    );

    wp_localize_script( 'national-id-js', 'nat_card_config', $data );

}

add_action( 'woocommerce_after_checkout_form', 'nat_card_load_scripts' );



 /*
 * This action hook registers our PHP class as a WooCommerce payment gateway
 */
add_filter( 'woocommerce_payment_gateways', 'pay_on_credit_add_gateway_class' );
function pay_on_credit_add_gateway_class( $gateways ) {
	$gateways[] = 'WC_PayOnCredit_Gateway';
	return $gateways;
}


/**
 * Add some JS
 */
function wcpg_pay_on_credit_script() {
    ?>
    <script>

    jQuery(document).ready(function(){
        jQuery('body').on('click','form input[type=radio]',function(){
            console.log("updating")
          jQuery('body').trigger('update_checkout');
        });

        jQuery('body').on('change','form input[type=radio][name=payment_method]',function(){
            console.log("updating 2")
          jQuery('body').trigger('update_checkout');
        });        
    });

    </script>
  <?php
  }
  add_action( 'woocommerce_after_checkout_form', 'wcpg_pay_on_credit_script' );

/*
 * This requires uploading an ID if customer selects pay on credit
 */  

add_action( 'wp_footer', 'conditionally_show_hide_billing_custom_field' );
function conditionally_show_hide_billing_custom_field(){
    // Only on checkout page
     if ( is_checkout() && ! is_wc_endpoint_url() ) :
    ?>
    <script>
        jQuery(function($){
            var a = 'input[name="payment_method"]',
                b = a + ':checked',
                c = '#national-id'; // The checkout field <p> container selector

            // Function that shows or hide checkout fields
            function showHide( selector = '', action = 'show' ){
                if( action == 'show' )
                    $(selector).show( 200, function(){
                        $(this).children('p').addClass("validate-required");
                    });
                else
                    $(selector).hide( 200, function(){
                        $(this).children('p').removeClass("validate-required");
                    });
                $(selector).children('p').removeClass("woocommerce-validated");
                $(selector).children('p').removeClass("woocommerce-invalid woocommerce-invalid-required-field");
            }

            // Initialising: Hide if choosen payment method is "wcpg-pay-on-credit"
            if( $(b).val() !== 'wcpg-pay-on-credit' )
                showHide( c, 'hide' );
            else
                showHide( c );

            // Live event (When payment method is changed): Show or Hide based on "wcpg-pay-on-credit"
            $( 'form.checkout' ).on( 'change', a, function() {
                if( $(b).val() !== 'wcpg-pay-on-credit' )
                    showHide( c, 'hide' );
                else
                    showHide( c );
            });
        });
    </script>
    <?php
    endif;
}

/**
 * Add the field to the checkout page
 */
add_action( 'woocommerce_after_checkout_billing_form', 'upload_id_card_field' );
 
function upload_id_card_field( $checkout ) {
 
    $uploadFile   = "";

    $uploadFile   .='<div id="national-id" class="woocommerce-billing-fields__field-wrapper"><p id="upload_doc" class="form-row form-row-wide">';
    $uploadFile   .='<label for="id_card_upload">'. __('Upload National ID') . '&nbsp<abbr class="required" title="required">*</abbr></label>';
    $uploadFile .='<span class="woocommerce-input-wrapper"><input id="id_card_upload" name="id_card_file" style="min-height:auto!important; width:100%" type="file" accept="image/png,image/jpeg,application/pdf" required>';
    $uploadFile .='<span id="uploadComplete">';
    $uploadFile .='</span></span>';
    $uploadFile .='</p></div><input type="hidden" name="id_card" id="id_card_url">';
    echo $uploadFile;    
 
}  

/** National ID Uploader */
add_action('woocommerce_checkout_process', 'upload_id_card_field_save');

 
function upload_id_card_field_save() {
    // Check if the field is set, if not then show an error message.

    if ( isset($_POST['id_card']) && empty($_POST['id_card']) && $_POST['payment_method'] == 'wcpg-pay-on-credit')
        wc_add_notice( __( '<strong>Upload National ID</strong> is a required field.' ), 'error' );
    
}

add_action( 'woocommerce_checkout_update_order_meta', 'upload_id_card_field_update_order_meta' );

function upload_id_card_field_update_order_meta( $order_id ) {
    if ( ! empty( $_POST['id_card'] ) && $_POST['payment_method'] == 'wcpg-pay-on-credit') {

        update_post_meta( $order_id, '_national_id', $_POST['id_card'] );
    }

}

add_action( 'woocommerce_admin_order_data_after_billing_address', 'upload_id_card_field_display_admin_order_meta', 10, 1 );

function upload_id_card_field_display_admin_order_meta($order){

    $national_id = get_post_meta( $order->get_id(), '_national_id', true );
    echo '<p><strong>'.__('National ID').':</strong> <a target="_blank" href="'.$national_id.'">' . $national_id . '</a></p>';
}



/*
 * This action hook calculates the interest to be paid
 */
function pay_on_credit_apply_interest_fee() {

   $payment_method = WC()->session->get('chosen_payment_method');
   $payment_duration = WC()->session->get('chosen_payment_duration');
    
    

    if ( $payment_method === "wcpg-pay-on-credit" ) {
        $label = __( 'Interest Fee', 'wcpg-pay-on-credit' );
        $amount = 0;

        switch ($payment_duration) {
            case '4':
                $percentage = 0.05;
                $amount  = ( WC()->cart->cart_contents_total + WC()->cart->shipping_total ) * $percentage;
                break;

            case '6':
                $percentage = 0.1;
                $amount  = ( WC()->cart->cart_contents_total + WC()->cart->shipping_total ) * $percentage;
                break;                        
            
            default:
                # code...
                break;
        }

        WC()->cart->add_fee( $label, $amount, false, '' );
    }
        
    
} 
  add_action( 'woocommerce_cart_calculate_fees', 'pay_on_credit_apply_interest_fee' );


add_action( 'woocommerce_checkout_update_order_review', 'pay_on_credit_radio_choice_set_session' );
  
function pay_on_credit_radio_choice_set_session( $posted_data ) {
    parse_str( $posted_data, $output );
    if ( isset( $output['payment_duration'] ) ){
        WC()->session->set( 'chosen_payment_duration', $output['payment_duration'] );
    }
}

 
/*
 * The class itself, please note that it is inside plugins_loaded action hook
 */
add_action( 'plugins_loaded', 'pay_on_credit_init_gateway_class' );
function pay_on_credit_init_gateway_class() {
 
	class WC_PayOnCredit_Gateway extends WC_Payment_Gateway {
 
 		/**
 		 * Class constructor, more about it in Step 3
 		 */
 		public function __construct() {
 
            $this->id = 'wcpg-pay-on-credit'; // payment gateway plugin ID
            $this->icon = ''; // URL of the icon that will be displayed on checkout page near your gateway name
            $this->has_fields = true; // in case you need a custom credit card form
            $this->method_title = 'Pay On Credit Gateway';
            $this->method_description = 'Pay on credit gateway'; // will be displayed on the options page
            $this->domain  = 'wcpg-pay-on-credit';
         
            // gateways can support subscriptions, refunds, saved payment methods,
            // but in this tutorial we begin with simple payments
            $this->supports = array(
                'products'
            );


            // Define "payment duration" radio buttons options field
            $this->options = array(
                '4' => __( '4 Months Payment', $this->domain ),
                '6' => __( '6 Months Payment', $this->domain ),
            );            
         
            // Method with all the options fields
            $this->init_form_fields();
         
            // Load the settings.
            $this->init_settings();
            $this->title        = $this->get_option( 'title' );
            $this->description  = $this->get_option( 'description' );
            $this->instructions = $this->get_option( 'instructions' );
            $this->order_status = $this->get_option( 'order_status' );
            $this->status_text  = $this->get_option( 'status_text' );
            $this->enabled = $this->get_option( 'enabled' );
         
            // This action hook saves the settings
            add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
            add_action( 'woocommerce_checkout_create_order', array( $this, 'save_order_payment_type_meta_data' ), 10, 2 );
            add_filter( 'woocommerce_get_order_item_totals', array( $this, 'display_payment_duration_order_item_totals'), 10, 3 );
            add_action( 'woocommerce_admin_order_data_after_billing_address',  array( $this, 'display_payment_type_order_edit_pages'), 10, 1 );
            add_action( 'woocommerce_thankyou_' . $this->id, array( $this, 'thankyou_page' ) );

            // Customer Emails
            add_action( 'woocommerce_email_before_order_table', array( $this, 'email_instructions' ), 10, 3 );            
         
            // We need custom JavaScript to obtain a token
            add_action( 'wp_enqueue_scripts', array( $this, 'payment_scripts' ) );
         
 
 		}
 
		/**
 		 * Plugin options, we deal with it in Step 3 too
 		 */
          public function init_form_fields(){
 
            $this->form_fields = apply_filters( 'wc_pay_on_credit_form_fields', array(
                'enabled' => array(
                    'title'   => __( 'Enable/Disable', $this->domain ),
                    'type'    => 'checkbox',
                    'label'   => __( 'Enable Pay On Credit', $this->domain ),
                    'default' => 'yes'
                ),
                'title' => array(
                    'title'       => __( 'Title', $this->domain ),
                    'type'        => 'text',
                    'description' => __( 'This controls the title for the payment method the customer sees during checkout.', $this->domain ),
                    'default'     => __( 'Pay On Credit', $this->domain ),
                    'desc_tip'    => true,
                ),
                'description' => array(
                    'title'       => __( 'Description', $this->domain ),
                    'type'        => 'textarea',
                    'description' => __( 'Payment method description that the customer will see on your checkout.', $this->domain ),
                    'default'     => __( 'Split your cost over a period and pay', $this->domain ),
                    'desc_tip'    => true,
                ),
                'instructions' => array(
                    'title'       => __( 'Instructions', $this->domain ),
                    'type'        => 'textarea',
                    'description' => __( 'Instructions that will be added to the thank you page and emails.', $this->domain ),
                    'default'     => '', // Empty by default
                    'desc_tip'    => true,
                ),
                'order_status' => array(
                    'title'       => __( 'Order Status', $this->domain ),
                    'type'        => 'select',
                    'description' => __( 'Choose whether order status you wish after checkout.', $this->domain ),
                    'default'     => 'wc-completed',
                    'desc_tip'    => true,
                    'class'       => 'wc-enhanced-select',
                    'options'     => wc_get_order_statuses()
                ),
                'status_text' => array(
                    'title'       => __( 'Order Status Text', $this->domain ),
                    'type'        => 'text',
                    'description' => __( 'Set the text for the selected order status.', $this->domain ),
                    'default'     => __( 'Order is completed', $this->domain ),
                    'desc_tip'    => true,
                ),
            ) );
        }
 
		/**
		 * You will need it if you want your custom credit card form, Step 4 is about it
		 */
        public function payment_fields(){
            if ( $description = $this->get_description() ) {
                echo wpautop( wptexturize( $description ) );
            }

            echo '<style>#payment_duration_field label.radio { display:inline-block;margin: .1em 2em 0 .05em !important;}</style>';

            $option_keys = array_keys($this->options);

            woocommerce_form_field( 'payment_duration', array(
                'type'          => 'radio',
                'class'         => array('payment_duration form-row-wide'),
                //'label'         => __('Payment Information', $this->domain),
                'options'       => $this->options,
            ), reset( $option_keys ) );
        }
 
		/*
		 * Custom CSS and JS, in most cases required only when you decided to go with a custom credit card form
		 */
	 	public function payment_scripts() {
 
	 	}
 
		/*
 		 * Fields validation, more in Step 5
		 */
		public function validate_fields() {
 
		//...
 
        }
        
        /**
         * Save the chosen payment type as order meta data.
         *
         * @param object $order
         * @param array $data
         */
        public function save_order_payment_type_meta_data( $order, $data ) {
            if ( $data['payment_method'] === $this->id && isset($_POST['payment_duration']) )
                $order->update_meta_data('_payment_duration', esc_attr($_POST['payment_duration']) );
        }   

        /**
         * Output for the order received page.
         *
         * @param int $order_id
         */
        public function thankyou_page( $order_id ) {
            $order = wc_get_order( $order_id );

            if ( $this->instructions ) {
                echo wpautop( wptexturize( $this->instructions ) );
            }
        }

        /**
         * Display the chosen payment type on the order edit pages (backend)
         *
         * @param object $order
         */
        public function display_payment_type_order_edit_pages( $order ){
            if( $this->id === $order->get_payment_method() && $order->get_meta('_payment_duration') ) {
                $options  = $this->options;
                echo '<p><strong>'.__('Payment Duration').':</strong> ' . $options[$order->get_meta('_payment_duration')] . '</p>';
            }
        }

        /**
         * Display the chosen payment type on order totals table
         *
         * @param array    $total_rows
         * @param WC_Order $order
         * @param bool     $tax_display
         * @return array
         */
        public function display_payment_duration_order_item_totals( $total_rows, $order, $tax_display ){
            if( is_a( $order, 'WC_Order' ) && $order->get_meta('_payment_duration') ) {
                $new_rows = []; // Initializing
                $options  = $this->options;

                // Loop through order total lines
                foreach( $total_rows as $total_key => $total_values ) {
                    $new_rows[$total_key] = $total_values;
                    if( $total_key === 'payment_method' ) {
                        $new_rows['payment_type'] = [
                            'label' => __("Payment Duration", $this->domain) . ':',
                            'value' => $options[$order->get_meta('_payment_duration')],
                        ];
                    }
                }

                $total_rows = $new_rows;
            }
            return $total_rows;
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
            if ( $this->instructions && ! $sent_to_admin && $this->id === $order->get_payment_method()
            && $order->has_status( $this->order_status ) ) {
                echo wpautop( wptexturize( $this->instructions ) ) . PHP_EOL;
            }
        }        
		/*
		 * We're processing the payments here, everything about it is in Step 5
		 */
        public function process_payment( $order_id ) {
            $order = wc_get_order( $order_id );

            // Mark as on-hold (we're awaiting the payment)
            $order->update_status( $this->order_status, $this->status_text );

            // Reduce stock levels
            wc_reduce_stock_levels( $order->get_id() );

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