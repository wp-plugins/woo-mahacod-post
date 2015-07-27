<?php
/**
Plugin Name: سرویس خرید پستی ماها
Plugin URI: http://mahacod.ir/
Description: این افزونه به شما امکان اتصال فروشگاه به <strong>سرویس خرید پستی ماها</strong> را میدهد
Version: 2.0
Author: mahacod
Text Domain: http://mahacod.ir/
**/
function activate_WC_MahaCOD_plugin(){
    wp_schedule_event(time(), 'hourly', 'update_maha_orders_state');
} 
register_activation_hook(__FILE__, 'activate_WC_MahaCOD_plugin');
function deactivate_WC_MahaCOD_plugin(){
    wp_clear_scheduled_hook('update_maha_orders_state');
}
register_deactivation_hook(__FILE__, 'deactivate_WC_MahaCOD_plugin');
// Check if WooCommerce is active
if ( in_array( 'woocommerce/woocommerce.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ) ) ) {
	function mahacod_shipping_method_init() {
		if(!class_exists('nusoap_client')) { // edit @ 02 14
			include_once(plugin_dir_path(__FILE__) . 'lib/nusoap/nusoap.php');
		}
		date_default_timezone_set('Asia/Tehran');
		ini_set('default_socket_timeout', 160);
        // Define Pishtaz method
		if ( ! class_exists( 'WC_mahacod_Pishtaz_Method' ) ) { include_once(plugin_dir_path(__FILE__) . 'maha.pishtaz.php'); }
        // Define Sefareshi method
        if ( ! class_exists( 'WC_mahacod_Sefareshi_Method' ) ) { include_once(plugin_dir_path(__FILE__) . 'maha.sefareshi.php'); }
	} // end function
	add_action( 'woocommerce_shipping_init', 'mahacod_shipping_method_init' );
	function add_mahacod_shipping_method( $methods ) {
		$methods[] = 'WC_mahacod_Pishtaz_Method';
        $methods[] = 'WC_mahacod_Sefareshi_Method';
		return $methods;
	}
	add_filter( 'woocommerce_shipping_methods', 'add_mahacod_shipping_method' );

require_once(plugin_dir_path(__FILE__) ."maha_debug.php");     
/***************************************************************************************************/
class WC_MahaCOD {
    var $maha_carrier;
    var $debug_file = "";
    var $email_handle;
    private $client = null;
/***************************************************************************************************/
	public function __construct() {
		add_action('woocommerce_cart_collaterals', array( $this, 'add_new_calculator'));
		add_action( 'woocommerce_check_cart_items', array( $this, 'check_ostan_filde'), 10, 2);
		add_action( 'woocommerce_checkout_order_processed', array( $this, 'save_order'), 10, 2);
		add_action('woocommerce_before_checkout_form', array( $this, 'calc_shipping_after_login'));
		add_action('woocommerce_cart_collaterals', array( $this, 'remove_shipping_calculator'));    
		add_filter('woocommerce_checkout_fields', array( $this, 'custom_override_checkout_fields'));
		add_filter('woocommerce_available_payment_gateways', array( $this, 'get_available_payment_gateways'), 10, 1);
		add_filter('woocommerce_locate_template', array( $this, 'new_template'), 50, 3); // edit @ 02 14
		add_filter('woocommerce_cart_shipping_method_full_label', array( $this, 'remove_free_text'), 10, 2);
		add_filter('woocommerce_default_address_fields', array( $this, 'remove_country_field'), 10, 1);
		add_action('woocommerce_admin_css', array( $this, 'add_css_file'));
		add_action('admin_enqueue_scripts', array( $this, 'overriade_js_file'), 11);
		add_action( 'update_maha_orders_state', array( $this, 'update_maha_orders_state'));
		add_filter( 'woocommerce_currencies', array( $this, 'check_currency'), 20 );
		add_filter('woocommerce_currency_symbol', array( $this, 'check_currency_symbol'), 20, 2);
		if(!class_exists('WC_mahacod_Pishtaz_Method') && function_exists('mahacod_shipping_method_init') && class_exists('WC_Shipping_Method')){
			mahacod_shipping_method_init();
		}
	} 
/***************************************************************************************************/
	public function filter_onlynum($string){
		return preg_replace("/[^0-9\.]/", "", $string);
	}
/***************************************************************************************************/
	public function custom_override_checkout_fields( $fields ) {
		unset($fields['billing']['billing_company']);
		unset($fields['billing']['billing_country']);
		$fields['billing']['billing_state'] = array('label'=>'استان','placeholder'=>'','required'=> false,'class'=> array('form-row-wide'),'clear'=> true);
		$fields['billing']['billing_city'] = array('label'=>'شهر','placeholder'=>'','required'=> false,'class'=> array('form-row-wide'),'clear'=> true);
		unset($fields['order']['order_comments']);
		unset($fields['shipping']['shipping_company']);
		unset($fields['shipping']['shipping_country']);
		$fields['shipping']['shipping_state'] = array('label'=>'استان','placeholder'=>'','required'=> false,'class'=> array('form-row-wide'),'clear'=> true);
		$fields['shipping']['shipping_city'] = array('label'=>'شهر','placeholder'=>'','required'=> false,'class'=> array('form-row-wide'),'clear'=> true);
		return $fields;
	}
/***************************************************************************************************/
    public function add_new_calculator(){
        global $woocommerce;
        $have_city = true;
        if( ! $woocommerce->customer->get_shipping_city()){
            echo '<style> div.cart_totals{display:none!important;}p.selectcitynotice {display:block;}</style>';
            $have_city = false;
        }
        include('cart/ostanselect-shipping-calculator.php');
    }
/***************************************************************************************************/
    public function check_ostan_filde(){
        global $woocommerce;
        // edit @ 02 14
		$ostan = (woocommerce_clean( $_POST['ostan'] )) ? woocommerce_clean( $_POST['ostan'] ) : $woocommerce->customer->get_shipping_state() ;
		$shahrestan  = (woocommerce_clean( $_POST['shahrestan'] )) ? woocommerce_clean( $_POST['shahrestan'] ) : $woocommerce->customer->get_shipping_city() ;
		$ostan = $this->filter_onlynum($ostan);
		$shahrestan = $this->filter_onlynum($shahrestan);
        if ( $ostan && $shahrestan) {
			update_option( 'woocommerce_calc_shipping', 'yes' );
			$woocommerce->customer->set_location( 'IR', $ostan, '', $shahrestan );
			$woocommerce->customer->set_shipping_location( 'IR', $ostan, '', $shahrestan );
		}else{
			update_option( 'woocommerce_calc_shipping', 'no' );
			$woocommerce->customer->set_location( 'IR', '', '', '' );
			$woocommerce->customer->set_shipping_location( 'IR', '', '', '' );
			wc_clear_notices();
			wc_add_notice('استان و شهر را انتخاب کنید. انتخاب هر دو فیلد الزامی است.', 'success');
		}
    }
/***************************************************************************************************/
    public function get_available_payment_gateways( $_available_gateways){
        global $woocommerce;
        $shipping_method = $woocommerce->session->chosen_shipping_method;
        if(in_array( $shipping_method, array('mahacod_pishtaz' ,'mahacod_sefareshi' ))){   
            foreach ( $_available_gateways as $gateway ){
			     if ($gateway->id == 'cod') $new_available_gateways[$gateway->id] = $gateway;
			}
        	return $new_available_gateways;
        }
        return $_available_gateways;
    }
/***************************************************************************************************/
    public function new_template( $template, $template_name, $template_path){
        global $woocommerce;
		$avalabel_page = array('checkout/form-billing.php','checkout/form-shipping.php','checkout/payment.php','checkout/thankyou.php');
        if(in_array($template_name,$avalabel_page))
            return untrailingslashit( plugin_dir_path( __FILE__ ) ). '/'. $template_name;
        return $template;
    }
/***************************************************************************************************/
    public function save_order($id, $posted){
        global $woocommerce;
		echo 'in function';
        $this->email_handle =  $woocommerce->mailer();
        $order = new WC_Order($id);
        if(!is_object($order)){return;}
        $is_maha = false; 
        if ( $order->shipping_method ) {
            if( in_array($order->shipping_method, array('mahacod_pishtaz' ,'mahacod_sefareshi' )) ) {
                $is_maha = true;
                $shipping_methods = $order->shipping_method;
            }
		}else {
            $shipping_s = $order->get_shipping_methods();
			foreach ( $shipping_s as $shipping ) {
			    if( in_array($shipping['method_id'], array('mahacod_pishtaz' ,'mahacod_sefareshi' )) ) {
                    $is_maha = true;
                    $shipping_methods = $shipping['method_id'];
                    break;
                }
			}
        }
        if( !$is_maha || $order->payment_method != 'cod' ){ return; }
        $this->maha_carrier      = new WC_mahacod_Pishtaz_Method();
        $service_type             = ($shipping_methods == 'mahacod_pishtaz') ? 2 : 1;
        if($this->maha_carrier->debug){
           $this->debug_file = new WC_MahaCOD_Debug();
           $this->debug_file->sep();
         }
        $unit = ($this->maha_carrier->w_unit == 'g') ? 1 : 1000;
        $orders = '';
        foreach ( $order->get_items() as $item ) {
			if ($item['product_id']>0) {
				$_product = $order->get_product_from_item( $item );
				$productName = str_ireplace('^', '', $_product->get_title()); // edit @ 02 14
				$productName = str_ireplace(';', '', $productName);
				$orders .= get_post_meta($item['product_id'], "_sku", true).'^';
				$orders .= $productName.'^';
				$orders .= intval($_product->weight * $unit).'^';
				$price  = $order->get_item_total( $item); 
				$orders .= ((get_woocommerce_currency() == "IRT") ? (int)$price*10: (int)$price).'^';
				$orders .= (int)$item['qty'];
				$orders .= ';';
			}

		}
		$customer_ostan = $this->filter_onlynum($order->shipping_state);
		$customer_city = $this->filter_onlynum($order->shipping_city);
		
		if( $customer_city && $customer_city >0){
                
		}else{
			if($this->maha_carrier->debug){
				$this->debug_file->write('@save_order::city is not valid');
				die('city is not valid');
			}
			return false;
		}
        $address = array ();
		$address[] = $order->billing_address_1;
		$address[] = $order->billing_address_2;
        $params = array(
         'o_name'			=>  $order->billing_first_name,
         'o_family'		  =>  $order->billing_last_name,
         'o_phone'		   =>  $order->billing_phone,
         'o_email'		   =>  $order->billing_email,
         'o_postalcod'	   =>  $order->billing_postcode,
         'o_ostan'		   =>  $customer_ostan,
         'o_shahr'		   =>  $customer_city,
         'o_address'		 =>  implode(' - ',$address),
         'o_message'		 =>  $order->customer_note,
         'o_sendtype'		=>  $service_type,
         'o_product_list'	=>  trim($orders, ';'),
         'o_partner_code'	=>  0,
         'o_ip_address'	  =>  $this->getIp(),
         'o_extra_parametr'  =>  ''
         ); 
         list($res, $response) = $this->add_order( $params, $order );
        
         if ($res === false) {
			if ($this->maha_carrier->debug) {
				ob_start();
				var_dump($params);
				$text = ob_get_contents();
				ob_end_clean();
				$this->debug_file->write('@save_order::error in registering by webservice:'.$response.'::'.$text);
			}
			$order->update_status( 'pending', 'maha : '.$response );
			$this->trigger($order->id, $order, '::سفارش در سیستم ماها ثبت نشد::');

         } elseif($res === true) {         
            if ($this->maha_carrier->debug) {
				$this->debug_file->write('@save_order::everything is Ok');
				wc_clear_notices();
				$woocommerce->add_message('<p>maha:</p> <p>Everthing is Ok!</p>');
			}
            $this->trigger($order->id, $order, true);
            update_post_meta($id, '_maha_tracking_code', $response->order_code);
         } else {
            $order->update_status( 'pending', 'maha : error in webservice, Order not register!' );
            $this->trigger($order->id, $order, false);    
         }        
    }
/***************************************************************************************************/
    public function add_order( $data, $order ){
        global $woocommerce;
        if ($this->maha_carrier->debug) {
			$this->debug_file->write('@add_order::here is top of function');
        }
        $this->maha_carrier->client = new nusoap_client( $this->maha_carrier->wsdl_url, true );
        $this->maha_carrier->client->soap_defencoding = 'UTF-8';
       	$this->maha_carrier->client->decode_utf8 = true;
		$this->maha_carrier->client->setCredentials($this->maha_carrier->username ,$this->maha_carrier->password,"basic");
        $response  = $this->maha_carrier->call("insert_order", $data);
        if(is_array($response) && $response['error_code']){
			if ($this->maha_carrier->debug) {
				$this->debug_file->write('@maha_service::'.$response['error_message']);
				wc_clear_notices();
				$woocommerce->add_message('<p>خطای سرویس ماها:</p> <p>'.$response['error_code'].')'.$response['error_message'].'</p>');
			}
			mkobject($response);
			return array(false, $this->maha_carrier->handleError($response->error_code,'register'));
        }
        mkobject($response);
        if ($this->maha_carrier->debug) {
			ob_start();
			var_dump($response);
			$text = ob_get_contents();
			ob_end_clean();
			$this->debug_file->write('@add_order::everything is Ok: '.$text);
		}
	    return array(true, $response);
    }
/***************************************************************************************************/
    function trigger( $order_id, $order, $subject= false ) {
		global $woocommerce;
        if(!$subject) {
			$message = $this->email_handle->wrap_message('سفارش در سیستم ماه ثبت نشد',sprintf( 'سفارش  %s در سیستم ماها ثبت نشد، لطفا بصورت دستی اقدام به ثبت سفارش در پنل شرکت ماها نمایید.', $order->get_order_number()));
			$this->email_handle->send( get_option( 'admin_email' ), sprintf('سفارش  %s در سیستم ماها ثبت نشد', $order->get_order_number() ), $message );
        }else{
			$message = $this->email_handle->wrap_message('سفارش با موفقیت در سیستم ماها ثبت گردید',sprintf( 'سفارش  %s با موفقیت در سیستم ماها ثبت گردید.', $order->get_order_number()));
			$this->email_handle->send( get_option( 'admin_email' ), sprintf( 'سفارش %s در سیستم ماها با موفقیت ثبت گردید', $order->get_order_number() ), $message );
        }
	}
/***************************************************************************************************/
    public function calc_shipping_after_login( $checkout ) {
        global $woocommerce;
        $state 		= $woocommerce->customer->get_shipping_state() ;
		$city       = $woocommerce->customer->get_shipping_city() ;
        if( $state && $city ) {
            $woocommerce->customer->calculated_shipping( true );
        } else {
            wc_add_notice( 'پیش از وارد کردن مشخصات و آدرس، لازم است استان و شهر خود را مشخص کنید.');
            $cart_page_id 	= get_option('woocommerce_cart_page_id' );//wc_get_page_id( 'cart' );
			wp_redirect( get_permalink( $cart_page_id ) );
        }
    }
/***************************************************************************************************/
	public function getIp(){
		if (!empty($_SERVER['HTTP_CLIENT_IP'])){
			$ip = $_SERVER['HTTP_CLIENT_IP'];
		}elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])){
			$ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
		}else {
			$ip = $_SERVER['REMOTE_ADDR'];
		}
		return $ip;
	}
/***************************************************************************************************/
    public function remove_shipping_calculator(){
        if( get_option('woocommerce_enable_shipping_calc')!='no' ){
            update_option('woocommerce_enable_shipping_calc', 'no');
		}
    }
/***************************************************************************************************/
    public function remove_free_text( $full_label, $method){
        global $woocommerce;
        $shipping_city = $woocommerce->customer->city;
        if(!in_array( $method->id, array('mahacod_pishtaz' ,'mahacod_sefareshi' )))
            return $full_label;
        if(empty($shipping_city))
            return $method->label;
        return $full_label;
    }
/***************************************************************************************************/
	public function remove_country_field( $fields ){
		unset( $fields['country'] );
		return $fields;
	}
/***************************************************************************************************/
    public function add_css_file(){
        global $typenow;
        if ( $typenow == '' || $typenow == "product" || $typenow == "service" || $typenow == "agent" ) {
             wp_enqueue_style( 'woocommerce_admin_override', untrailingslashit( plugins_url( '/', __FILE__ ) ) . '/css/override.css', array('woocommerce_admin_styles') );
        }
    }
/***************************************************************************************************/
    public function overriade_js_file(){
        global $woocommerce;
        wp_deregister_script( 'jquery-tiptip' );
        wp_register_script( 'jquery-tiptip', untrailingslashit( plugins_url( '/', __FILE__ ) ) . '/js/jquery.tipTip.min.js', array( 'jquery' ), $woocommerce->version, true );
    }
/***************************************************************************************************/
    public function update_maha_orders_state(){
        global $wpdb;
        $results = $wpdb->get_results($wpdab->prepare("SELECT meta.meta_value, posts.ID FROM {$wpdb->posts} AS posts
		LEFT JOIN {$wpdb->postmeta} AS meta ON posts.ID = meta.post_id
		LEFT JOIN {$wpdb->term_relationships} AS rel ON posts.ID=rel.object_ID
		LEFT JOIN {$wpdb->term_taxonomy} AS tax USING( term_taxonomy_id )
		LEFT JOIN {$wpdb->terms} AS term USING( term_id )
		WHERE 	meta.meta_key 		= '_maha_tracking_code'
        AND     meta.meta_value     != ''
		AND 	posts.post_type 	= 'shop_order'
		AND 	posts.post_status 	= 'publish'
		AND 	tax.taxonomy		= 'shop_order_status'
		AND		term.slug			IN ('processing', 'on-hold', 'pending')
	   "));
       if ( $results ) {
            $tracks = array();
	        foreach( $results as $result ) {
	           $tracks['code'][] = $result->meta_value;
               $tracks['id'][]   = $result->ID;
		    }
	   }
       if( empty($tracks)){ return ; }

        if(!is_object($this->maha_carrier)){
            $this->maha_carrier      = new WC_mahacod_Pishtaz_Method();
		}
        $this->maha_carrier->client = new nusoap_client( $this->maha_carrier->wsdl_url, true );
        $this->maha_carrier->client->soap_defencoding = 'UTF-8';
        $this->maha_carrier->client->decode_utf8 = true;
		$this->client->setCredentials($this->maha_carrier->username ,$this->maha_carrier->password,"basic");
		
        for($i = 0; $i < 5; $i++){  
            $data = array('order_id' =>$tracks['code'][$i]); 
            $response  = $this->maha_carrier->call("get_status", $data);     
            if(is_array($response) && $response['error']){
                if ($this->maha_carrier->debug) {
					$this->debug_file->write('@update_maha_orders_state::'.$response['message']);
				}
                return;
            }          
            mkobject($response);
            if ($this->maha_carrier->debug) {
                ob_start();
                var_dump($response);
                $text = ob_get_contents();
                ob_end_clean();
			   $this->debug_file->write('@update_maha_orders_state::everything is Ok: '.$text);
            }
            $res  = explode(';', $response->GetOrderStateResult);
            $status = false;
            switch($res[1]) {
                case '2': // آماده به ارسال
                case '4': // ارسال شده
                case '5':  //توزیع شده
                       /*$status = 'processing';
                       break; */
                case '6': // وصول شده
                       $status = 'completed';
                       break; 
                case '7': // برگشتی اولیه
                case '8': //برگشتی نهایی
                       $status = 'refunded';
                       break; 
                case '3': // انصرافی
                       $status = 'cancelled';
                       break; 
            }
            if ( $status ){
                $order = new WC_Order( $tracks['id'][$i] );
	            $order->update_status( $status, 'سیستم ماها @ '.$res[0] );
            }
		}// end for       
    }
/***************************************************************************************************/
    public function check_currency( $currencies ) {
		if(empty($currencies['IRR'])){ $currencies['IRR'] = __( 'ریال', 'woocommerce' );}
		if(empty($currencies['IRT'])) {$currencies['IRT'] = __( 'تومان', 'woocommerce' );}
		return $currencies;
    }
/***************************************************************************************************/
    public function check_currency_symbol( $currency_symbol, $currency ) {
		switch( $currency ) {
			case 'IRR': $currency_symbol = 'ریال'; break;
			case 'IRT': $currency_symbol = 'تومان'; break;
		}
		return $currency_symbol;
    }
}
/***************************************************************************************************/
    $GLOBALS['MahaCOD'] = new WC_MahaCOD();
    function mkobject(&$data){
		$numeric = false;
		foreach ($data as $p => &$d) {
			if (is_array($d)){mkobject($d);}
			if (is_int($p)){$numeric = true;}
		}
		if (!$numeric){settype($data, 'object');}
	} 
}