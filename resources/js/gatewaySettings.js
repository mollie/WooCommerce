(
    function ({_, gatewaySettingsData, jQuery }) {
        const { isEnabledIcon, fieldName, iconUrl } = gatewaySettingsData
        console.log(gatewaySettingsData)
        if (_.isEmpty(gatewaySettingsData)) {
            return
        }
        document.addEventListener("DOMContentLoaded", function(event) {

            if(!isEnabledIcon){
                return
            }

            let uploadField = document.querySelector('#'+fieldName)

            uploadField.insertAdjacentHTML('afterend', '<div class="mollie_custom_icon"><img src="'+iconUrl+'" alt="custom icon image" width="100px"></div>');

        });
    }
)
(
  window
)
