<?php 
class WC_mahacod_Pishtaz_Method extends WC_Shipping_Method {
	public $url            = "";
	public $wsdl_url       = "http://webservice.mahacod.com/cod.wsdl";
	public $username       = "";
	public $password       = "";
	public $debug          = false;
	public $w_unit         = "";
	public $debug_file     = "";
	public $client         = null;
/******************************************************/
	public function __construct(){
	   $this->id                 = 'mahacod_pishtaz'; 
	   $this->method_title       = __( 'پست پیشتاز' ); 
	   $this->method_description = __( 'ارسال توسط پست پیشتاز ' ); // Description shown in admin
	   $this->init();
	   $this->account_data();
	}
/******************************************************/
	public function init() {
	   $this->init_form_fields(); // This is part of the settings API. Override the method to add your own settings
	   $this->init_settings(); // This is part of the settings API. Loads settings you previously init.
	   $this->enabled		= $this->get_option( 'enabled' );
	   $this->title 		= $this->get_option( 'title' );
	   $this->min_amount 	= $this->get_option( 'min_amount', 0 );
	   $this->w_unit 	    = strtolower( get_option('woocommerce_weight_unit') );
	   // Save settings in admin if you have any defined
	   add_action( 'woocommerce_update_options_shipping_' . $this->id, array( $this, 'process_admin_options' ) );
	}
/******************************************************/
	public function account_data() {
		$this->username     = $this->get_option( 'username', '' );
		$this->password     = $this->get_option( 'password', '' );
	}
/******************************************************/
	public function init_form_fields() {
		global $woocommerce;
		if ( $this->min_amount ){
			$default_requires = 'min_amount';
		}
		$this->form_fields = array(
			'enabled' => array(
							'title' 		=> __( 'Enable/Disable', 'woocommerce' ),
							'type' 			=> 'checkbox',
							'label' 		=> __( 'فعال کردن پست پیشتاز', 'woocommerce' ),
							'default' 		=> 'yes'
						),
			'title' => array(
							'title' 		=> __( 'Method Title', 'woocommerce' ),
							'type' 			=> 'text',
							'description' 	=> __( 'This controls the title which the user sees during checkout.', 'woocommerce' ),
							'default'		=> __( 'پست پیشتاز', 'woocommerce' ),
							'desc_tip'      => true,
						),
			'min_amount' => array(
							'title' 		=> __( 'Minimum Order Amount', 'woocommerce' ),
							'type' 			=> 'number',
							'custom_attributes' => array(
								'step'	=> 'any',
								'min'	=> '0'
							),
							'description' 	=> __( 'کمترین میزان خرید برای فعال شدن این روش ارسال.', 'woocommerce' ),
							'default' 		=> '0',
							'desc_tip'      => true,
							'placeholder'	=> '0.00'
						),
			 'username' => array(
							'title' 		=> __( 'نام کاربری', 'woocommerce' ),
							'type' 			=> 'text',
							'description' 	=> __( 'نام کاربری شما در سرویس خرید پستی ماها.', 'woocommerce' ),
							'default'		=> __( '', 'woocommerce' ),
							'desc_tip'      => true,
						),
			 'password' => array(
							'title' 		=> __( 'رمز عبور', 'woocommerce' ),
							'type' 			=> 'password',
							'description' 	=> __( 'رمز عبور برای اتصال به وب سرویس ماها.', 'woocommerce' ),
							'default'		=> __( '', 'woocommerce' ),
							'desc_tip'      => true,
						)
			);

		}
/******************************************************/
	public function admin_options() {
		?>
		 <h3><?php _e( 'پست پیشتاز' ); ?></h3>
		<table class="form-table">
		<?php
			// Generate the HTML For the settings form.
			$this->generate_settings_html();
		?>
		</table>
		<?php
   }
/******************************************************/
	public function is_available( $package ) {
	   global $woocommerce;
	   if ( $this->enabled == "no") return false;
	   if ( !in_array( get_woocommerce_currency(),array( 'IRR', 'IRT' ) )){ return false; }
	   if ( $this->w_unit != 'g' && $this->w_unit != 'kg'){ return false; }
	   if ( $this->username =="" || $this->password==""){ return false; }
	   // Enabled logic
	   $has_met_min_amount = false;
	   if ( isset( $woocommerce->cart->cart_contents_total ) ) {
			$total = ( $woocommerce->cart->prices_include_tax ) ? $woocommerce->cart->cart_contents_total + array_sum( $woocommerce->cart->taxes ) : $woocommerce->cart->cart_contents_total;
			$has_met_min_amount = ( $total >= $this->min_amount )? true : false;
	   }
	   if ( $has_met_min_amount ) $is_available = true;
	   return apply_filters( 'woocommerce_shipping_' . $this->id . '_is_available', $is_available );
	}
/******************************************************/
	public function calculate_shipping( $package ){
		global $woocommerce;
		$customer = $woocommerce->customer;
		if( empty($package['destination']['city'])){
			$rate = array('id'=> $this->id,'label' => $this->title,'cost' => 0);
			$this->add_rate( $rate );
		}
		$this->shipping_total = 0;
		$weight = 0;
		$unit = ($this->w_unit == 'g') ? 1 : 1000;
		$data = array();
		if (sizeof($woocommerce->cart->get_cart()) > 0 && ($customer->get_shipping_city())) {
			foreach ($woocommerce->cart->get_cart() as $item_id => $values) {
				$_product = $values['data'];
				if ($_product->exists() && $values['quantity'] > 0) {
					if (!$_product->is_virtual()) {
						  $weight += $_product->get_weight() * $unit * $values['quantity'];
					}
				}
			} //end foreach
			$data['weight']         = $weight;
			$data['service_type']   = 2;  // پیشتاز
			if ($weight) {
				$this->get_shipping_response($data, $package);
			}
		}
	}
/******************************************************/
	function get_shipping_response($data = false, $package){
		global $woocommerce;
		if($this->debug){
			$this->debug_file = new WC_MahaCOD_Debug();
		}
		$rates             = array();
		$customer          = $woocommerce->customer;
		$update_rates      = false;
		$debug_response    = array();
		$cart_items        = $woocommerce->cart->get_cart();
		foreach ($cart_items as $id => $cart_item) {
			$cart_temp[] = $id . $cart_item['quantity'];
		}
		$cart_hash         = hash('MD5', serialize($cart_temp));
		$service           = 0;//$this->maha_service();
		$total_price       = (get_woocommerce_currency() == "IRT") ? $woocommerce->cart->subtotal * 10 + $service : $woocommerce->cart->subtotal + $service;	
		$customer_state    = $package['destination']['state'];
		$customer_state    = explode('-', $customer_state);
		$customer_state    = intval($customer_state[0]);
		if( $customer_state && $customer_state >0){
			// nothing!
		}else{
			if($this->debug){
				ob_start();
				var_dump($customer_state);
				$text = ob_get_contents();
				ob_end_clean();
				$this->debug_file->write('@get_shipping_response::state is not valid:'.$text);
			}
			return false;
		}
		$customer_city      = $package['destination']['city'];
		$customer_city      = explode('-', $customer_city);
		$customer_city      = intval($customer_city[0]);
		if( $customer_city && $customer_city >0){
			// again nothing!
		}else{
			if($this->debug){
				$this->debug_file->write('@get_shipping_response::city is not valid:'.$customer_city);
			}
			return false;
		}
		$shipping_data = array(
		'ostan_maghsad'		=> $customer_state,
		'shahr_maghsad'		=> $customer_city,
		'products_weight'	  => $data['weight'],
		'total_price'		  => $total_price,
		'send_type'			=> $data['service_type']
		);
		$cache_data	= false;
		$update_rates  = true;
		if ($update_rates) {
			$result = $this->maha_shipping($shipping_data);
			if ($this->debug) {
				ob_start();
				var_dump($result);
				$text = ob_get_contents();
				ob_end_clean();
				$this->debug_file->write('@get_shipping_response::everything is Ok:'.$text);
			}
			$rates = $result;
			$cache_data['shipping_data']        = $shipping_data;
			$cache_data['cart_hash']            = $cart_hash;
			$cache_data['rates']                = $rates;
		}
		//set_transient(get_class($this), $cache_data, 60*60*5);
		$rate       = (get_woocommerce_currency() == "IRT") ? $rates/10  : $rates;
		$my_rate = array('id'=> $this->id,'label'=> $this->title,'cost'=> $rate);
		$this->add_rate($my_rate);
	}
/******************************************************/
	public function maha_shipping($data = false, $cache = false) {
		  global $woocommerce;
		  if ($this->debug) {
			  $this->debug_file->write('@maha_shipping::here is top of function');
		  }
		  $this->client                      = new nusoap_client( $this->wsdl_url, true );
		  $this->client->soap_defencoding    = 'UTF-8';
		  $this->client->decode_utf8         = true;
		  $this->client->setCredentials ($this->username ,$this->password,"basic");
		  $response                          = $this->call("calculation_send_price", $data);
		  if(is_array($response) && $response['error_code']){
			  if ($this->debug){
					$this->debug_file->write('@maha_service::'.$response['error_message']);
					wc_clear_notices();
					wc_add_notice('<p>maha Error:</p> <p>'.$response['error_message'].'</p>.', 'error');				
			  }
			  return 10000;
		  }
		  mkobject($response);
		  $cost = $response->send_price+$response->maliyat+$response->khadamat;
		  if ($this->debug) {
			  ob_start();
			  var_dump($data);
			  $text = ob_get_contents();
			  ob_end_clean();
			  $this->debug_file->write('@maha_shipping::Everything is Ok:'.$text);
		  }
		  return $cost;
	  }
/******************************************************/
	public function call($method, $params){
		$result = $this->client->call($method, $params);
		if($this->client->fault || ((bool)$this->client->getError())){
			return array('error' => true, 'fault' => true, 'message' => $this->client->getError());
		}
		return $result;
	 }
/******************************************************/
	public function handleError($error,$status){
		if($status =='sendprice'){
			switch ($error){
				case -1:
					return 'User name or password is wrong';
				break;
				case -2:
					return 'Requested service is wrong';
				break;
				case -3:
					return 'resquest is out of normal service';
				break;
				case -4:
					return 'weight or amount is invalid';
				break;
				default:
					return false;
				break;		
			}
		}
		if($status =='register'){
			switch ($error){
				case -1:
					return 'User name or password is wrong';
				break;			
				case -2:
					return 'Requested service is wrong';
				break;			
				case -3:
					return 'resquest is out of normal service';
				break;			
				case -4:
					return 'Products list is invalid';
				break;			
				case -5:
					return 'Error in webservice';
				break;			
				default:
					return false;
				break;
			}
		}
	}
} // end class

?>