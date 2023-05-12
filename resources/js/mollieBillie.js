import {maybeRequireField, saveOriginalField} from "./wooCheckoutFieldsUtility";

(
    function ({jQuery}) {
        let gateway = 'mollie_wc_gateway_billie';
        let inputCompanyName = 'billing_company';
        let originalBillingCompanyField = saveOriginalField(inputCompanyName, {});
        let companyFieldId = 'billing_company_field';
        let companyField = jQuery('form[name="checkout"] p#billing_company_field');
        let positionCompanyField = 'form[name="checkout"] input[name="billing_last_name"]';
        let companyMarkup = '<p class="form-row form-row-wide" id="billing_company_field" data-priority="30">'
            + '<label for="billing_company" class="">Company name&nbsp;'
            + '<abbr className="required" title="required">*</abbr>'
            + '</label>'
            + '<span class="woocommerce-input-wrapper">'
            + '<input type="text" class="input-text " name="billing_company" id="billing_company" placeholder="" value="" autocomplete="organization">'
            + '</span>'
            + '</p>'

        jQuery(function () {
            jQuery('body')
                .on('updated_checkout payment_method_selected', function () {
                    companyField = maybeRequireField(companyField, positionCompanyField, companyMarkup, inputCompanyName, companyFieldId, originalBillingCompanyField, gateway);
                });
        });
    }
)(
    window
)



