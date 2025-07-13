const path = require('path');
const DependencyExtractionWebpackPlugin = require('@wordpress/dependency-extraction-webpack-plugin');
const CopyWebpackPlugin = require('copy-webpack-plugin');
const wpScriptConfig = require('@wordpress/scripts/config/webpack.config');

const isProduction = true;
const stats = {
  preset: 'minimal',
  assets: false,
  modules: false,
  chunks: true,
};

function requestToExternal(request) {
  const wcDepMap = {
    '@woocommerce/settings': ['wc', 'wcSettings'],
    '@woocommerce/blocks-checkout': ['wc', 'blocksCheckout'],
  };
  if (wcDepMap[request]) return wcDepMap[request];
}

function requestToHandle(request) {
  const wcHandleMap = {
    '@woocommerce/settings': 'wc-settings',
    '@woocommerce/blocks-checkout': 'wc-blocks-checkout',
  };
  if (wcHandleMap[request]) return wcHandleMap[request];
}

// Map entry points to output folders
const outputFolderMap = {
  'sms-consent-block': 'sms_consent',
  'sms-consent-block-frontend': 'sms_consent',
  'email-consent-block': 'email_consent',
  'email-consent-block-frontend': 'email_consent',
};

module.exports = {
  ...wpScriptConfig,
  name: 'consent_blocks',
  mode: isProduction ? 'production' : 'development',
  stats,
  entry: {
    'sms-consent-block': path.resolve(__dirname, 'assets/js/sms-consent/index.jsx'),
    'sms-consent-block-frontend': path.resolve(__dirname, 'assets/js/sms-consent/frontend.js'),
    'email-consent-block': path.resolve(__dirname, 'assets/js/email-consent/index.jsx'),
    'email-consent-block-frontend': path.resolve(__dirname, 'assets/js/email-consent/frontend.js'),
  },
  output: {
    filename: (pathData) => {
      const chunkName = pathData.chunk.name;
      const folder = outputFolderMap[chunkName];
      return `${folder}/${chunkName}.js`;
    },
    path: path.resolve(__dirname, 'assets/dist/js'),
  },
  module: {
    ...wpScriptConfig.module,
    rules: [
      ...wpScriptConfig.module.rules,
      {
        test: /\.(t|j)sx?$/,
        exclude: /node_modules/,
        use: {
          loader: 'babel-loader?cacheDirectory',
          options: {
            presets: ['@wordpress/babel-preset-default'],
          },
        },
      },
    ],
  },
  resolve: {
    extensions: ['.js', '.jsx'],
  },
  plugins: [
    ...wpScriptConfig.plugins.filter(
      (plugin) =>
        plugin.constructor.pluginName === 'mini-css-extract-plugin' ||
        plugin.constructor.name === 'CleanWebpackPlugin',
    ),
    new DependencyExtractionWebpackPlugin({
      injectPolyfill: true,
      requestToExternal,
      requestToHandle,
    }),
    new CopyWebpackPlugin({
      patterns: [
        {
          from: path.resolve(__dirname, 'assets/js/sms-consent/block.json'),
          to: path.resolve(__dirname, 'assets/dist/js/sms_consent/block.json'),
        },
        {
          from: path.resolve(__dirname, 'assets/js/email-consent/block.json'),
          to: path.resolve(__dirname, 'assets/dist/js/email_consent/block.json'),
        },
      ],
    }),
  ],
};
