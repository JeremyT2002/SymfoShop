<?php

namespace App\Service\System;

use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

final class AppUpdateService
{
    public function __construct(
        private readonly KernelInterface $kernel,
        private readonly CacheInterface $cache,
    ) {
    }

    /**
     * @return array{
     *   ok: bool,
     *   updates_available: bool|null,
     *   behind_by: int|null,
     *   error: string|null
     * }
     */
    public function checkForUpdates(): array
    {
        return $this->cache->get('admin_app_update_check', function (ItemInterface $item): array {
            $item->expiresAfter(30);

            $fetch = $this->runCommand(['git', 'fetch', 'origin', 'dev'], 20);
            if ($fetch['exit_code'] !== 0) {
                return [
                    'ok' => false,
                    'updates_available' => null,
                    'behind_by' => null,
                    'error' => 'git fetch failed: ' . trim($fetch['stderr'] ?: $fetch['stdout']),
                ];
            }

            $behind = $this->runCommand(['git', 'rev-list', '--count', 'HEAD..origin/dev'], 10);
            if ($behind['exit_code'] !== 0) {
                return [
                    'ok' => false,
                    'updates_available' => null,
                    'behind_by' => null,
                    'error' => 'git rev-list failed: ' . trim($behind['stderr'] ?: $behind['stdout']),
                ];
            }

            $count = (int) trim($behind['stdout']);

            return [
                'ok' => true,
                'updates_available' => $count > 0,
                'behind_by' => $count,
                'error' => null,
            ];
        });
    }

    /**
     * Runs `make app-update` and returns its combined output.
     *
     * @return array{ok: bool, exit_code: int, output: string}
     */
    public function runUpdate(): array
    {
        $result = $this->runCommand(['make', 'app-update'], 600);

        // Invalidate cached "updates available" state after a run (success or fail).
        $this->cache->delete('admin_app_update_check');

        return [
            'ok' => $result['exit_code'] === 0,
            'exit_code' => $result['exit_code'],
            'output' => trim($result['stdout'] . "\n" . $result['stderr']),
        ];
    }

    /**
     * @param string[] $cmd
     * @return array{exit_code:int, stdout:string, stderr:string}
     */
    private function runCommand(array $cmd, int $timeoutSeconds): array
    {
        $projectDir = $this->kernel->getProjectDir();

        $descriptorspec = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];

        $process = @proc_open(
            $cmd,
            $descriptorspec,
            $pipes,
            $projectDir
        );

        if (!\is_resource($process)) {
            return [
                'exit_code' => 1,
                'stdout' => '',
                'stderr' => 'Failed to start process.',
            ];
        }

        fclose($pipes[0]);
        stream_set_blocking($pipes[1], false);
        stream_set_blocking($pipes[2], false);

        $stdout = '';
        $stderr = '';
        $start = time();

        while (true) {
            $status = proc_get_status($process);
            $stdout .= stream_get_contents($pipes[1]) ?: '';
            $stderr .= stream_get_contents($pipes[2]) ?: '';

            if (($status['running'] ?? false) === false) {
                break;
            }

            if (time() - $start > $timeoutSeconds) {
                proc_terminate($process);
                $stderr .= "\nTimed out after {$timeoutSeconds}s.";
                break;
            }

            usleep(50_000);
        }

        $stdout .= stream_get_contents($pipes[1]) ?: '';
        $stderr .= stream_get_contents($pipes[2]) ?: '';

        fclose($pipes[1]);
        fclose($pipes[2]);

        $exitCode = proc_close($process);

        return [
            'exit_code' => (int) $exitCode,
            'stdout' => $stdout,
            'stderr' => $stderr,
        ];
    }
}

