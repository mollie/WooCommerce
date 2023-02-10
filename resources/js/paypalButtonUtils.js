export const ajaxCallToOrder = (ajaxUrl) => {
    let button = document.getElementById('mollie-PayPal-button')
    if(!button){
        return
    }

    let preventSpam = false
    const nonce = button.children[0].value
    button.addEventListener('click', (evt) => {
        evt.preventDefault()
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
                nonce: nonce,
            },
            success: (response) => {
                let result = response.data
                if (response.success === true) {
                    window.location.href = result['redirect']
                } else {
                    console.log(response.data)
                }
            },
            error: (jqXHR, textStatus, errorThrown) => {
                button.disabled = false;
                button.classList.remove("buttonDisabled");
                console.warn(textStatus, errorThrown)
            },
        })
        preventSpam = true
        if(preventSpam){
            setTimeout(function() {
                button.disabled = false;
                button.classList.remove("buttonDisabled");
                preventSpam = false
            }, 3000);
        }
    })
}
