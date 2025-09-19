// webpack.config.js (wp-scripts based, no Encore)
const path = require('path');
const MiniCssExtractPlugin = require('mini-css-extract-plugin');
const defaultConfig = require('@wordpress/scripts/config/webpack.config');

module.exports = (env = {}, argv = {}) => {
    const mode = argv.mode || process.env.NODE_ENV || 'development';
    const isProd = mode === 'production';
    const basePath = process.env.BASE_PATH || '.';

    // Common toggles to avoid extra runtime/vendor chunks (closer to Encore behavior)
    const noChunking = {
        optimization: {
            ...defaultConfig.optimization,
            runtimeChunk: false,
            splitChunks: { cacheGroups: { default: false, defaultVendors: false } },
        },
    };

    // --- JS bundle config ---
    const jsConfig = {
        ...defaultConfig,
        name: 'javascript-configuration',
        entry: {
            'applepay.min': './resources/js/src/features/apple-pay/applepay.js',
            'applepayDirect.min': './resources/js/src/features/apple-pay/applepayDirect.js',
            'applepayDirectCart.min': './resources/js/src/features/apple-pay/applepayDirectCart.js',
            'paypalButton.min': './resources/js/src/features/paypal/paypalButton.js',
            'paypalButtonCart.min': './resources/js/src/features/paypal/paypalButtonCart.js',
            'settings.min': './resources/js/src/admin/settings/settings.js',
            'gatewaySettings.min': './resources/js/src/admin/settings/gatewaySettings.js',
            'advancedSettings.min': './resources/js/src/admin/settings/advancedSettings.js',
            'gatewaySurcharge.min': './resources/js/src/features/surcharge/gatewaySurcharge.js',
            'mollie-components.min': './resources/js/src/checkout/legacy/components/mollie-components.js',
            'mollieBlockIndex.min': './resources/js/src/checkout/blocks/mollieBlockIndex.js',
            'paypalButtonBlockComponent.min':
                './resources/js/src/checkout/blocks/components/expressPayments/paypalButtonBlockComponent.js',
            'applepayButtonBlock.min': './resources/js/src/checkout/blocks/applepayButtonBlock.js',
            'rivertyCountryPlaceholder.min':
                './resources/js/src/features/regional/rivertyCountryPlaceholder.js',
            'mollie-settings-2024.min':
                './resources/js/src/admin/settings/mollie-settings-2024.js',
        },
        output: {
            ...defaultConfig.output,
            path: path.resolve(basePath, 'public/js'),
            filename: '[name].js',
            publicPath: '/public/js/',
            clean: true,
        },
        devtool: isProd ? false : 'source-map',
        ...noChunking,
    };

    // --- CSS/SCSS bundle config ---
    // Uses CSS entries directly. @wordpress/scripts already includes
    // RemoveEmptyScriptsPlugin so no stray JS files are emitted for pure-CSS entries.
    const cssConfig = {
        ...defaultConfig,
        name: 'css-configuration',
        entry: {
            'mollie-components.min': './resources/scss/mollie-components.scss',
            'mollie-gateway-icons.min': './resources/scss/mollie-gateway-icons.scss',
            'unabledButton.min': './resources/scss/unabledButton.scss',
            'mollie-applepaydirect.min': './resources/scss/mollie-applepaydirect.scss',
            'mollie-block-custom-field.min': './resources/scss/mollie-block-custom-field.scss',
        },
        output: {
            ...defaultConfig.output,
            path: path.resolve(basePath, 'public/css'),
            filename: '[name].noop.js', // will be removed by RemoveEmptyScriptsPlugin
            publicPath: '/public/css/',
            clean: true,
        },
        devtool: isProd ? false : 'source-map',
        plugins: [
            // Replace the default MiniCssExtractPlugin to control CSS output path/names
            ...defaultConfig.plugins.filter(
                (p) => !(p instanceof MiniCssExtractPlugin)
            ),
            new MiniCssExtractPlugin({
                filename: '[name].css',
                chunkFilename: '[name].css',
            }),
        ],
        module: {
            ...defaultConfig.module,
            rules: [
                ...defaultConfig.module.rules,
                // Sass loader is already configured by @wordpress/scripts.
                // No extra rule needed unless you want custom includePaths.
            ],
        },
        ...noChunking,
    };

    return [jsConfig, cssConfig];
};
