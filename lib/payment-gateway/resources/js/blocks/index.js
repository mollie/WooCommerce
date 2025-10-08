import {__} from '@wordpress/i18n';
import { registerPaymentMethod, registerExpressPaymentMethod } from '@woocommerce/blocks-registry';
import {decodeEntities} from '@wordpress/html-entities';
import {getSetting} from '@woocommerce/settings';
import { defaultHooks } from '@wordpress/hooks';
import { useEffect, useState } from 'react';

inpsydeGateways && inpsydeGateways.forEach((name) => {
    const settings = getSetting(`${name}_data`, {});
    const checkoutFieldsHookName = `${name}_checkout_fields`;
    const savedTokenFieldsHookName = `${name}_saved_token_fields`;
    const iconsHookName = `${name}_payment_method_icons`;
    const defaultLabel = __(
        'Syde Payment Gateway',
        'syde-payment-gateway'
    );

    const label = decodeEntities(settings.title) || defaultLabel;

    const Content = (props) => {
        const [components, setComponents] = useState([])
        useEffect(() => {
            setComponents(defaultHooks.applyFilters(checkoutFieldsHookName, []))
        }, []);
        /**
         * If no external plugins/slot-fills are configured,
         * we default to displaying the method description
         */
        if (!Array.isArray(components) || !components.length) {
            const DefaultPlugin = () => decodeEntities(settings.description || '');
            return <DefaultPlugin />
        }

        return (
            <>{components.map((Component) => <Component {...props} />)}</>
        );
    };
    /**
     * Label component
     *
     * @param {*} props Props from payment API.
     */
    const Label = (props) => {
        const {PaymentMethodLabel, PaymentMethodIcons} = props.components;
        return <>
            <PaymentMethodLabel text={label} />
            <PaymentMethodIcons icons={defaultHooks.applyFilters(iconsHookName, settings.icons)} />
        </>;
    };


    const SavedTokenContent = (props) => {
        const [components, setComponents] = useState([])
        useEffect(() => {
            setComponents(defaultHooks.applyFilters(savedTokenFieldsHookName, []))
        }, []);
        /**
         * If no external plugins/slot-fills are configured,
         * we default to not displaying anything
         */
        if (!Array.isArray(components) || !components.length) {
            return null;
        }

        return (
            <>{components.map((Component) => <Component {...props} />)}</>
        );
    };

    /**
     * Payment method config object.
     */
    const PaymentMethodArgs = {
        name: name,
        label: <Label />,
        content: <Content />,
        edit: <Content />,
        savedTokenComponent: <SavedTokenContent />,
        icons: settings.icons,
        canMakePayment: () => true,
        ariaLabel: label,
        supports: {
            features: settings.supports,
        }
    }
    if (settings.placeOrderButtonLabel) {
        PaymentMethodArgs.placeOrderButtonLabel = settings.placeOrderButtonLabel;
    }

    const shouldRegister = defaultHooks.applyFilters(`${name}_should_register_payment_method`, true, PaymentMethodArgs, settings);
    if (shouldRegister) {
        registerPaymentMethod(PaymentMethodArgs);
    }

    const expressMethodsHookName = `${name}_express_payment_methods`;
    const shouldRegisterExpressMethod = defaultHooks.applyFilters(expressMethodsHookName, false, PaymentMethodArgs, settings);
    if (shouldRegisterExpressMethod) {
        // hook for express payment method arguments
        const expressPaymentMethodArgs = defaultHooks.applyFilters(
            `${name}_express_payment_method_args`,
            {...PaymentMethodArgs},
            settings
        );
        registerExpressPaymentMethod(expressPaymentMethodArgs);
    }

})



