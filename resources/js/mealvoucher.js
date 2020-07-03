(
    function ({ _, mealvoucherSettings, jQuery })
    {
        console.log('en script')
        if (_.isEmpty(mealvoucherSettings)) {
            return
        }

        let eventName = 'updated_checkout'
        const $document = jQuery(document)
        const { productsWithCategory = 0} = mealvoucherSettings

console.log(productsWithCategory)
        let message = 'text message that they have to remove or change gateway'

        jQuery('body')
            .on('updated_checkout', function () {
                let button = document.getElementById("place_order");
                let element = document.getElementById("payment");


                jQuery('input[name="payment_method"]').change(function () {
                    //esto podría ser una función a parte checkingGateway()
                    if (jQuery('form[name="checkout"] input[name="payment_method"]:checked').val() === 'mollie_wc_gateway_mealvoucher') {
                        //esta comparación debería ser con un mollie type

                        if(productsWithCategory === '1'){
                            button.disabled = true;
                            button.classList.add("buttonDisabled");
                            let div = document.createElement( 'div' );
                            div.classList.add( 'error');
                            div.setAttribute('id', 'mealvoucher-error-notice')
                            let p = document.createElement( 'p' );
                            p.appendChild( document.createTextNode( message ) );
                            div.appendChild( p );
                            element.parentNode.insertBefore( div, element.nextSibling);
                        }

                    } else {
                        button.disabled = false;
                        button.classList.remove("buttonDisabled");
                        let errorNotice = document.getElementById('mealvoucher-error-notice')
                        errorNotice && element.parentNode.removeChild(errorNotice)

                    }

                });
            });


        /*if (isCheckoutPayPage) {
            eventName = 'payment_method_selected'
        }

        $document.on(
            eventName,
            () => initializeComponents(
                jQuery,
                mollie,
                mollieComponentsSettings,
                mollieComponentsMap
            )
        )*/
    }
)
(
    window
)

