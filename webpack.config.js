const defaultConfig = require('@wordpress/scripts/config/webpack.config');
const path = require('path');

module.exports = {
    ...defaultConfig,
    entry: {
        'block-checkout-integration': './assets/src/js/block-checkout-integration.js'
    },
    output: {
        path: path.resolve(__dirname, 'assets/build/js'),
        filename: '[name].js'
    },
    resolve: {
        extensions: ['.js', '.jsx', '.json']
    },
    externals: {
        ...defaultConfig.externals,
        '@wordpress/element': 'wp.element',
        '@wordpress/components': 'wp.components',
        '@wordpress/i18n': 'wp.i18n',
        '@wordpress/data': 'wp.data',
        '@woocommerce/settings': ['wc', 'settings'],
        '@woocommerce/blocks-checkout': ['wc', 'blocksCheckout'],
        '@woocommerce/block-data': ['wc', 'wcBlocksData']
    },
    module: {
        ...defaultConfig.module,
        rules: [
            {
                test: /\.(js|jsx)$/,
                exclude: /node_modules/,
                use: {
                    loader: 'babel-loader',
                    options: {
                        presets: ['@wordpress/babel-preset-default']
                    }
                }
            }
        ]
    }
}; 