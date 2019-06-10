<?php
	if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

	$store_id = edd_get_option( 'store_id' );
	$store_password = edd_get_option( 'store_password' );
	$order_id = $_POST['tran_id'];
	$payment = new EDD_Payment($order_id);

	if($store_id !="" && $store_password != "")
	{
		if(edd_is_test_mode()) {
			$validation_api = "https://sandbox.sslcommerz.com/validator/api/validationserverAPI.php";
		} else {
			$validation_api = "https://securepay.sslcommerz.com/validator/api/validationserverAPI.php";
		}

		if($_POST['tran_id'] != "" && $_POST['val_id'] != "")
		{
			$tran_id = $_POST['tran_id'];
			$val_id = $_POST['val_id'];

			if(sslcommerz_hash_key($store_password,$_POST))
			{
				$validation_url = ($validation_api."?val_id=".$val_id."&Store_Id=".$store_id."&Store_Passwd=".$store_password."&v=1&format=json");

				$handle = curl_init();
				curl_setopt($handle, CURLOPT_URL, $validation_url);
				curl_setopt($handle, CURLOPT_RETURNTRANSFER, true);

				$result = curl_exec($handle);
				$code = curl_getinfo($handle, CURLINFO_HTTP_CODE);

				if($code == 200 && !( curl_errno($handle))) 
				{	
					$result = json_decode($result);	
					$payment = new EDD_Payment( $tran_id );
					if($payment->total == trim($result->currency_amount))
					{
						if ($payment->currency == $result->currency_type) 
						{
							if($result->status=='VALIDATED' || $result->status=='VALID') 
							{ 
								if($payment->status == 'pending')
								{
									edd_update_payment_status($tran_id, 'complete');
								}
								else
								{
									echo "Order status Mismatched!";
									exit;
								}
							}
							else
							{
								echo "Order Not Validated!";
								exit;
							}
						}
						else
						{
							echo "Your Currency is Mismatched!";
							exit;
						}
					}
					else
					{
						echo "Your Store Amount & PG Amount Mismatched!";
						exit;
					}
				}
				else
				{
					echo "FAILED TO CONNECT WITH SSLCOMMERZ VALIDATION API!";
					exit;
				}
			}
			else
			{
				echo "SSLCommerz Hash Validation Failed From IPN!";
				exit;
			}
		}
		else
		{
			echo "No Data found for IPN! It may occure you don't set IPN URL properly or Server not responding!";
			exit;
		}
	}
	else
	{
		echo "Store ID & Password Not Set, Unable to connect IPN!";
		exit;
	}	

	function sslcommerz_hash_key($store_passwd="", $parameters=array()) 
	{
		if(isset($_POST) && isset($_POST['verify_sign']) && isset($_POST['verify_key'])) {
			# NEW ARRAY DECLARED TO TAKE VALUE OF ALL POST

			$pre_define_key = explode(',', $_POST['verify_key']);

			$new_data = array();
			if(!empty($pre_define_key )) {
				foreach($pre_define_key as $value) {
					if(isset($_POST[$value])) {
						$new_data[$value] = ($_POST[$value]);
					}
				}
			}
			# ADD MD5 OF STORE PASSWORD
			$new_data['store_passwd'] = md5($store_passwd);

			# SORT THE KEY AS BEFORE
			ksort($new_data);

			$hash_string="";
			foreach($new_data as $key=>$value) { $hash_string .= $key.'='.($value).'&'; }
			$hash_string = rtrim($hash_string,'&');

			if(md5($hash_string) == $_POST['verify_sign']) {

				return true;

			} else {
				return false;
			}
		} else return false;
	}
?>