jQuery(function($) {
    $('#woocommerce-mollie-payments_test_mode_enabled').change(function() {
        if ($(this).is(':checked'))
        {
            $('#woocommerce-mollie-payments_test_api_key').attr('required', true).closest('tr').show();
        }
        else
        {
            $('#woocommerce-mollie-payments_test_api_key').removeAttr('required').closest('tr').hide();
        }
    }).change();
});
