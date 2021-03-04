
(
    function ({_, molliepaypalbutton, jQuery}) {

        if (_.isEmpty(molliepaypalbutton)) {
            return
        }

        const {product: {id, needShipping = true, isVariation = false, price}, ajaxUrl} = molliepaypalbutton

        if (!id || !price || !ajaxUrl) {
            return
        }

        const nonce = document.getElementById('_wpnonce').value
        let productId = id
        let productQuantity = 1
        let redirectionUrl = ''
        document.querySelector('input.qty').addEventListener('change', event => {
            productQuantity = event.currentTarget.value
        })
        let payPalButton = document.querySelector('#mollie-PayPal-button');
        if (isVariation) {


            jQuery('.single_variation_wrap').on('show_variation', function (event, variation) {
                // Fired when the user selects all the required dropdowns / attributes
                // and a final variation is selected / shown
                if (variation.variation_id) {
                    productId = variation.variation_id
                }
                payPalButton.disabled = false;
                payPalButton.classList.remove("buttonDisabled");
            });
            payPalButton.disabled = true;
            payPalButton.classList.add("buttonDisabled");
        }

        document.querySelector('#mollie-PayPal-button').addEventListener('click', (evt) => {
            payPalButton.disabled = true;
            payPalButton.classList.add("buttonDisabled");
            jQuery.ajax({
                url: ajaxUrl,
                method: 'POST',
                data: {
                    action: 'mollie_paypal_create_order',
                    productId: productId,
                    productQuantity: productQuantity,
                    needShipping: needShipping,
                    'mollie-payments-for-woocommerce_issuer_paypal_button': 'paypal',
                    nonce: nonce,
                },
                complete: (jqXHR, textStatus) => {
                },
                success: (authorizationResult, textStatus, jqXHR) => {
                    payPalButton.disabled = false;
                    payPalButton.classList.remove("buttonDisabled");
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
)
(
    window
)



