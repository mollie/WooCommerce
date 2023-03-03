(
    function ({jQuery}) {
        let gateway = 'mollie_wc_gateway_billie';
        let originalBillingCompanyField = {};
        saveOriginalBillingCompanyField();

        function usingGateway(gateway)
        {
            return jQuery('form[name="checkout"] input[name="payment_method"]:checked').val() === gateway;
        }
        function showBillingCompanyField()
        {
            let billingField = jQuery('form[name="checkout"] p#billing_company_field');
            if ((billingField).length <= 0) {
                jQuery('form[name="checkout"] input[name="billing_last_name"]')
                    .closest('p')
                    .after(
                        '<p class="form-row form-row-wide" id="billing_company_field" data-priority="30">'
                        + '<label for="billing_company" class="">Company name&nbsp;'
                        + '<abbr className="required" title="required">*</abbr>'
                        + '</label>'
                        + '<span class="woocommerce-input-wrapper">'
                        + '<input type="text" class="input-text " name="billing_company" id="billing_company" placeholder="" value="" autocomplete="organization">'
                        + '</span>'
                        + '</p>'
                    );
            }
        }
        function requireBillingCompanyField()
        {
            jQuery('form[name="checkout"] input[name="billing_company"]').attr('required', '');
            jQuery('form[name="checkout"] p#billing_company_field').addClass('validate-required');
            jQuery('form[name="checkout"] p#billing_company_field label span').replaceWith('<abbr class="required" title="required">*</abbr>');
        }

        function saveOriginalBillingCompanyField()
        {
            let billingCompanyField = jQuery('form[name="checkout"] input[name="billing_company"]');
            let isVisible = billingCompanyField.is(':visible');
            let isRequired = billingCompanyField.prop('required');
            originalBillingCompanyField = { isVisible, isRequired };
        }

        function removeBillingCompanyField()
        {
            jQuery('form[name="checkout"] p#billing_company_field').remove();
        }

        function unrequireBillingCompanyField()
        {
            jQuery('form[name="checkout"] input[name="billing_company"]').removeAttr('required');
            jQuery('form[name="checkout"] p#billing_company_field').removeClass('validate-required');
            jQuery('form[name="checkout"] p#billing_company_field label abbr').replaceWith('<span class="optional">(optional)</span>');

        }

        function restoreOriginalBillingCompanyField()
        {
            let billingCompanyField = jQuery('form[name="checkout"] input[name="billing_company"]');
            let currentVisibility = billingCompanyField.is(':visible');
            let currentRequired = billingCompanyField.prop('required');
            if (currentVisibility !== originalBillingCompanyField.isVisible) {
                if (originalBillingCompanyField.isVisible) {
                    showBillingCompanyField();
                } else {
                    removeBillingCompanyField();
                }
            }
            if (currentRequired !== originalBillingCompanyField.isRequired) {
                if (originalBillingCompanyField.isRequired) {
                    requireBillingCompanyField();
                } else {
                    unrequireBillingCompanyField();
                }
            }
        }

        function maybeRequireBillingCompanyField()
        {
            if (usingGateway(gateway)) {
                showBillingCompanyField();
                requireBillingCompanyField();
            } else {
                restoreOriginalBillingCompanyField();
            }
        }

        jQuery(function () {
            jQuery('body')
                .on('updated_checkout', function () {
                    maybeRequireBillingCompanyField();
                });
            jQuery('body')
                .on('payment_method_selected', function () {
                    maybeRequireBillingCompanyField();
                });
        });
    }
)(
    window
)



