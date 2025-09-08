const Encore = require('@symfony/webpack-encore')

function extractEncoreConfig (name)
{
  const config = Encore.getWebpackConfig()

  Encore.reset()

  return { ...config, name }
}

function configJavaScript ({ basePath })
{
  Encore
    .setOutputPath(`${basePath}/public/js`)
    .setPublicPath('/public/js')
    .disableSingleRuntimeChunk()
    .addEntry('babel-polyfill.min', '@babel/polyfill')
    .addEntry('applepay.min', './resources/js/src/features/apple-pay/applepay.js')
    .addEntry('applepayDirect.min', './resources/js/src/features/apple-pay/applepayDirect.js')
    .addEntry('applepayDirectCart.min', './resources/js/src/features/apple-pay/applepayDirectCart.js')
    .addEntry('paypalButton.min', './resources/js/src/features/paypal/paypalButton.js')
    .addEntry('paypalButtonCart.min', './resources/js/src/features/paypal/paypalButtonCart.js')
    .addEntry('settings.min', './resources/js/src/admin/settings/settings.js')
    .addEntry('gatewaySettings.min', './resources/js/src/admin/settings/gatewaySettings.js')
    .addEntry('advancedSettings.min', './resources/js/src/admin/settings/advancedSettings.js')
    .addEntry('gatewaySurcharge.min', './resources/js/src/features/surcharge/gatewaySurcharge.js')
    .addEntry('mollie-components.min', './resources/js/src/checkout/legacy/components/mollie-components.js')
    .addEntry('mollieBlockIndex.min', './resources/js/src/checkout/blocks/mollieBlockIndex.js')
    .addEntry('paypalButtonBlockComponent.min', './resources/js/src/checkout/blocks/components/expressPayments/paypalButtonBlockComponent.js')
    .addEntry('applepayButtonBlock.min', './resources/js/src/checkout/blocks/applepayButtonBlock.js')
    .addEntry('rivertyCountryPlaceholder.min', './resources/js/src/features/regional/rivertyCountryPlaceholder.js')
      .addEntry('mollie-settings-2024.min', './resources/js/src/admin/settings/mollie-settings-2024.js')
      .enableSourceMaps(!Encore.isProduction())

  return extractEncoreConfig('javascript-configuration')
}

function configCss ({ basePath })
{
  Encore
    .setOutputPath(`${basePath}/public/css`)
    .setPublicPath('/public/css')
    .disableSingleRuntimeChunk()
    .enableSassLoader()
    .addStyleEntry('mollie-components.min', './resources/scss/mollie-components.scss')
    .addStyleEntry('mollie-gateway-icons.min', './resources/scss/mollie-gateway-icons.scss')
    .addStyleEntry('unabledButton.min', './resources/scss/unabledButton.scss')
    .addStyleEntry('mollie-applepaydirect.min', './resources/scss/mollie-applepaydirect.scss')
    .addStyleEntry('mollie-block-custom-field.min', './resources/scss/mollie-block-custom-field.scss')
    .enableSourceMaps(!Encore.isProduction())

  return extractEncoreConfig('css-configuration')
}

function config (env)
{
    const basePath = process.env.BASE_PATH || '.';
    const config = [
    configJavaScript({basePath}),
    configCss({basePath})
  ]

  return [...config]
}

module.exports = env => config(env)
