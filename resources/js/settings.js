(
    function ({_, mollieSettingsData, jQuery })
    {

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

            if(_.isEmpty(mollieSettingsData)){
                return
            }
            const gatewayName = mollieSettingsData['current_section']
            let fixedField = $('#'+gatewayName+'_fixed_fee').closest('tr')
            let percentField = $('#'+gatewayName+'_percentage').closest('tr')
            let limitField = $('#'+gatewayName+'_surcharge_limit').closest('tr')
            let maxField = $('#'+gatewayName+'_maximum_limit').closest('tr')

            $('#'+gatewayName+'_payment_surcharge').change(function() {
                switch ($(this).val()){
                    case 'no_fee':
                        fixedField.hide()
                        percentField.hide()
                        limitField.hide()
                        maxField.hide()
                        break
                    case 'fixed_fee':
                        fixedField.show()
                        maxField.show()
                        percentField.hide()
                        limitField.hide()
                        break
                    case 'percentage':
                        fixedField.hide()
                        maxField.show()
                        percentField.show()
                        limitField.show()
                        break
                    case 'fixed_fee_percentage':
                    default:
                        fixedField.show()
                        percentField.show()
                        limitField.show()
                        maxField.show()
                }
            }).change();
        });
    }
)
(
    window
)
