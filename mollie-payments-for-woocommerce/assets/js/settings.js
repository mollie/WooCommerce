jQuery(function($) {
    $('#mollie-payments-for-woocommerce_test_mode_enabled').change(function() {
        if ($(this).is(':checked'))
        {
            $('#mollie-payments-for-woocommerce_test_api_key').attr('required', true).closest('tr').show();
        }
        else
        {
            $('#mollie-payments-for-woocommerce_test_api_key').removeAttr('required').closest('tr').hide();
        }
    }).change();
});
