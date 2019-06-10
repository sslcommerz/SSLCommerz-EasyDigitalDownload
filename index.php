<?php
/*
	Plugin Name: SSLCOMMERZ for Easy Digital Download WP V5.2.*
	Plugin URI: https://developer.sslcommerz.com/
	Description: This plugin allows you to accept payments on your EDD store from customers using Visa Cards, Master cards, American Express etc. via SSLCommerz payment gateway.
	Version: 4
	Author: Prabal Mallick
	Author Email: integration@sslcommerz.com
	Copyright: © 2015-2019 SSLCommerz.
	License: GNU General Public License v3.0
	License URI: https://docs.easydigitaldownloads.com/article/942-terms-and-conditions
*/

if ( ! defined( 'ABSPATH' ) ) exit;
add_action('plugins_loaded', array(Create_ssl_ipn_page_url::get_instance(), 'setup'));

#--------------Show Label In Settings Page----------------------

function sslcommerz_edd_register_gateway($gateways) {
	$gateways['sslcommerz'] = array('admin_label' => 'SSLCommerz Payment Gateway', 'checkout_label' => __(edd_get_option( 'sslcommerz_title' ), 'sslcommerz_edd'));
	return $gateways;
}
add_filter('edd_payment_gateways', 'sslcommerz_edd_register_gateway', 1, 1 );

#----------------------END---------------------------------------


#-----------Payment GateWay Configure Page-----------------------
function sslcommerz_edd_add_settings($settings) {
	
	$sslcommerz_gateway_settings = array(
		array(
			'id' => 'sslcommerz_gateway_settings',
			'name' => '<br><br><hr><strong>' . __('Configure SSLCOMMERZ', 'sslcommerz_edd') . '</strong>',
			'desc' => __('Configure the gateway settings', 'sslcommerz_edd'),
			'type' => 'header'
		),
		array(
			'id' => 'sslcommerz_title',
			'name' => __('Checkout Title', 'sslcommerz_edd'),
			'desc' => __('This title will show in your Checkout page.', 'sslcommerz_edd'),
			'type' => 'text',
			'size' => 'regular'
		),
		array(
			'id' => 'store_id',
			'name' => __('Store/API ID', 'sslcommerz_edd'),
			'desc' => __('Enter your Store Id provided from SSLCommerz.', 'sslcommerz_edd'),
			'type' => 'text',
			'size' => 'regular'
		),
		array(
			'id' => 'store_password',
			'name' => __('Store/API Password', 'sslcommerz_edd'),
			'desc' => __('Enter your Store Password provided from SSLCommerz.', 'sslcommerz_edd'),
			'type' => 'text',
			'size' => 'regular'
		),
		array(
			'id' => 'sslcommerz_ipn',
			'name' => __('IPN URL ', 'sslcommerz_edd'),
			'type' => 'descriptive_text',
			'desc' => sprintf('<b><i>'.__( get_site_url(null, null, null) . '/index.php?sslcommerzeddipn', 'sslcommerz_edd' ).'</i></b>')
		)
	);
	
	return array_merge($settings, $sslcommerz_gateway_settings);	

}
add_filter('edd_settings_gateways', 'sslcommerz_edd_add_settings', 1, 1);

#------------------------------------END---------------------------------------

#-------------------- Show setting link in plugin option -----------------------------------

function plugin_page_settings_link($links)
    {
        $links[] = '<a href="' .
        admin_url( 'edit.php?post_type=download&page=edd-settings&tab=gateways') .
        '">' . __('Settings') . '</a>';
        return $links;
    }
add_filter('plugin_action_links_' . plugin_basename(__FILE__), 'plugin_page_settings_link');


#------------------------------------End-----------------------------------------


#---------------------Processing data and requesting SSLCommerz Payment Gateway page-----------------------
function sslcommerz_process_payment($purchase_data) {
	global $edd_options;
 
	/**********************************
	* set transaction mode
	**********************************/
	if(edd_is_test_mode()) 
	{
		$request_api = "https://sandbox.sslcommerz.com/gwprocess/v3/api.php";
	} 
	else 
	{
		$request_api = "https://securepay.sslcommerz.com/gwprocess/v3/api.php";
	}

	$payment_data = array(
		'price'         => $purchase_data['price'],
		'date'          => $purchase_data['date'],
		'user_email'    => $purchase_data['user_email'],
		'purchase_key'  => $purchase_data['purchase_key'],
		'currency'      => edd_get_currency(),
		'downloads'     => $purchase_data['downloads'],
		'user_info'     => $purchase_data['user_info'],
		'cart_details'  => $purchase_data['cart_details'],
		'gateway'       => 'sslcommerz',
		'status'        => 'pending'
	);

	$payment = edd_insert_payment( $payment_data );

	if ( ! $payment ) 
	{
		// Record the error
		edd_record_gateway_error( __( 'Payment Error', 'easy-digital-downloads' ), sprintf( __( 'Payment creation failed before sending buyer to SSLCommerz. Payment data: %s', 'easy-digital-downloads' ), json_encode( $payment_data ) ), $payment );
		// Problems? send back
		edd_send_back_to_checkout( '?payment-mode=' . $purchase_data['post_data']['edd-gateway'] );
	} 
	else 
	{
		$store_id = edd_get_option( 'store_id' );
		$store_password = edd_get_option( 'store_password' );

		// $success_url = add_query_arg( array(
		// 	'payment-confirmation' => 'sslcommerz',
		// 	'payment-id' => $payment
		// ), get_permalink( edd_get_option( 'success_page', false ) ) );

		$return_url = add_query_arg( array(
			'payment-mode' => 'sslcommerz',
		), get_permalink( edd_get_option( 'purchase_page', false ) ) );

		$sslc_post_data = array(
	        'store_id'      => $store_id,
	        'store_passwd' 	=> $store_password,
	        'total_amount'  => $purchase_data['price'],
	        'tran_id'       => $payment,
	        'success_url' 	=> $return_url,
	        'fail_url' 		=> edd_get_failed_transaction_uri( '?payment-id=' . $payment ),
	        'cancel_url' 	=> $return_url,
	        'cus_name'     	=> trim($purchase_data['user_info']['first_name'].' '.$purchase_data['user_info']['last_name']),
	        'cus_add1'  	=> trim($purchase_data['user_info']['address']['line1'], ','),
	        'cus_country'  	=> $purchase_data['user_info']['address']['country'],
	        'cus_state'   	=> $purchase_data['user_info']['address']['state'],
	        'cus_city'     	=> $purchase_data['user_info']['address']['city'],
	        'cus_postcode'  => $purchase_data['user_info']['address']['zip'],
	        'cus_phone'     => $purchase_data['post_data']['edd_phone'],
	        'cus_email'    	=> $purchase_data['post_data']['edd_email'],
	        'ship_name'    	=> $purchase_data['post_data']['edd_first'] . ' ' . $purchase_data['post_data']['edd_last'],
	        'ship_add1' 	=> trim($purchase_data['user_info']['address']['line1'], ','),
	        'ship_country' 	=> $purchase_data['user_info']['address']['country'],
	        'ship_state'   	=> $purchase_data['user_info']['address']['state'],
	        'ship_city'    	=> $purchase_data['user_info']['address']['city'],
	        'ship_postcode' => $purchase_data['user_info']['address']['zip'],
	        'currency'     	=> edd_get_currency(),
	        'value_a'		=> $purchase_data['purchase_key']
	    );

	    $handle = curl_init();
		curl_setopt($handle, CURLOPT_URL, $request_api );
		curl_setopt($handle, CURLOPT_TIMEOUT, 30);
		curl_setopt($handle, CURLOPT_CONNECTTIMEOUT, 30);
		curl_setopt($handle, CURLOPT_POST, 1 );
		curl_setopt($handle, CURLOPT_POSTFIELDS, $sslc_post_data);
		curl_setopt($handle, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($handle, CURLOPT_SSL_VERIFYPEER, FALSE); # KEEP IT FALSE IF YOU RUN FROM LOCAL PC


		$content = curl_exec($handle );

		$code = curl_getinfo($handle, CURLINFO_HTTP_CODE);

		if($code == 200 && !( curl_errno($handle))) {
			curl_close( $handle);
			$sslcommerzResponse = $content;
		} else {
			curl_close( $handle);
			echo "FAILED TO CONNECT WITH SSLCOMMERZ API";
			exit;
		}

		# PARSE THE JSON RESPONSE
		$sslcz = json_decode($sslcommerzResponse, true );

		if(isset($sslcz['GatewayPageURL']) && $sslcz['GatewayPageURL']!="" ) {
	        # THERE ARE MANY WAYS TO REDIRECT - Javascript, Meta Tag or Php Header Redirect or Other
	        # echo "<script>window.location.href = '". $sslcz['GatewayPageURL'] ."';</script>";
			echo "<meta http-equiv='refresh' content='0;url=".$sslcz['GatewayPageURL']."'>";
			# header("Location: ". $sslcz['GatewayPageURL']);
			exit;
		} 
		else {
			echo "JSON Data parsing error!";
		}
	}
}
add_action('edd_gateway_sslcommerz', 'sslcommerz_process_payment');

#-------------------------------END--------------------------------

#------------------- Add Custom Phone Field to purchase form --------------------

function user_contact_form_to_purchase()
{
	echo '<p id="edd-phone-wrap">	
		<span class="edd-description" id="edd-phone-description">We will send OTP to this number.</span>
		<label class="edd-label" for="edd-phone">
			Phone Number<span class="edd-required-indicator"> *</span></label>
		<input class="edd-input required" type="text" name="edd_phone" placeholder="Phone Number" id="edd-phone" value="" aria-describedby="edd-phone-description" required="">
		</p>';
}

add_action('edd_purchase_form_user_info', 'user_contact_form_to_purchase');

#-------------------------------END--------------------------------

#----------------- SSLCOMMERZ Validation API ----------------------

function listen_for_sslcommerz_response() {
	if ( (isset($_POST['val_id']) && $_POST['val_id'] !="") && ((isset($_POST['tran_id']) && $_POST['tran_id'] !="")) && $_SERVER['QUERY_STRING'] != 'sslcommerzeddipn') 
	{
		do_action('listen_for_sslcommerz_response');
	}
}
add_action( 'init', 'listen_for_sslcommerz_response' );

#------------------------Custom Action--------------------------

function process_sslcommerz_response_for_validation_api() 
{
	if ( (isset($_POST['val_id']) && $_POST['val_id'] !="") && ((isset($_POST['tran_id']) && $_POST['tran_id'] !="")) && (isset($_POST['bank_tran_id']) && $_POST['bank_tran_id'] !=""))
	{
		$val_id = $_POST['val_id'];
		$store_id = edd_get_option( 'store_id' );
		$store_password = edd_get_option( 'store_password' );

		if(edd_is_test_mode()) {
			$validation_api = "https://sandbox.sslcommerz.com/validator/api/merchantTransIDvalidationAPI.php?val_id=".$val_id."&store_id=".$store_id."&store_passwd=".$store_password."&v=1&format=json";
		} 
		else {
			$validation_api = "https://securepay.sslcommerz.com/validator/api/merchantTransIDvalidationAPI.php?val_id=".$val_id."&store_id=".$store_id."&store_passwd=".$store_password."&v=1&format=json";
		}

		if(sslcommerz_hash_validation($store_password, $_POST))
		{
			$handle = curl_init();
			curl_setopt($handle, CURLOPT_URL, $validation_api);
			curl_setopt($handle, CURLOPT_RETURNTRANSFER, true);
			curl_setopt($handle, CURLOPT_SSL_VERIFYHOST, false); # IF YOU RUN FROM LOCAL PC
			curl_setopt($handle, CURLOPT_SSL_VERIFYPEER, false); # IF YOU RUN FROM LOCAL PC

			$result = curl_exec($handle);

			$code = curl_getinfo($handle, CURLINFO_HTTP_CODE);

			if($code == 200 && !( curl_errno($handle)))
			{

				# TO CONVERT AS ARRAY
				# $result = json_decode($result, true);
				# $status = $result['status'];

				# TO CONVERT AS OBJECT
				$result = json_decode($result);

				# TRANSACTION INFO
				$status = $result->status;
				$tran_date = $result->tran_date;
				$tran_id = $result->tran_id;
				$val_id = $result->val_id;
				$amount = $result->amount;
				$store_amount = $result->store_amount;
				$bank_tran_id = $result->bank_tran_id;
				$card_type = $result->card_type;

				$payment = new EDD_Payment( $tran_id );
				// echo "<pre>";
				// print_r($result);
				// print_r($payment);
				// echo $payment->status;
				// exit;


				if(($status == 'VALID' || $status == 'VALIDATED') && $payment->status == 'pending' && $payment->key == $result->value_a)
				{
					// Status change to complete
					edd_update_payment_status($tran_id, 'complete');
					// Redirect to success page
					edd_send_to_success_page();
				}
				if(($status == 'VALID' || $status == 'VALIDATED') && $payment->status_nicename == 'Complete' && $payment->key == $result->value_a)
				{
				    edd_empty_cart();
					edd_send_to_success_page();
				}
				else
				{
					echo "Validation Failed: $status";
					wp_redirect( edd_get_failed_transaction_uri( '?payment-id=' . $tran_id ) );
					exit;
				}

			} 
			else
			{
				echo "FAILED TO CONNECT WITH SSLCOMMERZ VALIDATION API!";
			}
		}
		else
		{
			echo "SSLCommerz Hash Validation Failed!";
		}
	}
	else
	{
		echo "No data found for validation, from custom function!";
	}
}
add_action( 'listen_for_sslcommerz_response', 'process_sslcommerz_response_for_validation_api');

#----------------------------SSLCOMMERZ Validation API END-----------------------------


#--------------------------SSLCOMMERZ Hash Validation Function---------------------------

function sslcommerz_hash_validation($store_passwd = "", $post_data)
{
    if (isset($post_data) && isset($post_data['verify_sign']) && isset($post_data['verify_key'])) {
        # NEW ARRAY DECLARED TO TAKE VALUE OF ALL POST
        $pre_define_key = explode(',', $post_data['verify_key']);
        $new_data = array();
        if (!empty($pre_define_key)) {
            foreach ($pre_define_key as $value) {
             //   if (isset($post_data[$value])) {
                    $new_data[$value] = ($post_data[$value]);
              //  }
            }
        }
        # ADD MD5 OF STORE PASSWORD
        $new_data['store_passwd'] = md5($store_passwd);
        # SORT THE KEY AS BEFORE
        ksort($new_data);
        $hash_string = "";
        foreach ($new_data as $key => $value) {
            $hash_string .= $key . '=' . ($value) . '&';
        }
        $hash_string = rtrim($hash_string, '&');
        if (md5($hash_string) == $post_data['verify_sign']) {
            return true;
        } else {
            echo "Verification signature not matched";
            return false;
        }
    } 
    else {
        echo 'Required data missing for Hash. ex: verify_key, verify_sign';
        return false;
    }
}

#-------------------------------END--------------------------------


#-------------------------- SSLCOMMERZ IPN ---------------------------------

class Create_ssl_ipn_page_url
{

    protected static $instance = NULL;

    public function __construct()
    { }

    public static function get_instance()
    {
        NULL === self::$instance and self::$instance = new self;
        return self::$instance;
    }

    public function setup()
    {
        add_action('init', array($this, 'rewrite_rules'));
        add_filter('query_vars', array($this, 'query_vars'), 10, 1);
        add_action('parse_request', array($this, 'parse_request'), 10, 1);

        register_activation_hook(__FILE__, array($this, 'flush_rules'));
    }

    public function rewrite_rules()
    {
        add_rewrite_rule('sslcommerzeddipn/?$', 'index.php?sslcommerzeddipn', 'top');
    }

    public function flush_rules()
    {
        $this->rewrite_rules();
        flush_rewrite_rules();
    }

    public function query_vars($vars)
    {
        $vars[] = 'sslcommerzeddipn';
        return $vars;
    }

    public function parse_request($wp)
    {
        if (array_key_exists('sslcommerzeddipn', $wp->query_vars)) {
            include plugin_dir_path(__FILE__) . 'sslcommerz_ipn.php';
            exit();
        }
    }
}

?>