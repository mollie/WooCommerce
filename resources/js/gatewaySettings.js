(
    function ({_, gatewaySettingsData, jQuery }) {
        const { isEnabledIcon, uploadFieldName, enableFieldName, iconUrl } = gatewaySettingsData

        if (_.isEmpty(gatewaySettingsData)) {
            return
        }
        document.addEventListener("DOMContentLoaded", function(event) {

            if(!isEnabledIcon){
                return
            }

            let uploadField = document.querySelector('#'+uploadFieldName)

            uploadField.insertAdjacentHTML('afterend', '<div class="mollie_custom_icon"><img src="'+iconUrl+'" alt="custom icon image" width="100px"></div>');

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
