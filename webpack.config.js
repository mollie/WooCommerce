const path = require('path')
const webpack = require('webpack')

const ENV_PRODUCTION = 'production'
const ENV_DEVELOPMENT = 'development'
const ENV_NONE = 'none'

/**
 * Resolve path based on dirname
 * @param part
 * @returns {*}
 */
function resolve (part)
{
  return path.resolve(__dirname, part)
}

module.exports = (env, argv) =>
{
  const mode = argv.mode || ENV_PRODUCTION
  const devtool = 'eval-source-map'
  const sourceMap = new webpack.SourceMapDevToolPlugin({
    filename: '[file].map',
  })
  const buildBasePath = argv.buildBasePath || '.'
  const configJsOutput = {
    path: resolve(`${buildBasePath}/assets/js`),
    filename: '[name].min.js',
  }

  const config = new Set([
    {
      entry: {
        "babel-polyfill": "@babel/polyfill",
      },
      output: configJsOutput
    },
    {
      entry: {
        applepay: './resources/js/applepay.js',
      },
      output: configJsOutput
    },
    {
      entry: {
        'mollie-components': './resources/js/mollie-components.js',
      },
      output: configJsOutput
    },
    {
      entry: {
        settings: './resources/js/settings.js',
      },
      output: configJsOutput
    },
  ])

  config.forEach(item => {
    if (mode === ENV_DEVELOPMENT) {
      Object.assign(item, {
        devtool,
        plugins: [
          sourceMap,
        ],
      })
    }

    Object.assign(item, {
      mode,
      module: {
        rules: [
          {
            test: /\.js$/,
            exclude: /(node_modules)/,
            use: {
              loader: 'babel-loader',
              options: {
                presets: [
                  ['@babel/preset-env'],
                ],
              },
            },
          },
        ],
      },
    })
  })

  return [...config.values()]
}
