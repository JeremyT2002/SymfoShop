import { defineConfig, devices } from '@playwright/test';

const port = process.env.E2E_PORT ?? '8123';

export default defineConfig({
    testDir: './tests',
    // One shop, one database: tests run one after another
    workers: 1,
    fullyParallel: false,
    forbidOnly: !!process.env.CI,
    retries: process.env.CI ? 1 : 0,
    reporter: process.env.CI ? [['list'], ['html', { open: 'never' }]] : 'list',
    use: {
        baseURL: `http://127.0.0.1:${port}`,
        locale: 'en-US',
        trace: 'retain-on-failure',
        screenshot: 'only-on-failure',
    },
    projects: [
        { name: 'desktop', use: { ...devices['Desktop Chrome'] }, testIgnore: /mobile\.spec\.ts/ },
        { name: 'mobile', use: { ...devices['Pixel 7'] }, testMatch: /mobile\.spec\.ts/ },
    ],
    webServer: {
        command: 'node serve.mjs',
        url: `http://127.0.0.1:${port}/`,
        timeout: 180_000,
        reuseExistingServer: !process.env.CI,
        stdout: 'ignore',
        stderr: 'pipe',
    },
});
