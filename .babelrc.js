module.exports = {
  presets: [
    "@babel/preset-env"
  ],
  plugins: [
    '@wordpress/babel-plugin-import-jsx-pragma',
    '@babel/plugin-transform-react-jsx',
  ]
}