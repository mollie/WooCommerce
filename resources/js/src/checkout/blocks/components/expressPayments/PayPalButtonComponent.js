import {useState} from '@wordpress/element';
import {select} from '@wordpress/data';

export const PayPalButtonComponent = ({buttonData, buttonAttributes = {}}) => {

    const [isProcessing, setIsProcessing] = useState(false);

    if (!buttonData) {
        console.warn('Mollie PayPal: No button data available');
        return null;
    }

    const {ajaxUrl, buttonImageUrl, minFee = 0, nonce: dataNonce} = buttonData;

    const cartStore = select('wc/store/cart');
    const cartTotal = cartStore?.getCartTotals()?.total_price /
        Math.pow(10, cartStore?.getCartTotals()?.currency_minor_unit || 2) || 0;
    const cartNeedsShipping = cartStore?.getNeedsShipping() || false;

    // Don't show if cart needs shipping or below minimum
    const shouldShow = cartTotal > minFee && !cartNeedsShipping;

    if (!shouldShow) {
        return null;
    }

    let nonce = false;
    const wooNonceElement = document.getElementById('woocommerce-process-checkout-nonce');
    if (wooNonceElement) {
        nonce = wooNonceElement.value;
    }
    if (!nonce) {
        nonce = dataNonce;
    }

    const handlePayPalClick = async (event) => {
        event.preventDefault();

        if (isProcessing) {
            return;
        }

        setIsProcessing(true);

        try {
            const params = new URLSearchParams();
            params.append('action', 'mollie_paypal_create_order_cart');
            params.append('mollie-payments-for-woocommerce_issuer_paypal_button', 'paypal');
            params.append('nonce', nonce);

            const response = await fetch(ajaxUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: params.toString(),
            });

            const result = await response.json();

            if (result.success === true) {
                window.location.href = result.data.redirect;
            } else {
                console.error('PayPal order creation failed:', result.data);
                setIsProcessing(false);
            }
        } catch (error) {
            console.error('PayPal error:', error);
            setIsProcessing(false);
        }
    };

    const buttonStyle = {
        background: 'none',
        border: 'none',
        padding: 0,
        cursor: isProcessing ? 'not-allowed' : 'pointer',
        opacity: isProcessing ? 0.6 : 1,
        display: 'inline-block',
    };

    const imageStyle = {
        height: `${buttonAttributes.height || 48}px`,
        borderRadius: `${buttonAttributes.borderRadius || 4}px`,
        display: 'block',
    };

    return (
        <div id="mollie-PayPal-button" className="mol-PayPal">
            <button
                id="mollie_paypal_button"
                className="mollie-paypal-button"
                onClick={handlePayPalClick}
                disabled={isProcessing}
                style={buttonStyle}
                type="button"
            >
                {buttonImageUrl ? (
                    <img
                        src={buttonImageUrl}
                        alt="PayPal Button"
                        style={imageStyle}
                    />
                ) : (
                    <span>Pay with PayPal</span>
                )}
            </button>
        </div>
    );
};

export default PayPalButtonComponent;
