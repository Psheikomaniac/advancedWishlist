const { defineConfig } = require('cypress');

module.exports = defineConfig({
  e2e: {
    baseUrl: 'http://localhost:8000',
    specPattern: 'tests/E2E/specs/**/*.cy.{js,jsx,ts,tsx}',
    supportFile: 'tests/E2E/support/e2e.js',
    fixturesFolder: 'tests/E2E/fixtures',
    viewportWidth: 1920,
    viewportHeight: 1080,
    defaultCommandTimeout: 10000,
    requestTimeout: 10000,
    responseTimeout: 60000,
    video: false,
    screenshotOnRunFailure: true,
    screenshotsFolder: 'tests/E2E/screenshots',
    videosFolder: 'tests/E2E/videos',
    setupNodeEvents(on, config) {
      // implement node event listeners here
      return config;
    },
  },
});