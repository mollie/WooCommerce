import {ajaxCallToOrder} from "./paypalButtonUtils";

(
    function ({  molliepaypalButtonCart})
    {
        if (molliepaypalButtonCart.length === 0 ) {
            return
        }

        const { registerPlugin } = wp.plugins;
        const { ExperimentalOrderMeta } = wc.blocksCheckout;
        const { minFee, ajaxUrl, buttonMarkup } = molliepaypalButtonCart;
        const PayPalButtonComponent = ( { cart, extensions } ) => {
            let cartTotal = cart.cartTotals.total_price/Math.pow(10, cart.cartTotals.currency_minor_unit)
            const amountOverRangeSetting = cartTotal > minFee;
            const cartNeedsShipping = cart.cartNeedsShipping
            return amountOverRangeSetting && !cartNeedsShipping ? <div dangerouslySetInnerHTML={ {__html: buttonMarkup} }/>: null
        }
        const MolliePayPalButtonCart = () => {
            return  <ExperimentalOrderMeta>
                    <PayPalButtonComponent />
                </ExperimentalOrderMeta>
        };

        registerPlugin( 'mollie-paypal-block-button', {
            render: () => {
                return <MolliePayPalButtonCart />;
            },
            scope: 'woocommerce-checkout'
        } );

        setTimeout(function(){
            let payPalButton = document.getElementById('mollie-PayPal-button');
            if(payPalButton == null || payPalButton.parentNode == null){
                return
            }
            ajaxCallToOrder(ajaxUrl)
        },3000);
    }
)
(
    window
)
