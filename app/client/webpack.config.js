const MiniCssExtractPlugin = require('mini-css-extract-plugin')
const OptimizeCSSAssetsPlugin = require('optimize-css-assets-webpack-plugin')
const postcssWillChange = require('postcss-will-change')
const postcssAssets = require('postcss-assets')
const path = require('path')
const TerserPlugin = require('terser-webpack-plugin')

// PostCSS
const autoprefixerPlugin = require('autoprefixer')({
  grid: 'autoplace'
})
const fix100vh = require('postcss-100vh-fix')()
const rfs = require('rfs')({
  twoDimensional: false,
  baseValue: 16,
  unit: 'rem',
  breakpoint: 1230, // xl + gap
  breakpointUnit: 'px',
  factor: 10,
  class: false,
  unitPrecision: 6,
  safariIframeResizeBugFix: false,
  remValue: 16,
})
const moveProps = require('postcss-move-props-to-bg-image-query')({
  match: '-svg-*',
  transform: ({ name, value }) => ({
    name: name.replace(/^-svg-/, ''),
    value,
  }),
})
const postcssFlexbugsFixes = require('postcss-flexbugs-fixes')()
const postcssPresetEnv = require('postcss-preset-env')({
  autoprefixer: false,
})
const postcssSorting = require('postcss-sorting')({
  'properties-order': 'alphabetical',
})
const cssnano = require('cssnano')({
  preset: 'default',
})
const sortMediaQueries = require('postcss-sort-media-queries')({
  sort: 'mobile-first',
})
const combineMediaQuery = require('postcss-combine-media-query')()
const at2x = require('postcss-at2x')()

module.exports = {
  cache: true,
  devtool: 'source-map',
  entry: {
    bundle: ['./main.js', './main.sass'],
  },
  output: {
    path: path.resolve(__dirname, 'env/var'),
    filename: '[name].js',
    pathinfo: false
  },
  resolve: {
    mainFiles: ['index'],
    alias: {
      app: path.resolve(__dirname, '.'),
      component: path.resolve(__dirname, './component'),
      lib: path.resolve(__dirname, './lib'),
      asset: path.resolve(__dirname, './asset'),
    }
  },
  module: {
    rules: [
       {
        test: /\.m?js$/,
        exclude: /node_modules/,
        use: [
          {
            loader: 'babel-loader'
          }
        ]
      },
      {
        test: /\.jext/,
        use: [
          {
            loader: 'jext-loader'
          }
        ]
      },
      {
        test: /\.svg(\?.*)?$/, // match img.svg and img.svg?param=value
        use: [
          'svg-url-loader?iesafe', // or file-loader or svg-url-loader
          'svg-transform-loader'
        ]
      },
      {
        test: /\.(png|jpe?g|gif)$/,
        use: [
          {
            loader: 'url-loader?limit=4096&name=/img/[name].[ext]'
          }
        ],
      },
      {
        test: /\.(eot|ttf|woff|woff2)$/,
        use: [
          {
            loader: 'file-loader?name=/font/[name].[ext]'
          }
        ]
      },
      {
        test: /\.(sa|sc|c)ss$/,
        use: [
          MiniCssExtractPlugin.loader,
          {
            loader: 'css-loader',
            options: {
              importLoaders: 2,
              esModule: false
            }
          },
          {
            loader: 'postcss-loader',
            options: {
              postcssOptions: {
                plugins: [
                  at2x,
                  rfs,
                  postcssAssets({basePath: './asset/img'}),
                  postcssFlexbugsFixes,
                  moveProps,
                  postcssSorting,
                  postcssPresetEnv,
                  postcssWillChange(),
                  combineMediaQuery,
                  sortMediaQueries,
                  // fix100vh(),
                  autoprefixerPlugin,
                  cssnano
                ].filter(Boolean)
              }
            }
          }, {
            loader: 'sass-loader'
          }
        ]
      }
    ]
  },
  plugins: [
    new MiniCssExtractPlugin({
      filename: '[name].css',
			chunkFilename: '[id].css'
    })
  ],

  optimization: {
    minimizer: [
      new TerserPlugin({
        parallel: true
      }),
      new OptimizeCSSAssetsPlugin({}),
      '...'
    ]
  },
  watchOptions: {
    poll: 1000,
    aggregateTimeout: 500
  }
}
