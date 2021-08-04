(
    function ({_, molliepaypalButtonCart, jQuery}) {

        if (_.isEmpty(molliepaypalButtonCart)) {
            return
        }

        const {product: {needShipping = true, minFee}, ajaxUrl} = molliepaypalButtonCart

        if (!ajaxUrl) {
            return
        }

        let redirectionUrl = ''
        let payPalButton = document.getElementById('mollie-PayPal-button');

        const maybeShowButton = (underRange) => {
            if (underRange) {
                hideButton()
            }
        }

        const hideButton = () => {
            payPalButton = document.getElementById('mollie-PayPal-button');
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
            let currency = workingNode.lastChild
            workingNode.removeChild(currency)
            let total = parseFloat(extractValue(workingNode));
            total += calculateTaxes()

            return total
        }

        const underRange = () => {
            const updatedPrice = calculateTotal()
            return minFee > updatedPrice
        }

        const ajaxCallToOrder = () => {
            let button = document.getElementById('mollie-PayPal-button')

            if(!button){
                return
            }

            let preventSpam = false
            const nonce = button.children[0].value
            button.addEventListener('click', (evt) => {
                if(!button){
                    return
                }
                button.disabled = true;
                button.classList.add("buttonDisabled");
                jQuery.ajax({
                    url: ajaxUrl,
                    method: 'POST',
                    data: {
                        action: 'mollie_paypal_create_order_cart',
                        'mollie-payments-for-woocommerce_issuer_paypal_button': 'paypal',
                        needShipping: needShipping,
                        nonce: nonce,
                    },
                    success: (response) => {
                        let result = response.data
                        if (response.success === true) {
                            redirectionUrl = result['redirect'];
                            window.location.href = redirectionUrl
                        } else {
                            console.log(response.data)
                        }
                    },
                    error: (jqXHR, textStatus, errorThrown) => {
                        payPalButton.disabled = false;
                        payPalButton.classList.remove("buttonDisabled");
                        console.warn(textStatus, errorThrown)
                    },
                })
                preventSpam = true
                if(preventSpam){
                    let seconds = 3
                    var countdown = setInterval(function() {
                        seconds--;
                        if (seconds <= 0){
                            clearInterval(countdown);
                            payPalButton.disabled = false;
                            payPalButton.classList.remove("buttonDisabled");
                            preventSpam = false
                        }
                    }, 1000);
                }
            })
        }
        jQuery(document.body).on('updated_cart_totals', function (event) {
            let payPalButton = document.getElementById('mollie-PayPal-button')
            if(payPalButton == null || payPalButton.parentNode == null){
                return
            }
            maybeShowButton(underRange())
            ajaxCallToOrder()
        })
        if(payPalButton == null || payPalButton.parentNode == null){
            return
        }
        maybeShowButton(underRange())
        ajaxCallToOrder()
    }
)
(
    window
)



