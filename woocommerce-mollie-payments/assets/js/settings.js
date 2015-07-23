jQuery(function($) {
    $('#woocommerce-mollie-payments_test_mode_enabled').change(function() {
        if ($(this).is(':checked'))
        {
            $('#woocommerce-mollie-payments_test_api_key').closest('tr').show();
        }
        else
        {
            $('#woocommerce-mollie-payments_test_api_key').closest('tr').hide();
        }
    }).change();
});
