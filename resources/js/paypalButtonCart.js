
(
    function ({_, molliepaypalButtonCart, jQuery}) {

        if (_.isEmpty(molliepaypalButtonCart)) {
            return
        }

        const {ajaxUrl} = molliepaypalButtonCart

        if ( !ajaxUrl) {
            return
        }
        const nonce = document.getElementById('_wpnonce').value
        let redirectionUrl = ''
        let payPalButton = document.querySelector('#mollie-PayPal-button');


        document.querySelector('#mollie-PayPal-button').addEventListener('click', (evt) => {

            jQuery.ajax({
                url: ajaxUrl,
                method: 'POST',
                data: {
                    action: 'mollie_paypal_create_order_cart',
                    'mollie-payments-for-woocommerce_issuer_paypal_button': 'paypal',
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
)
(
    window
)



