(function (ApplePaySession) {
  document.addEventListener('DOMContentLoaded', function () {
    var applePayMethodElement = document.querySelector(
      '.payment_method_mollie_wc_gateway_applepay',
    )
    var woocommerceCheckoutForm = document.querySelector(
      'form.woocommerce-checkout',
    )

    if (!woocommerceCheckoutForm) {
      return
    }

    if (!ApplePaySession || !ApplePaySession.canMakePayments()) {
      applePayMethodElement &&
      applePayMethodElement.parentNode.removeChild(applePayMethodElement)

      woocommerceCheckoutForm.insertAdjacentHTML(
        'beforeend',
        '<input type="hidden" name="mollie_apple_pay_method_not_allowed" value="1" />',
      )
    }
  })
})(window.ApplePaySession)
