<?php
/**
 * Plugin Name: SagePay Server Gateway for WooCommerce
 * Plugin URI: http://www.patsatech.com/
 * Description: WooCommerce Plugin for accepting payment through SagePay Server Gateway.
 * Version: 1.0.2
 * Author: PatSaTECH
 * Author URI: http://www.patsatech.com
 * Contributors: patsatech
 * Requires at least: 3.5
 * Tested up to: 4.1
 *
 * Text Domain: patsatech-woo-sagepay-server
 * Domain Path: /lang/
 *
 * @package SagePay Server Gateway for WooCommerce
 * @author PatSaTECH
 */

add_action('plugins_loaded', 'init_woocommerce_sagepayserver', 0);

function init_woocommerce_sagepayserver() {

    if ( ! class_exists( 'WC_Payment_Gateway' ) ) { return; }
    
	load_plugin_textdomain('patsatech-woo-sagepay-server', false, dirname( plugin_basename( __FILE__ ) ) . '/lang');

	class woocommerce_sagepayserver extends WC_Payment_Gateway{
		
		public function __construct() {
			global $woocommerce;
			
	        $this->id               = 'sagepayserver';
	        $this->method_title     = __('SagePay Server', 'patsatech-woo-sagepay-server');
			$this->icon     		= apply_filters( 'woocommerce_sagepayserver_icon', '' );
	        $this->has_fields       = false;
	        $this->notify_url   	= str_replace( 'https:', 'http:', add_query_arg( 'wc-api', 'woocommerce_sagepayserver', home_url( '/' ) ) );

			$default_card_type_options = array(
												'VISA' => 'VISA',
												'MC' => 'MasterCard',
												'AMEX' => 'American Express',
												'DISC' => 'Discover',
												'DC' => 'Diner\'s Club',
												'JCB' => 'JCB Card'
												);
												
			$this->card_type_options = apply_filters( 'woocommerce_sagepayserver_card_types', $default_card_type_options );
	                
	        // load form fields
	        $this->init_form_fields();
			
	        // initialise settings
	        $this->init_settings();
	        
	        // variables            
	        $this->title            = $this->settings['title'];
	        $this->description      = $this->settings['description'];
	        $this->vendor_name      = $this->settings['vendorname'];
	        $this->mode             = $this->settings['mode'];        
	        $this->transtype        = $this->settings['transtype'];
	        $this->paymentpage      = $this->settings['paymentpage'];
	        $this->iframe        	= $this->settings['iframe'];
	        $this->currency         = $this->settings['currency'];
			$this->cardtypes		= $this->settings['cardtypes'];
	        $this->woo_version		= $this->get_woo_version();
			
			if( $this->mode == 'test' ){
				$this->gateway_url = 'https://test.sagepay.com/gateway/service/vspserver-register.vsp';
			}else if( $this->mode == 'live' ){
				$this->gateway_url = 'https://live.sagepay.com/gateway/service/vspserver-register.vsp';
			}
	        
	        // actions
			add_action( 'init', array( $this, 'successful_request') );
			add_action( 'woocommerce_api_woocommerce_sagepayserver', array( $this, 'successful_request' ) );
			add_action( 'woocommerce_receipt_sagepayserver', array( $this, 'receipt_page' ) );
	        add_action( 'woocommerce_update_options_payment_gateways', array( $this, 'process_admin_options' ) );
			add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
			
			if ( !$this->is_valid_for_use() ) $this->enabled = false; 
		}
			
		/**
		 * get_icon function.
		 *
		 * @access public
		 * @return string
		 */
		function get_icon() {
			global $woocommerce;
			
			$icon = '';
			if ( $this->icon ) {
				// default behavior
				$icon = '<img src="' . $this->force_ssl( $this->icon ) . '" alt="' . $this->title . '" />';
			} elseif ( $this->cardtypes ) {
				// display icons for the selected card types
				$icon = '';
				foreach ( $this->cardtypes as $cardtype ) {
					if ( file_exists( plugin_dir_path( __FILE__ ) . '/images/card-' . strtolower( $cardtype ) . '.png' ) ) {
						$icon .= '<img src="' . $this->force_ssl( plugins_url( '/images/card-' . strtolower( $cardtype ) . '.png', __FILE__ ) ) . '" alt="' . strtolower( $cardtype ) . '" />';
					}
				}
			}
			
			return apply_filters( 'woocommerce_gateway_icon', $icon, $this->id );
		}

	     /**
	     * Check if this gateway is enabled and available in the user's country
	     */
	    function is_valid_for_use() {
	        if (!in_array(get_option('woocommerce_currency'), array('USD','AUD','CAD','CHF','DKK','EUR','GBP','HKD','IDR','JPY','LUF','NOK','NZD','SEK','SGD','TRL'))) return false;

	        return true;
	    }
	        
	    /**
	    * Admin Panel Options 
	    **/       
	    public function admin_options()
	    {
			?>
	        <h3><?php _e('SagePay Server', 'patsatech-woo-sagepay-server'); ?></h3>
	        <p><?php _e('SagePay Server works by processing Credit Cards on site. So users do not leave your site to enter their payment information.', 'patsatech-woo-sagepay-server'); ?></p>
			<table class="form-table">
	        <?php           
	    		if ( $this->is_valid_for_use() ) :

	    			// Generate the HTML For the settings form.
	    			$this->generate_settings_html();

	    		else :

	    			?>
	            		<div class="inline error"><p><strong><?php _e( 'Gateway Disabled', 'woothemes' ); ?></strong>: <?php _e( 'SagePay Server does not support your store currency.', 'woothemes' ); ?></p></div>
	        		<?php

	    		endif;
	        ?>
	        </table><!--/.form-table-->
	        <?php
		}

	    /**
	    * Initialise Gateway Settings Form Fields
	    */
	    public function init_form_fields() {
	            
			//  array to generate admin form
	        $this->form_fields = array(
	        	'enabled' => array(
	            				'title' => __( 'Enable/Disable', 'patsatech-woo-sagepay-server' ), 
			                    'type' => 'checkbox', 
			                    'label' => __( 'Enable SagePay Server', 'patsatech-woo-sagepay-server' ), 
			                    'default' => 'yes'
							),
				'title' => array(
			                    'title' => __( 'Title', 'patsatech-woo-sagepay-server' ), 
			                    'type' => 'text', 
			                    'description' => __( 'This is the title displayed to the user during checkout.', 'patsatech-woo-sagepay-server' ), 
			                    'default' => __( 'SagePay Server', 'patsatech-woo-sagepay-server' )
			                ),
	            'description' => array(
	            				'title' => __( 'Description', 'patsatech-woo-sagepay-server' ), 
			                    'type' => 'textarea', 
			                    'description' => __( 'This is the description which the user sees during checkout.', 'patsatech-woo-sagepay-server' ), 
			                    'default' => __("Payment via SagePay, Please enter your credit or debit card below.", 'patsatech-woo-sagepay-server')
							),
	            'vendorname' => array(
			                    'title' => __( 'Vendor Name', 'patsatech-woo-sagepay-server' ), 
			                    'type' => 'text', 
			                    'description' => __( 'Please enter your vendor name provided by SagePay.', 'patsatech-woo-sagepay-server' ), 
			                    'default' => ''
			                ),
				'mode' => array(
								'title' => __('Mode Type', 'patsatech-woo-sagepay-server'),
			                    'type' => 'select',
			                    'options' => array( 
													'test' => 'Test',
													'live' => 'Live'
													),
			                    'default' => 'test',
								'description' => __( 'Select Test or Live modes.', 'patsatech-woo-sagepay-server' )
							),
				'paymentpage' => array(
								'title' => __('Payment Page Type', 'patsatech-woo-sagepay-server'),
			                    'type' => 'select',
			                    'options' => array( 
													'LOW' => 'LOW',
													'NORMAL' => 'NORMAL'
													),
			                    'default' => 'low',
								'description' => __( 'This is used to indicate what type of payment page should be displayed. <br>LOW returns simpler payment pages which have only one step and minimal formatting. Designed to run in i-Frames. <br>NORMAL returns the normal card selection screen. We suggest you disable i-Frame if you select NORMAL.', 'patsatech-woo-sagepay-server' )
							),
	        	'iframe' => array(
	            				'title' => __( 'Enable/Disable', 'patsatech-woo-sagepay-server' ), 
			                    'type' => 'checkbox', 
			                    'label' => __( 'Enable i-Frame Mode', 'patsatech-woo-sagepay-server' ), 
			                    'default' => 'yes',
								'description' => __( 'Make sure your site is SSL Protected before using this feature.', 'patsatech-woo-sagepay-server' )
							),
				'transtype'	=> array(
								'title' => __('Transaction Type', 'patsatech-woo-sagepay-server'),
			                    'type' => 'select',
			                    'options' => array(
													'PAYMENT' => __('Payment', 'patsatech-woo-sagepay-server'), 
													'DEFFERRED' => __('Deferred', 'patsatech-woo-sagepay-server'), 
													'AUTHENTICATE' => __('Authenticate', 'patsatech-woo-sagepay-server')
													),
								'description' => __( 'Select Payment, Deferred or Authenticated.', 'patsatech-woo-sagepay-server' )
							),
				'cardtypes'	=> array(
								'title' => __( 'Accepted Cards', 'woothemes' ),
								'type' => 'multiselect', 
								'description' => __( 'Select which card types to accept.', 'woothemes' ), 
								'default' => 'VISA',
								'options' => $this->card_type_options,
							)
				);
	            
		}
	    
		/**
		 * Generate the sagepayserver button link
		 **/
	    public function generate_sagepayserver_form( $order_id ) {
			global $woocommerce;
			
	        $order = new WC_Order( $order_id );
			
			if($this->iframe == 'yes'){
			
				return '<iframe src="'. esc_url( get_transient('sagepay_server_next_url') ) .'" name="sagepayserver_payment_form" width="100%" height="900px" ></iframe>';
				
			}else{
				
				wc_enqueue_js('
					jQuery("body").block({
							message: "<img src=\"'.esc_url( $woocommerce->plugin_url() ).'/assets/images/ajax-loader.gif\" alt=\"Redirecting...\" style=\"float:left; margin-right: 10px;\" />'.__('Thank you for your order. We are now redirecting you to verify your card.', 'woothemes').'",
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
						        cursor:         "wait",
						        lineHeight:		"32px"
						    }
						});
					jQuery("#submit_sagepayserver_payment_form").click();
				');
				
				return '<form action="'.esc_url( get_transient('sagepay_server_next_url') ).'" method="post" id="sagepayserver_payment_form">
						<input type="submit" class="button alt" id="submit_sagepayserver_payment_form" value="'.__('Submit', 'woothemes').'" /> <a class="button cancel" href="'.esc_url( $order->get_cancel_order_url() ).'">'.__('Cancel order &amp; restore cart', 'woothemes').'</a>
					</form>';
					
			}
			
		}
	    
		/**
		* 
	    * process payment
	    * 
	    */
	    function process_payment( $order_id ) {
			global $woocommerce;
			
	        $order = new WC_Order( $order_id );
	        
	        $time_stamp = date("ymdHis");
	        $orderid = $this->vendor_name . "-" . $time_stamp . "-" . $order_id;
			
	        $sd_arg['ReferrerID'] 			= 'CC923B06-40D5-4713-85C1-700D690550BF';
	        $sd_arg['Amount'] 				= $order->order_total;
	        $sd_arg['CustomerEMail'] 		= $order->billing_email;
	        $sd_arg['BillingSurname'] 		= $order->billing_last_name;
	        $sd_arg['BillingFirstnames'] 	= $order->billing_first_name;
	        $sd_arg['BillingAddress1'] 		= $order->billing_address_1;
	        $sd_arg['BillingAddress2'] 		= $order->billing_address_2;
	        $sd_arg['BillingCity'] 			= $order->billing_city;
			if( $order->billing_country == 'US' ){
	        	$sd_arg['BillingState'] 		= $order->billing_state;
			}else{
	        	$sd_arg['BillingState'] 		= '';
			}
	        $sd_arg['BillingPostCode'] 		= $order->billing_postcode;
	        $sd_arg['BillingCountry'] 		= $order->billing_country;
	        $sd_arg['BillingPhone'] 		= $order->billing_phone;
			
			if( $this->cart_has_virtual_product() == true ){
				
		        $sd_arg['DeliverySurname'] 		= $order->billing_last_name;
		        $sd_arg['DeliveryFirstnames'] 	= $order->billing_first_name;
		        $sd_arg['DeliveryAddress1'] 	= $order->billing_address_1;
		        $sd_arg['DeliveryAddress2'] 	= $order->billing_address_2;
		        $sd_arg['DeliveryCity'] 		= $order->billing_city;
				if( $order->billing_country == 'US' ){
		        	$sd_arg['DeliveryState'] 		= $order->billing_state;
				}else{
		        	$sd_arg['DeliveryState'] 		= '';
				}
		        $sd_arg['DeliveryPostCode'] 	= $order->billing_postcode;
		        $sd_arg['DeliveryCountry'] 		= $order->billing_country;	
			
			}else{
				
		        $sd_arg['DeliverySurname'] 		= $order->shipping_last_name;
		        $sd_arg['DeliveryFirstnames'] 	= $order->shipping_first_name;
		        $sd_arg['DeliveryAddress1'] 	= $order->shipping_address_1;
		        $sd_arg['DeliveryAddress2'] 	= $order->shipping_address_2;
		        $sd_arg['DeliveryCity'] 		= $order->shipping_city;
				if( $order->shipping_country == 'US' ){
		        	$sd_arg['DeliveryState'] 		= $order->shipping_state;
				}else{
		        	$sd_arg['DeliveryState'] 		= '';
				}
		        $sd_arg['DeliveryPostCode'] 	= $order->shipping_postcode;
		        $sd_arg['DeliveryCountry'] 		= $order->shipping_country;	
			}
			
	        $sd_arg['Description'] 			= sprintf(__('Order #%s' , 'woothemes'), $order->id);
	        $sd_arg['Currency'] 			= get_option('woocommerce_currency');
	        $sd_arg['VPSProtocol'] 			= 3.00;
	        $sd_arg['Vendor'] 				= $this->vendor_name;
	        $sd_arg['TxType'] 				= $this->transtype;
	        $sd_arg['VendorTxCode'] 		= $orderid;    
	        $sd_arg['Profile'] 				= $this->paymentpage;
	        $sd_arg['NotificationURL'] 		= $this->notify_url;    
			
	        $post_values = "";
	        foreach( $sd_arg as $key => $value ) {
	            $post_values .= "$key=" . urlencode( $value ) . "&";
	        }
	        $post_values = rtrim( $post_values, "& " );
	        
	        $response = wp_remote_post($this->gateway_url, array( 
															'body' => $post_values,
															'method' => 'POST',
	                										'headers' => array( 'Content-Type' => 'application/x-www-form-urlencoded' ),
															'sslverify' => FALSE
															));
			
			if (!is_wp_error($response) && $response['response']['code'] >= 200 && $response['response']['code'] < 300 ) { 
				
		        $resp = array();   
		        
		        $lines = preg_split( '/\r\n|\r|\n/', $response['body'] );
		        foreach($lines as $line){            
	                $key_value = preg_split( '/=/', $line, 2 );
	                if(count($key_value) > 1)
	                    $resp[trim($key_value[0])] = trim($key_value[1]);
		        }
					
	        	if(isset($resp['Status'])) update_post_meta($order->id, 'Status', $resp['Status']);
			
			    if(isset($resp['StatusDetail'])) update_post_meta($order->id, 'StatusDetail', $resp['StatusDetail']);
					
			    if(isset($resp['VPSTxId'])) update_post_meta($order->id, 'VPSTxId', $resp['VPSTxId']);
					
			    if(isset($resp['CAVV'])) update_post_meta($order->id, 'CAVV', $resp['CAVV']);
					
			    if(isset($resp['SecurityKey'])) update_post_meta($order->id, 'SecurityKey', $resp['SecurityKey']);
					
			    if(isset($resp['TxAuthNo'])) update_post_meta($order->id, 'TxAuthNo', $resp['TxAuthNo']);
					
			    if(isset($resp['AVSCV2'])) update_post_meta($order->id, 'AVSCV2', $resp['AVSCV2']);
					
			    if(isset($resp['AddressResult'])) update_post_meta($order->id, 'AddressResult', $resp['AddressResult']);
					
			    if(isset($resp['PostCodeResult'])) update_post_meta($order->id, 'PostCodeResult', $resp['PostCodeResult']);
					
			    if(isset($resp['CV2Result'])) update_post_meta($order->id, 'CV2Result', $resp['CV2Result']);
					
			    if(isset($resp['3DSecureStatus'])) update_post_meta($order->id, '3DSecureStatus', $resp['3DSecureStatus']);
					
			    if(isset($orderid)) update_post_meta($order->id, 'VendorTxCode', $orderid );
		        
				if ( $resp['Status'] == "OK" ) 
			    {		
							
		            $order->add_order_note( $resp['StatusDetail'] );
					
					set_transient( 'sagepay_server_next_url', $resp['NextURL'] ); 
			        			
					if($this->woo_version >= 2.1){
						$redirect = $order->get_checkout_payment_url( true );			
					}else if( $this->woo_version < 2.1 ){
						$redirect = add_query_arg('order', $order->id, add_query_arg('key', $order->order_key, get_permalink(get_option('woocommerce_pay_page_id'))));
					}else{
						$redirect = add_query_arg('order', $order->id, add_query_arg('key', $order->order_key, get_permalink(get_option('woocommerce_pay_page_id'))));
					}
					
					return array(
						'result' 	=> 'success',
						'redirect'	=> $redirect
					);
						
				}else{
					
					if(isset($resp['StatusDetail'])){
							
						if($this->woo_version >= 2.1){
							wc_add_notice( sprintf('Transaction Failed. %s - %s', $resp['Status'], $resp['StatusDetail'] ), 'error' );
						}else if( $this->woo_version < 2.1 ){
							$woocommerce->add_error( sprintf('Transaction Failed. %s - %s', $resp['Status'], $resp['StatusDetail'] ) );
						}else{
							$woocommerce->add_error( sprintf('Transaction Failed. %s - %s', $resp['Status'], $resp['StatusDetail'] ) );
						}
						
					}
		            else{
						
						if($this->woo_version >= 2.1){
							wc_add_notice( sprintf( 'Transaction Failed with %s - unknown error.', $resp['Status'] ), 'error' );
						}else if( $this->woo_version < 2.1 ){
							$woocommerce->add_error( sprintf( 'Transaction Failed with %s - unknown error.', $resp['Status'] ) );
						}else{
							$woocommerce->add_error( sprintf( 'Transaction Failed with %s - unknown error.', $resp['Status'] ) );
						}
				
					}
				}
				
			}else{
				
				if($this->woo_version >= 2.1){
					wc_add_notice( __('Gateway Error. Please Notify the Store Owner about this error.', 'patsatech-woo-sagepay-server'), 'error' );
				}else if( $this->woo_version < 2.1 ){
					$woocommerce->add_error( __('Gateway Error. Please Notify the Store Owner about this error.', 'patsatech-woo-sagepay-server') );
				}else{
					$woocommerce->add_error( __('Gateway Error. Please Notify the Store Owner about this error.', 'patsatech-woo-sagepay-server') );
				}
				
			}
		}
		
		/**
		 * receipt_page
		 **/
		function receipt_page( $order ) {
			global $woocommerce;
			
			echo '<p>'.__('Thank you for your order.', 'woothemes').'</p>';
			
			echo $this->generate_sagepayserver_form( $order );
			
		}
	        
			
		/**
		 * Successful Payment!
		 **/
		function successful_request() {
			global $woocommerce;
	            
		    $eoln = chr(13) . chr(10);
				
	        $params = array();
	            
	        $params['Status'] = 'INVALID';
	            
	        if( isset( $_POST['VendorTxCode'] ) ){
				
				$vendor_tx_code = explode('-',$_POST['VendorTxCode']);
					
				$order = new WC_Order( $vendor_tx_code[2] );
					
				if( $_POST['Status'] == 'OK' ){
					$params = array('Status' => 'OK', 'StatusDetail' => __('Transaction acknowledged.', 'patsatech-woo-sagepay-server') );
					$redirect_url = $this->get_return_url( $order );      
					$order->add_order_note( __('Sagepay Direct payment completed', 'patsatech-woo-sagepay-server') . ' ( ' . __('Transaction ID: ','patsatech-woo-sagepay-server') . $_POST['VendorTxCode'] . ' )' );			
					$order->payment_complete();
				}elseif( $_POST['Status'] == 'ABORT' ){
					$params = array('Status' => 'INVALID', 'StatusDetail' => __('Transaction aborted - ', 'patsatech-woo-sagepay-server') . $_POST['StatusDetail'] );
					
					if($this->woo_version >= 2.1){
						wc_add_notice( __('Aborted by user.', 'patsatech-woo-sagepay-server'), 'error' );
					}else if( $this->woo_version < 2.1 ){
						$woocommerce->add_error( __('Aborted by user.', 'patsatech-woo-sagepay-server') );
					}else{
						$woocommerce->add_error( __('Aborted by user.', 'patsatech-woo-sagepay-server') );
					}
					
					$redirect_url = get_permalink( woocommerce_get_page_id( 'checkout' ) );
				}elseif( $_POST['Status'] == 'ERROR' ){
					$params = array('Status' => 'INVALID', 'StatusDetail' => __('Transaction errored - ', 'patsatech-woo-sagepay-server') . $_POST['StatusDetail'] );
	            	$redirect_url = $order->get_cancel_order_url();
				}else{
					$params = array('Status' => 'INVALID', 'StatusDetail' => __('Transaction failed - ', 'patsatech-woo-sagepay-server') . $_POST['StatusDetail'] );
	            	$redirect_url = $order->get_cancel_order_url();
				}
			}else{
	        	$params['StatusDetail'] =  __('SagePay Server, No VendorTxCode posted.', 'patsatech-woo-sagepay-server');        
			}
	        
	        $params['RedirectURL'] =  $this->force_ssl( $redirect_url );
	            
	        if($this->iframe == 'yes'){
	        	$params['RedirectURL'] =  add_query_arg( 'page', urlencode( $redirect_url ), 
	            	$this->force_ssl( WP_PLUGIN_URL ."/" . plugin_basename( dirname(__FILE__) ) . '/includes/pages/redirect.php' ) 
				);
			}else{
				$params['RedirectURL'] =  $this->force_ssl( $redirect_url );
			}
				
	        $param_string = "";
	        foreach( $params as $key => $value ) {
	        	$param_string .= $key . "=" . $value  . $eoln;
			}
	                
	        ob_clean();                              
	        echo $param_string;
	        exit();
			
		}
		
		/**
		* Check if the cart contains virtual product
		*
		* @return bool
		*/
		private function cart_has_virtual_product() {
			global $woocommerce;
			
			$has_virtual_products = false;
			
			$virtual_products = 0;
			
			$products = $woocommerce->cart->get_cart();
			
			foreach( $products as $product ) {
			
				$product_id = $product['product_id'];
				$is_virtual = get_post_meta( $product_id, '_virtual', true );
				// Update $has_virtual_product if product is virtual
				if( $is_virtual == 'yes' )
				$virtual_products += 1;
			}
			if( count($products) == $virtual_products ){
				$has_virtual_products = true;
			}
		
			return $has_virtual_products;
		 
		}
		
		function get_woo_version() {
		    
			// If get_plugins() isn't available, require it
			if ( ! function_exists( 'get_plugins' ) )
				require_once( ABSPATH . 'wp-admin/includes/plugin.php' );
			
		    // Create the plugins folder and file variables
			$plugin_folder = get_plugins( '/woocommerce' );
			$plugin_file = 'woocommerce.php';
			
			// If the plugin version number is set, return it 
			if ( isset( $plugin_folder[$plugin_file]['Version'] ) ) {
				return $plugin_folder[$plugin_file]['Version'];
		
			} else {
				// Otherwise return null
				return NULL;
			}
		}
		
		private function force_ssl($url){
			
			if ( 'yes' == get_option( 'woocommerce_force_ssl_checkout' ) ) {
				$url = str_replace( 'http:', 'https:', $url );
			}
			
			return $url;
		}
	} 

	/**
	 * Add the gateway to WooCommerce
	 **/
	function add_sagepayserver_gateway( $methods ) 
	{
	    $methods[] = 'woocommerce_sagepayserver'; 
	    return $methods;
	}
	add_filter('woocommerce_payment_gateways', 'add_sagepayserver_gateway' );

}
