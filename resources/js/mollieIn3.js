import {maybeRequireField, saveOriginalField} from "./wooCheckoutFieldsUtility";

(
    function ({jQuery}) {
        let gateway = 'mollie_wc_gateway_in3';
        let inputPhoneName = 'billing_phone';
        let originalPhone = saveOriginalField(inputPhoneName, {});
        let phoneId = 'billing_phone_field';
        let phoneField = jQuery('form[name="checkout"] p#billing_phone_field');
        let positionPhoneField = 'form[name="checkout"] input[name="billing_city"]';
        let phoneMarkup = '<p class="form-row form-row-wide" id="billing_phone_field" data-priority="30">'
            + '<label for="billing_phone" class="">Phone'
            + '<abbr className="required" title="required">*</abbr>'
            + '</label>'
            + '<span class="woocommerce-input-wrapper">'
            + '<input type="text" class="input-text " name="billing_phone" id="billing_phone" placeholder="+00000000000" value="" autocomplete="phone">'
            + '</span>'
            + '</p>'
        let inputBirthName = 'billing_birthdate';
        let originalBirth = saveOriginalField(inputBirthName, {});
        let birthId = 'billing_birthdate_field';
        let birthField = jQuery('form[name="checkout"] p#billing_birthdate_field');
        let positionBirthField = 'form[name="checkout"] input[name="billing_phone"]';
        let birthMarkup = '<p class="form-row form-row-wide" id="billing_birthdate_field" data-priority="30">'
            + '<label for="billing_birthdate" class="">Birth date'
            + '<abbr className="required" title="required">*</abbr>'
            + '</label>'
            + '<span class="woocommerce-input-wrapper">'
            + '<input type="text" class="input-text " name="billing_birthdate" id="billing_birthdate" placeholder="dd/mm/yyyy" value="" autocomplete="birthdate">'
            + '</span>'
            + '</p>'
        jQuery(function () {
            jQuery('body')
                .on('updated_checkout', function () {
                    phoneField = maybeRequireField(phoneField, positionPhoneField, phoneMarkup, inputPhoneName, phoneId, originalPhone, gateway);
                    birthField = maybeRequireField(birthField, positionBirthField, birthMarkup, inputBirthName, birthId, originalBirth, gateway);
                });
            jQuery('body')
                .on('payment_method_selected', function () {
                    phoneField = maybeRequireField(phoneField, positionPhoneField, phoneMarkup, inputPhoneName, phoneId, originalPhone, gateway);
                    birthField = maybeRequireField(birthField, positionBirthField, birthMarkup, inputBirthName, birthId, originalBirth, gateway);
                });
        });
    }
)(
    window
)



