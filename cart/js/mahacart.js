function submitchform(){
     jQuery('input.checkout-button').click();
    }
jQuery(document).ready(function($) {
	$("select#ostan").change(function() {
		GetCity("ostan","shahrestan");
	});
});
