(function() {
    const mollieSettingsData = window.mollieSettingsData || {};
    const { current_section = false } = mollieSettingsData;

    function isEmpty(obj) {
        return !obj || Object.keys(obj).length === 0;
    }

    function getFieldElement(gatewayName, fieldName) {
        const element = document.getElementById(`${gatewayName}_${fieldName}`);
        return element ? element.closest('tr') : null;
    }

    function showElement(element) {
        if (element) {
            element.style.display = '';
        }
    }

    function hideElement(element) {
        if (element) {
            element.style.display = 'none';
        }
    }

    function handleSurchargeChange(fixedField, percentField, limitField, maxField) {
        return function() {
            const value = this.value;

            switch (value) {
                case 'no_fee':
                    hideElement(fixedField);
                    hideElement(percentField);
                    hideElement(limitField);
                    hideElement(maxField);
                    break;
                case 'fixed_fee':
                    showElement(fixedField);
                    showElement(maxField);
                    hideElement(percentField);
                    hideElement(limitField);
                    break;
                case 'percentage':
                    hideElement(fixedField);
                    showElement(maxField);
                    showElement(percentField);
                    showElement(limitField);
                    break;
                case 'fixed_fee_percentage':
                default:
                    showElement(fixedField);
                    showElement(percentField);
                    showElement(limitField);
                    showElement(maxField);
            }
        };
    }

    function initializeSurchargeSettings() {
        if (isEmpty(mollieSettingsData)) {
            return;
        }

        const gatewayName = `woocommerce_${current_section}`;
        if (!gatewayName || current_section === false) {
            return;
        }

        const fixedField = getFieldElement(gatewayName, 'fixed_fee');
        const percentField = getFieldElement(gatewayName, 'percentage');
        const limitField = getFieldElement(gatewayName, 'surcharge_limit');
        const maxField = getFieldElement(gatewayName, 'maximum_limit');

        const surchargeElement = document.getElementById(`${gatewayName}_payment_surcharge`);
        if (!surchargeElement) {
            return;
        }

        const changeHandler = handleSurchargeChange(fixedField, percentField, limitField, maxField);

        surchargeElement.addEventListener('change', changeHandler);

        // Trigger initial change event
        changeHandler.call(surchargeElement);
    }
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initializeSurchargeSettings);
    } else {
        initializeSurchargeSettings();
    }
})();
