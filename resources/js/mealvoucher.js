(
    function ({_, mealvoucherSettings, jQuery}) {
        if (_.isEmpty(mealvoucherSettings)) {
            return
        }

        const {message, productsWithCategory = 0} = mealvoucherSettings

        jQuery('body')
            .on('updated_checkout', function () {
                let button = document.getElementById("place_order");
                const element = document.getElementById("payment");
                jQuery('input[name="payment_method"]').change(function () {
                    //esto podría ser una función a parte checkingGateway()
                    if (jQuery('form[name="checkout"] input[name="payment_method"]:checked').val() === 'mollie_wc_gateway_mealvoucher') {
                        if (productsWithCategory === '1') {
                            button.disabled = true;
                            button.classList.add("buttonDisabled");
                            let div = document.createElement('div');
                            div.classList.add('error');
                            div.setAttribute('id', 'mealvoucher-error-notice')
                            let p = document.createElement('p');
                            p.appendChild(document.createTextNode(message));
                            div.appendChild(p);
                            element.parentNode.insertBefore(div, element.nextSibling);
                        }
                    } else {
                        button.disabled = false;
                        button.classList.remove("buttonDisabled");
                        let errorNotice = document.getElementById('mealvoucher-error-notice')
                        errorNotice && element.parentNode.removeChild(errorNotice)
                    }
                });
            });
    }
)
(
    window
)

