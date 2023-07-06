import {maybeRequireField, saveOriginalField} from "./wooCheckoutFieldsUtility";

(
    function ({jQuery}) {
        let positionField = 'li.wc_payment_method.payment_method_mollie_wc_gateway_in3';
        let gateway = 'mollie_wc_gateway_in3';
        let inputPhoneName = 'billing_phone';
        let originalPhone = saveOriginalField(inputPhoneName, {});
        let phoneId = 'billing_phone_field';
        let phoneField = jQuery('form[name="checkout"] p#billing_phone_field');
        let phoneMarkup = '<p class="form-row form-row-wide" id="billing_phone_field" data-priority="30">'
            + '<label for="billing_phone" class="">Phone'
            + '<abbr className="required" title="required">*</abbr>'
            + '</label>'
            + '<span class="woocommerce-input-wrapper">'
            + '<input type="tel" class="input-text " name="billing_phone" id="billing_phone" placeholder="+00000000000" value="" autocomplete="phone">'
            + '</span>'
            + '</p>'
        let inputBirthName = 'billing_birthdate';
        let originalBirth = saveOriginalField(inputBirthName, {});
        let birthId = 'billing_birthdate_field';
        let birthField = jQuery('form[name="checkout"] p#billing_birthdate_field');
        let birthMarkup = '<p class="form-row form-row-wide" id="billing_birthdate_field" data-priority="30">'
            + '<label for="billing_birthdate" class="">Birth date'
            + '<abbr className="required" title="required">*</abbr>'
            + '</label>'
            + '<span class="woocommerce-input-wrapper">'
            + '<input type="date" class="input-text " name="billing_birthdate" id="billing_birthdate" value="" autocomplete="birthdate">'
            + '</span>'
            + '</p>'
        jQuery(function () {
            jQuery('body')
                .on('updated_checkout payment_method_selected', function () {
                    phoneField = maybeRequireField(phoneField, positionField, phoneMarkup, inputPhoneName, phoneId, originalPhone, gateway);
                    birthField = maybeRequireField(birthField, positionField, birthMarkup, inputBirthName, birthId, originalBirth, gateway);
                });
        });
    }
)(
    window
)



