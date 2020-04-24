module.exports = {
	...require( '@wordpress/scripts/config/jest-e2e.config' ),
	setupFilesAfterEnv: [
		'<rootDir>/config/setup-test-framework.js',
		/**
		 * There are existing console warnings that Edit Flow causing
		 * that need to be resolved before this can be turned back on.
		 * In the meantime, we'll configure this ourselves 
		 * and ignore warnings that are thrown for the time being
		 * 
		 * ex: "[DOM] Found 2 elements with non-unique id #_wpnonce: (More info: https://goo.gl/9p2vKq) %o %o"],["[DOM] Found 2 elements with non-unique id #_wpnonce: (More info: https://goo.gl/9p2vKq) %o %o"
		 */
		// '@wordpress/jest-console',
		'@wordpress/jest-puppeteer-axe',
		'expect-puppeteer',
	],
	testPathIgnorePatterns: [],
};