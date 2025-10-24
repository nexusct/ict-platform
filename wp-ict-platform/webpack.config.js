const path = require('path');
const MiniCssExtractPlugin = require('mini-css-extract-plugin');
const { CleanWebpackPlugin } = require('clean-webpack-plugin');
const WorkboxPlugin = require('workbox-webpack-plugin');

module.exports = (env, argv) => {
  const isProduction = argv.mode === 'production';

  return {
    entry: {
      admin: './src/admin/index.tsx',
      public: './src/public/index.tsx',
      'time-tracker': './src/apps/time-tracker/index.tsx',
      'project-dashboard': './src/apps/project-dashboard/index.tsx',
      'inventory-manager': './src/apps/inventory-manager/index.tsx',
    },
    output: {
      path: path.resolve(__dirname, 'assets/js/dist'),
      filename: '[name].bundle.js',
      chunkFilename: '[name].chunk.js',
      publicPath: '',
    },
    module: {
      rules: [
        {
          test: /\.(ts|tsx)$/,
          exclude: /node_modules/,
          use: {
            loader: 'ts-loader',
          },
        },
        {
          test: /\.(js|jsx)$/,
          exclude: /node_modules/,
          use: {
            loader: 'babel-loader',
            options: {
              presets: [
                '@babel/preset-env',
                '@babel/preset-react',
                '@babel/preset-typescript',
              ],
            },
          },
        },
        {
          test: /\.(css|scss)$/,
          use: [
            isProduction ? MiniCssExtractPlugin.loader : 'style-loader',
            'css-loader',
            'sass-loader',
          ],
        },
        {
          test: /\.(png|jpg|gif|svg)$/,
          type: 'asset/resource',
          generator: {
            filename: '../images/[name][ext]',
          },
        },
      ],
    },
    resolve: {
      extensions: ['.ts', '.tsx', '.js', '.jsx', '.json'],
      alias: {
        '@': path.resolve(__dirname, 'src'),
        '@components': path.resolve(__dirname, 'src/components'),
        '@hooks': path.resolve(__dirname, 'src/hooks'),
        '@services': path.resolve(__dirname, 'src/services'),
        '@utils': path.resolve(__dirname, 'src/utils'),
        '@types': path.resolve(__dirname, 'src/types'),
      },
    },
    plugins: [
      new CleanWebpackPlugin(),
      new MiniCssExtractPlugin({
        filename: '../css/[name].css',
      }),
      ...(isProduction
        ? [
            new WorkboxPlugin.GenerateSW({
              clientsClaim: true,
              skipWaiting: true,
              runtimeCaching: [
                {
                  urlPattern: /^https:\/\/fonts\.googleapis\.com/,
                  handler: 'CacheFirst',
                  options: {
                    cacheName: 'google-fonts',
                    expiration: {
                      maxEntries: 20,
                      maxAgeSeconds: 60 * 60 * 24 * 365, // 1 year
                    },
                  },
                },
                {
                  urlPattern: /\.(?:png|jpg|jpeg|svg|gif)$/,
                  handler: 'CacheFirst',
                  options: {
                    cacheName: 'images',
                    expiration: {
                      maxEntries: 50,
                      maxAgeSeconds: 60 * 60 * 24 * 30, // 30 days
                    },
                  },
                },
                {
                  urlPattern: /\/wp-json\/ict\/v1\//,
                  handler: 'NetworkFirst',
                  options: {
                    cacheName: 'api-cache',
                    networkTimeoutSeconds: 10,
                    expiration: {
                      maxEntries: 100,
                      maxAgeSeconds: 60 * 5, // 5 minutes
                    },
                  },
                },
              ],
            }),
          ]
        : []),
    ],
    optimization: {
      splitChunks: {
        chunks: 'all',
        cacheGroups: {
          vendor: {
            test: /[\\/]node_modules[\\/]/,
            name: 'vendors',
            priority: 10,
          },
          common: {
            minChunks: 2,
            priority: 5,
            reuseExistingChunk: true,
          },
        },
      },
    },
    devtool: isProduction ? 'source-map' : 'eval-source-map',
    performance: {
      hints: isProduction ? 'warning' : false,
      maxEntrypointSize: 512000,
      maxAssetSize: 512000,
    },
  };
};
