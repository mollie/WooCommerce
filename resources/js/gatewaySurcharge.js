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

                $('body').on('change', 'input[name="payment_method"]', function() {
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

                            if(result){
                                const productTotal = "</th><td class='product-total'><span class='woocommerce-Price-amount amount'><bdi><span class='woocommerce-Price-currencySymbol'>"+ result.currency +"</span>"+ result.newTotal +"</bdi></span></td>"

                                if(!result.amount){
                                    if($('#order_review table:first-child tfoot tr').text().indexOf('Gateway Fee') !== -1){
                                        $('#order_review table:first-child tfoot tr:contains("Gateway Fee")').remove()
                                        $('#order_review table:first-child tfoot tr:last td').replaceWith(productTotal)
                                    }
                                }else{
                                    const tableRow = "<tr><th scope='row' colspan='2'>"+ result.name + "</th><td class='product-total'><span class='woocommerce-Price-amount amount'><bdi><span class='woocommerce-Price-currencySymbol'>"+ result.currency +"</span>"+ (result.amount).toFixed(2) +"</bdi></span></td></tr>"
                                    if($('#order_review table:first-child tfoot tr').text().indexOf('Gateway Fee') !== -1){
                                        $('#order_review table:first-child tfoot tr:contains("Gateway Fee")').replaceWith(tableRow)
                                        $('#order_review table:first-child tfoot tr:last td').replaceWith(productTotal)
                                    }else{
                                        $('#order_review table:first-child tfoot tr:first').after(tableRow)
                                        $('#order_review table:first-child tfoot tr:last td').replaceWith(productTotal)
                                    }
                                }
                            }
                        },
                        error: (jqXHR, textStatus, errorThrown) => {
                            console.warn(textStatus, errorThrown)
                        },
                    })
                });
            });
        }
    }
)
(
    window
)



