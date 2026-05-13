
(function({ gatewaySettingsData }) {
    const {
        uploadFieldName,
        iconUrl,
        message,
        removeLogoLabel,
        undoRemoveLabel,
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

        // handleCustomIconDisplay() has already injected the preview as the
        // upload field's next sibling. We work inside that container.
        const preview = uploadField.nextElementSibling;
        if (!preview || !preview.classList.contains('mollie_custom_icon')) {
            return;
        }

        const previewImg = preview.querySelector('img');
        if (!previewImg) {
            return;
        }

        // Hidden field travelling with the form on normal save. PHP picks it
        // up in Settings::processAdminOptionCustomLogo() and drops the stored
        // iconFileUrl/iconFilePath when its value is non-empty. We never
        // submit the form ourselves — toggling this flag only takes effect
        // when the merchant clicks "Save changes".
        const removeInputName = 'woocommerce_' + uploadFieldName.replace('_upload_logo', '_remove_logo');
        const hiddenInput = document.createElement('input');
        hiddenInput.type = 'hidden';
        hiddenInput.name = removeInputName;
        hiddenInput.value = '';
        form.appendChild(hiddenInput);

        // Mirror the empty-state placeholder rendered by
        // handleCustomIconDisplay() when no logo is stored, so toggling
        // pending-remove gives the merchant a true preview of the post-save
        // result. Hidden until the merchant marks the logo for removal.
        const placeholder = document.createElement('p');
        placeholder.textContent = message;
        placeholder.style.display = 'none';
        preview.insertBefore(placeholder, previewImg);

        const removeLink = document.createElement('button');
        removeLink.type = 'button';
        removeLink.className = 'mollie-remove-logo-link';
        preview.appendChild(removeLink);

        function setMarkedForRemoval(marked, notifyDirty) {
            if (marked) {
                hiddenInput.value = '1';
                previewImg.style.display = 'none';
                placeholder.style.display = '';
                removeLink.textContent = '↺ ' + undoRemoveLabel;
                removeLink.classList.add('mollie-remove-logo-link--undo');
            } else {
                hiddenInput.value = '';
                previewImg.style.display = '';
                placeholder.style.display = 'none';
                removeLink.textContent = '× ' + removeLogoLabel;
                removeLink.classList.remove('mollie-remove-logo-link--undo');
            }
            if (notifyDirty) {
                // WC's settings page tracks dirty state by listening for
                // change / input events on form inputs. Programmatically
                // setting .value does not fire either, so the save button
                // stays disabled until we synthesize them ourselves. Skipped
                // on initial render to avoid marking the form dirty on load.
                hiddenInput.dispatchEvent(new Event('input', { bubbles: true }));
                hiddenInput.dispatchEvent(new Event('change', { bubbles: true }));
            }
        }

        removeLink.addEventListener('click', function() {
            setMarkedForRemoval(hiddenInput.value !== '1', true);
        });

        // Picking a new file overrides a pending remove — the merchant is
        // replacing the logo, not deleting it. The PHP side also early-returns
        // on the remove flag and would silently drop an uploaded file, so
        // clearing the flag here keeps both layers in sync. The file input
        // itself already fires a change event, so WC's dirty tracker picks
        // that up; we just need to reset our own state.
        uploadField.addEventListener('change', function() {
            if (uploadField.files && uploadField.files.length > 0) {
                setMarkedForRemoval(false, false);
            }
        });

        setMarkedForRemoval(false, false);
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
