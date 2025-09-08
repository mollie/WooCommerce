import { MOLLIE_STORE_KEY } from "../store";
import { createPaymentComponent } from './PaymentComponentFactory';
import { mollieComponentsManager } from '../services/MollieComponentsManager';
export const PaymentMethodContentRenderer = (props) => {
    const { useEffect, useRef } = wp.element;
    const { useSelect } = wp.data;

    const {
        activePaymentMethod,
        billing,
        item,
        jQuery,
        emitResponse,
        eventRegistration,
        requiredFields,
        shippingData,
        isPhoneFieldVisible
    } = props;

    const { responseTypes } = emitResponse;
    const { onPaymentSetup } = eventRegistration;
    const containerRef = useRef(null);

    // Redux store selectors
    const {
        selectedIssuer,
        inputPhone,
        inputBirthdate,
        inputCompany,
        cardToken,
        canCreateToken,
        isComponentReady,
        componentError
    } = useSelect((select) => ({
        selectedIssuer: select(MOLLIE_STORE_KEY).getSelectedIssuer(),
        inputPhone: select(MOLLIE_STORE_KEY).getInputPhone(),
        inputBirthdate: select(MOLLIE_STORE_KEY).getInputBirthdate(),
        inputCompany: select(MOLLIE_STORE_KEY).getInputCompany(),
        cardToken: select(MOLLIE_STORE_KEY).getCardToken(),
        canCreateToken: select(MOLLIE_STORE_KEY).getCanCreateToken(),
        isComponentReady: select(MOLLIE_STORE_KEY).getIsComponentReady(),
        componentError: select(MOLLIE_STORE_KEY).getComponentError()
    }), []);

    // Initialize ComponentManager when payment method changes
    useEffect(() => {
        if (activePaymentMethod && item.name === 'mollie_wc_gateway_creditcard') {
            const initializeComponents = async () => {
                try {
                    const mollieConfig = window.mollieComponentsSettings || {};
                    if (!mollieConfig.merchantProfileId) {
                        console.error('Mollie merchant profile ID not found');
                        return;
                    }

                    await mollieComponentsManager.initialize({
                        merchantProfileId: mollieConfig.merchantProfileId,
                        options: mollieConfig.options || {}
                    });
                    if (containerRef.current && mollieConfig.componentsSettings) {
                        await mollieComponentsManager.mountComponents(
                            activePaymentMethod,
                            mollieConfig.componentsAttributes,
                            mollieConfig.componentsSettings,
                            containerRef.current
                        );
                    }

                } catch (error) {
                    console.error('Failed to initialize Mollie components:', error);
                }
            };

            initializeComponents();
        }

        return () => {
            if (mollieComponentsManager.getActiveGateway() === activePaymentMethod) {
                mollieComponentsManager.unmountComponents(activePaymentMethod);
            }
        };
    }, [activePaymentMethod, item.name]);

    useEffect(() => {
        const onProcessingPayment = async () => {
            // For non-Mollie gateways, return immediately
            if (!activePaymentMethod.startsWith('mollie_wc_gateway_')) {
                return responseTypes.SUCCESS;
            }

            try {
                let token = cardToken;

                if (item.name === 'mollie_wc_gateway_creditcard' && canCreateToken && !token) {
                    token = await mollieComponentsManager.createToken();
                }

                const paymentData = {
                    payment_method: activePaymentMethod,
                    payment_method_title: item.title,
                    [`mollie-payments-for-woocommerce_issuer_${activePaymentMethod}`]: selectedIssuer,
                    billing_phone: inputPhone,
                    billing_company_billie: inputCompany,
                    billing_birthdate: inputBirthdate,
                    cardToken: token || '',
                };

                return {
                    type: responseTypes.SUCCESS,
                    payment_method: activePaymentMethod,
                    meta: { paymentMethodData: paymentData }
                };

            } catch (error) {
                console.error('Payment processing failed:', error);
                return {
                    type: responseTypes.ERROR,
                    message: error.message || 'Payment processing failed'
                };
            }
        };

        const unsubscribePaymentProcessing = onPaymentSetup(onProcessingPayment);
        return unsubscribePaymentProcessing;
    }, [
        activePaymentMethod,
        item,
        selectedIssuer,
        inputPhone,
        inputCompany,
        inputBirthdate,
        cardToken,
        canCreateToken,
        onPaymentSetup,
        responseTypes
    ]);

    // Prepare common props for child components
    const commonProps = {
        item,
        jQuery,
        useEffect,
        billing,
        shippingData,
        eventRegistration,
        requiredFields,
        isPhoneFieldVisible,
        activePaymentMethod,
        containerRef,
        tokenManager: mollieComponentsManager,
        isComponentReady,
        componentError
    };

    return (
        <div ref={containerRef} className="mollie-payment-method-container">
            {createPaymentComponent(item, commonProps)}
        </div>
    );
};
