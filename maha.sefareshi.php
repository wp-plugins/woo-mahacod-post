<?php 
class WC_mahacod_Sefareshi_Method extends WC_mahacod_Pishtaz_Method {
	public $username = "";
	public $password = "";
	public $w_unit   = "";
/*************************************************************************/
	public function __construct() {
		$this->id                 = 'mahacod_sefareshi'; 
		$this->method_title       = __( 'پست سفارشی' ); 
		$this->method_description = __( 'ارسال توسط پست سفارشی ' ); // Description shown in admin
		$this->init();
		$this->account_data();
	}
/*************************************************************************/
	public function init() {
		// Load the settings API
		$this->init_form_fields(); // This is part of the settings API. Override the method to add your own settings
		$this->init_settings(); // This is part of the settings API. Loads settings you previously init.
		$this->enabled		= $this->get_option( 'enabled' );
		$this->title 		= $this->get_option( 'title' );
		$this->min_amount 	= $this->get_option( 'min_amount', 0 );
		$this->w_unit      = strtolower( get_option('woocommerce_weight_unit') );
		// Save settings in admin if you have any defined
		add_action( 'woocommerce_update_options_shipping_' . $this->id, array( $this, 'process_admin_options' ) );
	}
/*************************************************************************/
	public function account_data() {
		$ins = new WC_mahacod_Pishtaz_Method();
		$this->username     = $ins->get_option( 'username', '' );
		$this->password     = $ins->get_option( 'password', '' );
	}
/*************************************************************************/
	public function init_form_fields() {
		global $woocommerce;
		if ( $this->min_amount ) { $default_requires = 'min_amount'; }
	   $this->form_fields = array(
					 'enabled' => array(
									'title' 		=> __( 'Enable/Disable', 'woocommerce' ),
									'type' 			=> 'checkbox',
									'label' 		=> __( 'فعال کردن پست سفارشی', 'woocommerce' ),
									'default' 		=> 'yes'
								),
					'title' => array(
														'title' 		=> __( 'Method Title', 'woocommerce' ),
									'type' 			=> 'text',
									'description' 	=> __( 'This controls the title which the user sees during checkout.', 'woocommerce' ),
									'default'		=> __( 'پست سفارشی', 'woocommerce' ),
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
								)
					);
	}
/*************************************************************************/
	public function admin_options() {
		  // Generate the HTML For the settings form.
		echo '<h3>'._e( 'پست سفارشی' ).'</h3>
	   <table class="form-table">'.$this->generate_settings_html().'</table>';
	}
/*************************************************************************/
	public function calculate_shipping( $package ) {
		global $woocommerce;
		$customer = $woocommerce->customer;
		if( empty($package['destination']['city'])) {
			$rate = array('id' => $this->id, 'label' => $this->title, 'cost' => 0 );
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
			$data['service_type']   = 1;  // سفارشی
			if ($weight) {
				$this->get_shipping_response($data, $package);
			}
		}
		
	}
/*************************************************************************/
}
?>