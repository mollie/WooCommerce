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
    .addEntry('applepay.min', './resources/js/applepay.js')
    .addEntry('applepayDirect.min', './resources/js/applepayDirect.js')
    .addEntry('applepayDirectCart.min', './resources/js/applepayDirectCart.js')
    .addEntry('paypalButton.min', './resources/js/paypalButton.js')
    .addEntry('paypalButtonCart.min', './resources/js/paypalButtonCart.js')
    .addEntry('settings.min', './resources/js/settings.js')
    .addEntry('gatewaySettings.min', './resources/js/gatewaySettings.js')
    .addEntry('advancedSettings.min', './resources/js/advancedSettings.js')
    .addEntry('gatewaySurcharge.min', './resources/js/gatewaySurcharge.js')
    .addEntry('mollie-components.min', './resources/js/mollie-components.js')
    .addEntry('mollieBlockIndex.min', './resources/js/mollieBlockIndex.js')
    .addEntry('paypalButtonBlockComponent.min', './resources/js/paypalButtonBlockComponent.js')
    .addEntry('applepayButtonBlockComponent.min', './resources/js/applepayButtonBlockComponent.js')
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
    .enableSourceMaps(!Encore.isProduction())

  return extractEncoreConfig('css-configuration')
}

function config (env)
{
  const config = [
    configJavaScript(env),
    configCss(env)
  ]

  return [...config]
}

module.exports = env => config(env)
