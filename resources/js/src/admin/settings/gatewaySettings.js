
(function({ gatewaySettingsData }) {
    const {
        uploadFieldName,
        iconUrl,
        message,
        removeLogoLabel,
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
        const uploadField = document.querySelector('#woocommerce_' + uploadFieldName);
        if (!uploadField) {
            return;
        }

        const iconHtml = isEmpty(iconUrl)
            ? '<div class="mollie_custom_icon"><p>' + message + '</p></div>'
            : '<div class="mollie_custom_icon"><img src="' + iconUrl + '" alt="custom icon image" width="100px"></div>';

        uploadField.insertAdjacentHTML('afterend', iconHtml);
    }

    function handleRemoveLogoButton() {
        if (isEmpty(iconUrl)) {
            return;
        }

        const uploadField = document.querySelector('#woocommerce_' + uploadFieldName);
        if (!uploadField) {
            return;
        }

        const form = uploadField.closest('form');
        if (!form) {
            return;
        }

        const removeInputName = 'woocommerce_' + uploadFieldName.replace('_upload_logo', '_remove_logo');

        const hiddenInput = document.createElement('input');
        hiddenInput.type = 'hidden';
        hiddenInput.name = removeInputName;
        hiddenInput.value = '';
        form.appendChild(hiddenInput);

        const removeBtn = document.createElement('button');
        removeBtn.type = 'button';
        removeBtn.textContent = removeLogoLabel;
        removeBtn.className = 'button';
        removeBtn.style.marginLeft = '8px';
        removeBtn.addEventListener('click', function() {
            hiddenInput.value = '1';
            form.submit();
        });

        uploadField.insertAdjacentElement('afterend', removeBtn);
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
        handleRemoveLogoButton();
        handlePayPalIconSelector();
    }
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initialize);
    } else {
        initialize();
    }
})(window);
