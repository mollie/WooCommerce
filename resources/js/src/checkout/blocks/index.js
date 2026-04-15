/* global wc */
import './store/index.js';
import {select} from '@wordpress/data';
import {initializeMollieStoreListeners} from './store/storeListeners';
import {MOLLIE_STORE_KEY} from './store';
import {addFilter} from '@wordpress/hooks';
import {getSetting} from '@woocommerce/settings';
import {PaymentMethodContentRenderer} from './components/PaymentMethodContentRenderer';
import {ApplePayUtils} from '../../shared/utils/applePayUtils';
import ApplePayButtonComponent from './components/expressPayments/ApplePayButtonComponent';
import ApplePayButtonEditorComponent from './components/expressPayments/ApplePayButtonEditorComponent';
import {isEditorContext} from '../../shared/utils/paymentUtils';
import PayPalButtonComponent from './components/expressPayments/PayPalButtonComponent';
import PayPalButtonEditorComponent from './components/expressPayments/PayPalButtonEditorComponent';
import {PayPalUtils} from '../../shared/utils/paypalUtils';
import {buildRegistrationContext} from './registration/contextBuilder';

/**
 * Initialization with mollieComponentsManager
 * Hooks for content and shouldRegister
 * The main registration is done in the paymentGateway lib
 *
 */
const isOrderPayPage = select(MOLLIE_STORE_KEY).getIsOrderPayPage();

//if we are in order pay page there are no blocks, so we bail
if (!isOrderPayPage) {
    try {
        // inpsydeGateways is an array of gateway ID strings, localized on our handle
        const mollieGateways = (window.inpsydeGateways || [])
            .filter((name) => name.startsWith('mollie_wc_gateway_'))
            .map((name) => ({name}));

        const context = buildRegistrationContext(wc);

        // These don't depend on stores and should run before the library registration
        registerAllContentHooks(mollieGateways, context);
        registerGatewayRegistrationHooks(mollieGateways);
        registerExpressPaymentMethodHooks(mollieGateways);
        registerIconHooks(mollieGateways);

        initializeMollieStoreListeners();

    } catch (error) {
        console.error('Mollie: Initialization failed:', error);
    }
}

function registerGatewayRegistrationHooks(mollieGateways) {
    const applePayGateway = mollieGateways.find((gateway) => {
        return gateway.name === ApplePayUtils.GATEWAY_NAME;
    });

    if (applePayGateway) {
        addFilter(
            `${applePayGateway.name}_should_register_payment_method`,
            'mollie/apple-pay-conditional-registration',
            (shouldRegister, PaymentMethodArgs, settings) => {
                if (isEditorContext()) {
                    return true;
                }
                return ApplePayUtils.canRegisterApplePay();
            }
        );
    }
}

function registerExpressPaymentMethodHooks(mollieGateways) {
    const applePayGateway = mollieGateways.find((gateway) => {
        return gateway.name === ApplePayUtils.GATEWAY_NAME;
    });

    if (applePayGateway) {
        addFilter(
            `${applePayGateway.name}_express_payment_method_args`,
            'mollie/apple-pay-express-args',
            (PaymentMethodArgs, settings) => {
                const isAppleSession = ApplePayUtils.isAppleSessionAvailable();

                return {
                    name: 'mollie_wc_gateway_applepay_express',
                    title: 'Apple Pay Express button',
                    description: 'Apple Pay Express button',
                    content: <ApplePayButtonComponent buttonData={settings.expressButtonData}/>,
                    edit: <ApplePayButtonEditorComponent/>,
                    ariaLabel: 'Apple Pay',
                    canMakePayment: () => {
                        if (isEditorContext()) {
                            return true;
                        }
                        return isAppleSession && window.ApplePaySession.canMakePayments();
                    },
                    paymentMethodId: 'mollie_wc_gateway_applepay',
                    gatewayId: 'mollie_wc_gateway_applepay',
                    supports: {
                        features: ['products'],
                        style: ['height', 'borderRadius'],
                    },
                };
            },
            10,
            2
        );

        addFilter(
            `${applePayGateway.name}_express_payment_methods`,
            'mollie/apple-pay-express-registration',
            (shouldRegister, PaymentMethodArgs, settings) => {
                if (isEditorContext()) {
                    return true;
                }
                if (!settings.isExpressEnabled) {
                    return false;
                }
                return ApplePayUtils.canRegisterApplePay();
            },
            10,
            3
        );
    }

    const paypalGateway = mollieGateways.find((gateway) => {
        return gateway.name === PayPalUtils.GATEWAY_NAME;
    });

    if (paypalGateway) {
        addFilter(
            `${paypalGateway.name}_express_payment_method_args`,
            'mollie/paypal-express-args',
            (PaymentMethodArgs, settings) => {
                return {
                    name: 'mollie_wc_gateway_paypal_express',
                    title: 'PayPal Express button',
                    description: 'PayPal Express button',
                    content: <PayPalButtonComponent buttonData={settings.expressButtonData}/>,
                    edit: <PayPalButtonEditorComponent/>,
                    ariaLabel: 'PayPal',
                    canMakePayment: ({cartItems = []} = {}) => {
                        if (isEditorContext()) {
                            return true;
                        }
                        if (!PayPalUtils.canRegisterPayPal()) {
                            return false;
                        }
                        const hasPhysicalItem = cartItems.some(
                            (item) => item.extensions?.['mollie-payments']?.virtual === false
                        );
                        return !hasPhysicalItem;
                    },
                    paymentMethodId: 'mollie_wc_gateway_paypal',
                    gatewayId: 'mollie_wc_gateway_paypal',
                    supports: {
                        features: ['products'],
                        style: ['height', 'borderRadius'],
                    },
                };
            },
            10,
            2
        );

        addFilter(
            `${paypalGateway.name}_express_payment_methods`,
            'mollie/paypal-express-registration',
            (shouldRegister, PaymentMethodArgs, settings) => {
                if (isEditorContext()) {
                    return true;
                }
                if (!settings.isExpressEnabled) {
                    return false;
                }
                if (!PayPalUtils.canRegisterPayPal()) {
                    return false;
                }
                // Only register when the cart contains exclusively virtual items.
                // Relies on per-item `virtual` flag exposed by
                // PayPalExpressButton::registerStoreApiExtension() via the
                // WC Store API extension mechanism. If the extension data is absent
                // (e.g. PayPal gateway disabled), the check is skipped (safe fallback).
                const cartItems = select('wc/store/cart')?.getCartItems() ?? [];
                const hasPhysicalItem = cartItems.some(
                    (item) => item.extensions?.['mollie-payments']?.virtual === false
                );
                return !hasPhysicalItem;
            },
            10,
            3
        );
    }
}

function registerAllContentHooks(mollieGateways, context) {
    if (typeof mollieGateways !== 'undefined' && mollieGateways.length > 0) {
        mollieGateways.forEach((gateway) => {
            const checkoutFieldsHookName = `${gateway.name}_checkout_fields`;
            addFilter(
                checkoutFieldsHookName,
                'mollie/register-payment-content-renderer',
                (components) => {
                    const item = getSetting(`${gateway.name}_data`, {});
                    const MollieComponent = (props) => {
                        const mappedProps = {
                            props,
                            item,
                            requiredFields: context.requiredFields,
                            shouldHidePhoneField: context.shouldHidePhoneField,
                        };

                        return <PaymentMethodContentRenderer {...mappedProps} />;
                    };

                    return [...components, MollieComponent];
                }
            );
        });
    }
}

function registerIconHooks(mollieGateways) {
    if (mollieGateways) {
        mollieGateways.forEach((gateway) => {
            const hookName = `${gateway.name}_payment_method_icons`;

            addFilter(
                hookName,
                'inpsyde-payment-gateway/icons',
                (defaultIcons) => {
                    const item = getSetting(`${gateway.name}_data`, {});
                    return item?.label?.iconsArray ?? defaultIcons;
                },
                10
            );
        });
    }
}
