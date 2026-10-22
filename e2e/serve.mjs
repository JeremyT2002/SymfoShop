// Starts the shop for the E2E tests on a fresh SQLite database with demo data:
//   var/e2e.db  ← migrations + doctrine fixtures (append mode keeps the migration-seeded shipping methods)
// then serves it with PHP's built-in server. Used as Playwright's webServer command.
import { execFileSync, spawn } from 'node:child_process';
import { rmSync } from 'node:fs';
import { dirname, join } from 'node:path';
import { fileURLToPath } from 'node:url';

const root = join(dirname(fileURLToPath(import.meta.url)), '..');
const port = process.env.E2E_PORT ?? '8123';
const php = process.env.PHP_BINARY ?? 'php';

const env = {
    ...process.env,
    APP_ENV: 'dev',
    APP_DEBUG: '1',
    DATABASE_URL: 'sqlite:///%kernel.project_dir%/var/e2e.db',
    DEFAULT_URI: `http://127.0.0.1:${port}`,
    MAILER_DSN: 'null://null',
    MESSENGER_TRANSPORT_DSN: 'sync://',
    PAYMENT_PROVIDER: 'dev',
    CHECKOUT_SKIP_PAYMENT: '0',
};

const console_ = (...args) => execFileSync(php, ['bin/console', ...args, '--no-interaction'], { cwd: root, env, stdio: 'inherit' });

rmSync(join(root, 'var', 'e2e.db'), { force: true });
console_('cache:clear');
console_('doctrine:migrations:migrate');
console_('doctrine:fixtures:load', '--append');

const server = spawn(php, ['-S', `127.0.0.1:${port}`, '-t', 'public', 'e2e/router.php'], { cwd: root, env, stdio: 'inherit' });
for (const signal of ['SIGINT', 'SIGTERM']) {
    process.on(signal, () => server.kill(signal));
}
server.on('exit', (code) => process.exit(code ?? 0));
