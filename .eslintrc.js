require( '@automattic/eslint-plugin-wpvip/init' );

module.exports = {
	extends: [ 'plugin:@automattic/wpvip/recommended' ],
	root: true,
	env: {
		jest: true,
	},
	rules: {
		"no-prototype-builtins": 0,
		"no-eval": 0,
		"complexity": 0,
		"camelcase": 0,
		"no-undef": 0,
		"wpcalypso/import-docblock": 0,
		"valid-jsdoc": 0,
		"react/prop-types": 0,
		"react/react-in-jsx-scope": 0,
		"react-hooks/rules-of-hooks": 0,
		"no-redeclare": 0,
		"no-shadow": 0,
		"no-nested-ternary": 0,
		"no-var": 0,
		"no-unused-vars": 0,
		"no-useless-escape": 0,
		"prefer-const": 0,
		"no-global-assign": 0,
		"no-constant-binary-expression": 0,
		"valid-typeof": 0,
		"eqeqeq": 0,
		"radix": 0,
		"no-eq-null": 0,
		"array-callback-return": 0,
		"no-unused-expressions": 0,
		"no-alert": 0,
		"no-lonely-if": 0,
	}
};
