(
    function ({jQuery, surchargeData}) {
        jQuery( function( $ ) {
            $('body').on('change', 'input[name="payment_method"]', function() {
                $('body').trigger('update_checkout');
            });
        });
        if(!surchargeData){
            return
        }

        const isOrderPay = document.body.classList.contains('woocommerce-order-pay')

        if(isOrderPay){
            jQuery( function( $ ) {
                let orderId = false;
                let hiddenField = $('input:hidden[name="mollie-woocommerce-orderId"]')
                if(hiddenField.length){
                    orderId = hiddenField.val()
                }
                const gatewayLabel = surchargeData.gatewayFeeLabel
                const updateSurcharge = () => {
                    jQuery.ajax({
                        url: surchargeData.ajaxUrl,
                        method: 'POST',
                        data: {
                            action: 'update_surcharge_order_pay',
                            method: $('input:radio[name="payment_method"]:checked').val(),
                            orderId: orderId
                        },
                        success: (response) => {
                            let result = response.data
                            if(!result?.template){
                                console.warn("Missing template in the response.");
                                return;
                            }

                            const {template} = result;
                            const DOMTemplate = jQuery.parseHTML(template);
                            const newShopTable = jQuery(DOMTemplate).find(".shop_table");

                            if(!newShopTable.length){
                                console.warn("Template changed, can't update the totals.");
                                return;
                            }

                            jQuery(".shop_table").html(jQuery(newShopTable).html());
                        },
                        error: (jqXHR, textStatus, errorThrown) => {
                            console.warn(textStatus, errorThrown)
                        },
                    })
                }
                updateSurcharge()
                $('body').on('change', 'input[name="payment_method"]', function() {
                    updateSurcharge()
                });
            });
        }
    }
)
(
    window
)



