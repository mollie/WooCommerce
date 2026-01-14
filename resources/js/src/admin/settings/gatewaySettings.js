
(function({ gatewaySettingsData }) {
    const {
        isEnabledIcon,
        uploadFieldName,
        enableFieldName,
        iconUrl,
        message,
        pluginUrlImages,
    } = gatewaySettingsData;

    function isEmpty(obj) {
        return obj == null || Object.keys(obj).length === 0;
    }

    function iconName(val) {
        const res = val.split('-');
        return res[0] + '/' + res[1] + '/' + res[2] + '-' + res[3];
    }

    function handleCustomIconDisplay() {
        if (!isEnabledIcon) {
            return;
        }

        const uploadField = document.querySelector('#woocommerce_' + uploadFieldName);
        if (!uploadField) {
            return;
        }

        const iconHtml = isEmpty(iconUrl)
            ? '<div class="mollie_custom_icon"><p>' + message + '</p></div>'
            : '<div class="mollie_custom_icon"><img src="' + iconUrl + '" alt="custom icon image" width="100px"></div>';

        uploadField.insertAdjacentHTML('afterend', iconHtml);
    }

    function handleEnableFieldToggle() {
        const enableField = document.querySelector('#woocommerce_' + enableFieldName);
        const uploadField = document.querySelector('#woocommerce_' + uploadFieldName);

        if (!enableField || !uploadField) {
            return;
        }

        const uploadRow = uploadField.closest('tr');
        if (!uploadRow) {
            return;
        }

        function toggleUploadField() {
            if (enableField.checked) {
                uploadRow.style.display = '';
            } else {
                uploadRow.style.display = 'none';
            }
        }

        enableField.addEventListener('change', toggleUploadField);
        toggleUploadField();
    }

    function handlePayPalIconSelector() {
        const payPalIconSelector = document.querySelector('#woocommerce_mollie_wc_gateway_paypal_color');
        if (!payPalIconSelector) {
            return;
        }

        function updatePayPalIcon() {
            const fixedPath = pluginUrlImages + '/PayPal_Buttons/';
            const buttonIcon = iconName(payPalIconSelector.value) + '.png';
            const url = fixedPath + buttonIcon;

            const existingIcon = document.querySelector('#mol-paypal-settings-icon');
            if (existingIcon) {
                existingIcon.remove();
            }

            // Create and insert new icon
            const iconImg = document.createElement('img');
            iconImg.id = 'mol-paypal-settings-icon';
            iconImg.width = 200;
            iconImg.src = url;
            iconImg.alt = 'PayPal_Icon';

            payPalIconSelector.insertAdjacentElement('afterend', iconImg);
        }

        payPalIconSelector.addEventListener('change', updatePayPalIcon);
        updatePayPalIcon();
    }

    function initialize() {
        if (isEmpty(gatewaySettingsData)) {
            return;
        }

        handleCustomIconDisplay();
        handleEnableFieldToggle();
        handlePayPalIconSelector();
    }
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initialize);
    } else {
        console.log('DOM fully loaded and parsed');
        initialize();
    }
})(window);
