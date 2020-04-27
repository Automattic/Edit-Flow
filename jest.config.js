module.exports = {
    testMatch: ["<rootDir>/tests/jest/**/**.test.js"], // finds test
    moduleNameMapper: {
        "^.+\\.(css|less|scss)$": "babel-jest"
    },
    globals: {
        "EF_CALENDAR": {
            "WP_VERSION": 5.4
        }
    },
    preset: '@wordpress/jest-preset-default',
};