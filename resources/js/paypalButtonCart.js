(
    function ({_, molliepaypalButtonCart, jQuery}) {

        if (_.isEmpty(molliepaypalButtonCart)) {
            return
        }

        const {product: {needShipping = true, minFee}, ajaxUrl} = molliepaypalButtonCart

        if (!ajaxUrl) {
            return
        }
        const nonce = document.getElementById('_wpnonce').value
        let redirectionUrl = ''
        let isButtonVisible = true;
        let payPalButton = document.getElementById('mollie-PayPal-button');

        const maybeShowButton = (underRange) => {
            if (underRange && isButtonVisible) {
                hideButton()
                isButtonVisible = false
            }
            if (!underRange && !isButtonVisible) {
                //woo reloads that part and renders the button
                isButtonVisible = true
            }
        }

        const hideButton = () => {
            payPalButton = document.getElementById('mollie-PayPal-button');
            if (payPalButton.parentNode !== null) {
                payPalButton.parentNode.removeChild(payPalButton)
            }
        }

        const extractValue = (path) => {
            let updatedPrice = path.textContent
            updatedPrice = updatedPrice.replace(/(€|$|£)/g, '');
            updatedPrice = parseFloat(updatedPrice).toFixed(2);
            return updatedPrice
        }

        const calculateTaxes = () => {
            let taxesPath = document.getElementsByClassName('tax-rate')
            if (taxesPath.length === 0) {
                return 0
            }
            let total = 0.00;
            for (let tax of taxesPath) {
                let taxPath = tax.getElementsByClassName('woocommerce-Price-amount')[0]
                total += parseFloat(extractValue(taxPath))
            }
            return total
        }

        const calculateTotal = () => {
            let subtotalPath = document.getElementsByClassName('cart-subtotal')[0].getElementsByClassName('woocommerce-Price-amount')[0]
            let total = parseFloat(extractValue(subtotalPath));
            total += calculateTaxes()

            return total
        }

        const underRange = () => {
            const updatedPrice = calculateTotal()
            return minFee > updatedPrice
        }

        const ajaxCallToOrder = () => {
            if (!isButtonVisible) {
                return
            }
            document.querySelector('#mollie-PayPal-button').addEventListener('click', (evt) => {
                console.log(isButtonVisible)
                if (!isButtonVisible) {
                    return
                }
                jQuery.ajax({
                    url: ajaxUrl,
                    method: 'POST',
                    data: {
                        action: 'mollie_paypal_create_order_cart',
                        'mollie-payments-for-woocommerce_issuer_paypal_button': 'paypal',
                        needShipping: needShipping,
                        nonce: nonce,
                    },
                    complete: (jqXHR, textStatus) => {
                    },
                    success: (authorizationResult, textStatus, jqXHR) => {
                        payPalButton.disabled = true;
                        payPalButton.classList.add("buttonDisabled");
                        let result = authorizationResult.data

                        if (authorizationResult.success === true) {
                            redirectionUrl = result['redirect'];
                            window.location.href = redirectionUrl
                        } else {
                            console.log(result.error)
                        }
                    },
                    error: (jqXHR, textStatus, errorThrown) => {
                        payPalButton.disabled = false;
                        payPalButton.classList.remove("buttonDisabled");
                        console.warn(textStatus, errorThrown)
                    },
                })
            })
        }

        maybeShowButton(underRange())
        jQuery(document.body).on('updated_cart_totals', function (event) {
            maybeShowButton(underRange())
            ajaxCallToOrder()
        })
        ajaxCallToOrder()
    }
)
(
    window
)



