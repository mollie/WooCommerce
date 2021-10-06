
(
    function ({ mollieBlockData, wc, _}) {
        console.log(mollieBlockData)
        if (_.isEmpty(mollieBlockData)) {
            return
        }
        const { registerPaymentMethod } = wc.wcBlocksRegistry;
        console.log('register')
        console.log(registerPaymentMethod)
        const { gatewayData } = mollieBlockData;
        console.log('gateway')
        console.log(gatewayData)

        gatewayData.forEach(item=>{
            console.log('item')
            console.log(item)
            let paymentMethod = {
                name: item.name,
                label: <strong>{item.label}</strong>,
                content:  <div>{item.content}</div>,
                edit:  <div>{item.edit}</div>,
                paymentMethodId: item.paymentMethodId,
                canMakePayment: () => item.canMakePayment,
                ariaLabel: item.ariaLabel,
                supports: {
                    features: item.supports,
                },
            };

            registerPaymentMethod( paymentMethod );
        })
    }
)
(
    window, wc
)
