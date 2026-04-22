const path = require('path');
const MiniCssExtractPlugin = require('mini-css-extract-plugin');

module.exports = {
  entry: {
    app: './megaservice/assets/scss/app.scss',
    theme: './megaservice/assets/js/app.js'
  },
  output: {
    path: path.resolve(__dirname, 'megaservice/assets/dist'),
    filename: (pathData) => {
      // PS core cherche theme.js dans assets/js/ (chemin hardcodé)
      return pathData.chunk.name === 'theme' ? '../js/theme.js' : '[name].js';
    }
  },
  module: {
    rules: [
      {
        test: /\.scss$/,
        use: [
          MiniCssExtractPlugin.loader,
          { loader: 'css-loader', options: { url: false } },
          'sass-loader'
        ]
      },
      {
        test: /\.js$/,
        exclude: /node_modules/,
        use: ['babel-loader']
      }
    ]
  },
  plugins: [
    new MiniCssExtractPlugin({
      filename: '[name].css'
    })
  ]
};
