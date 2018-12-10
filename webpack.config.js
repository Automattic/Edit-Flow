var ExtractText = require('extract-text-webpack-plugin');
var debug = process.env.NODE_ENV !== 'production';
var glob = require("glob");

const entries = glob.sync("./blocks/src/**/block.js").reduce((acc, item) => {
  const name = item.replace( /blocks\/src\/(.*)\/block.js/, '$1' )
  acc[ name ] = item;
  return acc;
}, {});

// @todo
var extractEditorSCSS = new ExtractText({
  filename: './[name].editor.build.css'
});

var extractBlockSCSS = new ExtractText({
  filename: './[name].style.build.css'
});

var plugins = [extractEditorSCSS, extractBlockSCSS];

var scssConfig = {
  use: [
    {
      loader: 'css-loader'
    },
    {
      loader: 'sass-loader',
      options: {
        outputStyle: 'compressed'
      }
    }
  ]
};

module.exports = {
  context: __dirname,
  devtool: debug ? 'sourcemap' : null,
  mode: debug ? 'development' : 'production',
  // entry: './blocks/src/blocks.js',
  entry: entries,
  output: {
    path: __dirname + '/blocks/dist/',
    filename: "[name].build.js"
  },
  externals: {
    'react': "React",
    "react-dom": "ReactDOM"
  },
  module: {
    rules: [
      {
        test: /\.js$/,
        exclude: /node_modules/,
        use: [
          {
            loader: 'babel-loader'
          }
        ]
      },
      {
        test: /editor\.scss$/,
        exclude: /node_modules/,
        use: extractEditorSCSS.extract(scssConfig)
      },
      {
        test: /style\.scss$/,
        exclude: /node_modules/,
        use: extractBlockSCSS.extract(scssConfig)
      }
    ]
  },
  plugins: plugins
};