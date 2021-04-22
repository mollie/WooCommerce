(
    function ({_, gatewaySettingsData, jQuery }) {
        const { isEnabledIcon, uploadFieldName, enableFieldName, iconUrl, message } = gatewaySettingsData

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


        });
    }
)
(
  window
)
