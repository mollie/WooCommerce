import {ajaxCallToOrder} from "./paypalButtonUtils";

(
    function ({  molliepaypalButtonCart})
    {
        console.log('paypal component')
        console.log(molliepaypalButtonCart)
        if (molliepaypalButtonCart.length === 0 ) {
            return
        }

        const { registerPlugin } = wp.plugins;
        const { ExperimentalOrderMeta } = wc.blocksCheckout;
        const { minFee, ajaxUrl, buttonMarkup } = molliepaypalButtonCart;
        console.log(minFee)
        console.log(buttonMarkup)
        const PayPalButtonComponent = ( { cart, extensions } ) => {
            console.log(cart)
            console.log(minFee)
            console.log(buttonMarkup)
            let cartTotal = cart.cartTotals.total_price/Math.pow(10, cart.cartTotals.currency_minor_unit)
            console.log(cartTotal)
            const amountOverRangeSetting = cartTotal > minFee;
            console.log(amountOverRangeSetting)
            const cartNeedsShipping = cart.cartNeedsShipping
            console.log(cartNeedsShipping)
            console.log(buttonMarkup)
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
        },500);
    }
)
(
    window
)
