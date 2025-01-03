import {__} from '@wordpress/i18n';
import {registerPaymentMethod} from '@woocommerce/blocks-registry';
import {decodeEntities} from '@wordpress/html-entities';
import {getSetting} from '@woocommerce/settings';
import {defaultHooks} from '@wordpress/hooks';


inpsydeGateways.forEach((name) => {
    const settings = getSetting(`${name}_data`, {});
    const hookName = `${name}_checkout_fields`;
    const defaultLabel = __(
        'Syde Payment Gateway',
        'syde-payment-gateway'
    );

    const label = decodeEntities(settings.title) || defaultLabel;

    const Content = () => {
        const components = defaultHooks.applyFilters(hookName, [])
        console.log(hookName)

        /**
         * If no external plugins/slot-fills are configured,
         * we default to displaying the method description
         */
        if (!Array.isArray(components) || !components.length) {
            const DefaultPlugin = () => decodeEntities(settings.description || '');
            return <DefaultPlugin />
        }

        return (
            <>{components.map((Component) => <Component />)}</>
        );
    };
    /**
     * Label component
     *
     * @param {*} props Props from payment API.
     */
    const Label = (props) => {
        const {PaymentMethodLabel} = props.components;
        return <PaymentMethodLabel text={label} />;
    };

    /**
     * Payment method config object.
     */
    const PaymentMethod = {
        name: name,
        label: <Label />,
        content: <Content />,
        edit: <Content />,
        canMakePayment: () => true,
        ariaLabel: label,
        supports: {
            features: settings.supports,
        },
    };

    if (settings.placeOrderButtonLabel) {
        PaymentMethod.placeOrderButtonLabel = settings.placeOrderButtonLabel;
    }

    registerPaymentMethod(PaymentMethod);

})

