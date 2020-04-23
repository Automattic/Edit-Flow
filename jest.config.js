module.exports = {
    testMatch: ["<rootDir>/tests/jest/**/**.test.js"], // finds test
    moduleNameMapper: {
        "^.+\\.(css|less|scss)$": "babel-jest"
    },
    preset: '@wordpress/jest-preset-default',
};