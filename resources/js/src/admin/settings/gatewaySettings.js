(
    function ({_, gatewaySettingsData, jQuery }) {
        const { isEnabledIcon, uploadFieldName, enableFieldName, iconUrl, message, pluginUrlImages } = gatewaySettingsData

        if (_.isEmpty(gatewaySettingsData)) {
            return
        }
        document.addEventListener("DOMContentLoaded", function(event) {

            if(!isEnabledIcon){
                return
            }

            let uploadField = document.querySelector('#'+uploadFieldName)

            if (_.isEmpty(iconUrl)) {
                uploadField.insertAdjacentHTML('afterend', '<div class="mollie_custom_icon"><p>' + message + '</p></div>');
            } else {
                uploadField.insertAdjacentHTML('afterend', '<div class="mollie_custom_icon"><img src="' + iconUrl + '" alt="custom icon image" width="100px"></div>');
            }

        });

        function iconName(val) {
            const res = val.split("-");
            return res[0]+"/"+res[1]+"/"+res[2]+"-"+res[3]
        }

        jQuery(function($) {

            $('#'+enableFieldName).change(function() {
                if ($(this).is(':checked'))
                {
                    $('#'+uploadFieldName).closest('tr').show();
                }
                else
                {
                    $('#'+uploadFieldName).closest('tr').hide();
                }
            }).change();

            const payPalIconSelectorElement = $('#mollie_wc_gateway_paypal_color')
            payPalIconSelectorElement.change(function() {
                const fixedPath = pluginUrlImages + "/PayPal_Buttons/"
                let buttonIcon = iconName(payPalIconSelectorElement.val())+".png"
                let url = fixedPath + buttonIcon
                const iconImageElement = $('#mol-paypal-settings-icon')
                if(iconImageElement.length){
                    iconImageElement.remove()
                }
                payPalIconSelectorElement.after("<img id='mol-paypal-settings-icon' width='200px' src="+url+" alt='PayPal_Icon'/>")

            }).change();
        });
    }
)
(
  window
)
