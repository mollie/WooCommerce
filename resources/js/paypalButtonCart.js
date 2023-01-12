import {ajaxCallToOrder} from "./paypalButtonUtils";

(
    function ({_, molliepaypalButtonCart, jQuery}) {

        if (_.isEmpty(molliepaypalButtonCart)) {
            return
        }

        const {product: {minFee}, ajaxUrl} = molliepaypalButtonCart

        if (!ajaxUrl) {
            return
        }

        const maybeShowButton = (underRange) => {
            if (underRange) {
                hideButton()
            }
        }

        const hideButton = () => {
            let payPalButton = document.getElementById('mollie-PayPal-button');
            if (payPalButton.parentNode !== null) {
                payPalButton.parentNode.removeChild(payPalButton)
            }
        }

        const extractValue = (path) => {
            return parseFloat(path.textContent).toFixed(2);
        }

        const calculateTaxes = () => {
            let taxesPath = document.getElementsByClassName('tax-rate')
            if (taxesPath.length === 0) {
                return 0
            }
            let total = 0.00;
            for (let tax of taxesPath) {
                let taxPath = tax.getElementsByClassName('woocommerce-Price-amount')[0]
                let workingNode = taxPath.cloneNode(true);
                let currency = workingNode.lastChild
                workingNode.removeChild(currency)
                total += parseFloat(extractValue(workingNode))
            }
            return total
        }

        const calculateTotal = () => {
            let subtotalPath = document.getElementsByClassName('cart-subtotal')[0].getElementsByClassName('woocommerce-Price-amount')[0].childNodes[0]
            let workingNode = subtotalPath.cloneNode(true);
            let currency = workingNode.getElementsByClassName('woocommerce-Price-currencySymbol')[0]
            workingNode.removeChild(currency)
            let total = parseFloat(extractValue(workingNode));
            total += calculateTaxes()

            return total
        }

        const underRange = () => {
            const updatedPrice = calculateTotal()
            return minFee > updatedPrice
        }

        jQuery(document.body).on('updated_cart_totals', function (event) {
            let payPalButton = document.getElementById('mollie-PayPal-button')
            if(payPalButton == null || payPalButton.parentNode == null){
                return
            }
            maybeShowButton(underRange())
            ajaxCallToOrder(ajaxUrl)
        })

        setTimeout(function(){
            let payPalButton = document.getElementById('mollie-PayPal-button');
            if(payPalButton == null || payPalButton.parentNode == null){
                return
            }
            maybeShowButton(underRange())
            ajaxCallToOrder(ajaxUrl)
        },500);

    }
)
(
    window
)



