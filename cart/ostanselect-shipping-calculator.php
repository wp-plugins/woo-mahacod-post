<?php
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly
global $woocommerce;

wp_enqueue_script( 'city', untrailingslashit( plugins_url( '/', __FILE__ ) ). '/js/city.js', array(), '1.0.0', true );
wp_enqueue_script( 'mahacart', untrailingslashit( plugins_url( '/', __FILE__ ) ). '/js/mahacart.js', array(), '1.0.0', true );
do_action( 'woocommerce_before_shipping_calculator' ); ?>

<form class="shipping_calculator" action="<?php echo esc_url( $woocommerce->cart->get_cart_url() ); ?>" method="post" style="width: 65%!important;">
  <p class="selectcitynotice" style="display:none;padding: 10px 5px; background: #F2EABB; font:12px tahoma;border-radius: 5px;">استان و شهر خود را انتخاب کنید تا روش های ارسال ، هزینه هر روش و  جمع کل سفارش شما محاسبه شود</p>
  <section class="shipping-calculator">
    <?php 
	$output = '';
	$my_state = $woocommerce->customer->get_shipping_state();
	if(isset($my_state) && intval($my_state) > 0 ){
		$output .= " $('select#ostan').val(".$my_state."); GetCity('ostan','shahrestan');";
	}
	$my_city = $woocommerce->customer->get_shipping_city();
	if(isset($my_city) && intval($my_city) > 0 ){
		$output .= " $('select#shahrestan').val(".$my_city."); ";
	}
    if (!empty($output)){
    ?>
    <script type="text/javascript">jQuery(document).ready(function($) {<?php echo $output; ?>});</script>
    <?php }?>
    <style>
    select{font:12px tahoma; padding: 2px 1px;}
    </style>
    <p class="form-row form-row-last" id="billing_state_field" data-o_class="form-row form-row-last address-field">
      <label for="billing_state" class="">استان<abbr class="required" title="ضروری">*</abbr></label>
      <select tabindex="2" name="ostan" id="ostan">
        <option value="">لطفا استان خود را انتخاب کنید</option>
        <option value="41">آذربايجان شرقي</option>
        <option value="44">آذربايجان غربي</option>
        <option value="45">اردبيل</option>
        <option value="31">اصفهان</option>
        <option value="84">ايلام</option>
        <option value="77">بوشهر</option>
        <option value="21">تهران</option>
        <option value="38">چهارمحال بختياري</option>
        <option value="58">خراسان شمالي</option>
        <option value="56">خراسان جنوبي</option>
        <option value="51">خراسان رضوي</option>
        <option value="61">خوزستان</option>
        <option value="24">زنجان</option>
        <option value="23">سمنان</option>
        <option value="54">سيستان و بلوچستان</option>
        <option value="71">فارس</option>
        <option value="28">قزوين</option>
        <option value="25">قم</option>
        <option value="87">كردستان</option>
        <option value="34">كرمان</option>
        <option value="83">كرمانشاه</option>
        <option value="74">كهكيلويه و بويراحمد</option>
        <option value="17">گلستان</option>
        <option value="13">گيلان</option>
        <option value="66">لرستان</option>
        <option value="15">مازندران</option>
        <option value="86">مركزي</option>
        <option value="76">هرمزگان</option>
        <option value="81">همدان</option>
        <option value="35">يزد</option>
        <option value="26">البرز</option>
      </select>
    </p>
    <p class="form-row form-row-first address-field  update_totals_on_change" id="billing_city_field" data-o_class="form-row form-row-first address-field">
      <label for="billing_city" class="">شهر <abbr class="required" title="ضروری">*</abbr></label>
      <select name="shahrestan" id="shahrestan">
        <option value="0">استان را انتخاب کنید</option>
      </select>
    </p>
    <div style="clear:both;"></div>
    <div style="text-align:center;">
      <button type="submit" id="send_price_calculate" name="calc_shipping" value="1" class="button"><?php echo $have_city ? 'محاسبه مجدد هزینه ارسال' : 'محاسبه هزینه ارسال'; ?></button>
    </div>
    <?php wp_nonce_field('cart'); ?>
  </section>
</form>
<?php do_action( 'woocommerce_after_shipping_calculator' ); ?>
