// @ts-check
const { defineConfig, devices } = require('@playwright/test');

/**
 * @see https://playwright.dev/docs/test-configuration
 */
module.exports = defineConfig({
    testDir: '.',
    fullyParallel: false,
    forbidOnly: !!process.env.CI,
    retries: process.env.CI ? 2 : 0,
    workers: process.env.CI ? 1 : undefined,
    reporter: [
        ['html', { outputFolder: '../../tests/Browser/report' }],
        ['list'],
    ],
    use: {
        baseURL: process.env.BASE_URL || 'http://localhost:8082',
        trace: 'on-first-retry',
        screenshot: 'only-on-failure',
    },
    projects: [
        {
            name: 'chromium-desktop',
            use: {
                ...devices['Desktop Chrome'],
                viewport: { width: 1920, height: 1080 },
            },
        },
        {
            name: 'chromium-laptop',
            use: {
                ...devices['Desktop Chrome'],
                viewport: { width: 1366, height: 768 },
            },
        },
        {
            name: 'tablet',
            use: {
                ...devices['iPad Pro 11'],
                viewport: { width: 834, height: 1194 },
            },
        },
        {
            name: 'mobile-iphone',
            use: {
                ...devices['iPhone 14'],
                viewport: { width: 390, height: 844 },
            },
        },
        {
            name: 'mobile-pixel',
            use: {
                ...devices['Pixel 7'],
                viewport: { width: 412, height: 915 },
            },
        },
        {
            name: 'firefox',
            use: { ...devices['Desktop Firefox'] },
        },
        {
            name: 'webkit',
            use: { ...devices['Desktop Safari'] },
        },
    ],
});
