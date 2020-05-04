module.exports = {
	...require( '@wordpress/scripts/config/jest-e2e.config' ),
	setupFilesAfterEnv: [
		'<rootDir>/config/setup-test-framework.js',
		/**
		 * Sometimes Edit Flow causes a console warning/error
		 * and sometimes core does, irrespective of the test being 
		 * run. So we're not going to enable `jest-console` for the time
		 * being.
		 * 
		 * ex: "[DOM] Found 2 elements with non-unique id #_wpnonce: (More info: https://goo.gl/9p2vKq) %o %o"],["[DOM] Found 2 elements with non-unique id #_wpnonce: (More info: https://goo.gl/9p2vKq) %o %o"
		 */
		// '@wordpress/jest-console',
		'@wordpress/jest-puppeteer-axe',
		'expect-puppeteer',
	],
	testPathIgnorePatterns: [],
};