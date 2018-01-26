<?php

namespace DCHelper\Commands;

use DCHelper\Tools\External\Exec;

/**
 * Class DeProxy
 * @package DCHelper\Commands
 *
 * Detects any proxied (socat) TCP port for this project from before and (attempts to) terminate(s) the process
 */
class DeProxy implements Command
{
    public function shouldRun(...$arguments): bool
    {
        return true;
    }

    public function run(...$arguments): bool
    {
        $proxiedIPs = $this->proxiedIPs();
        if (!count($proxiedIPs)) {
            // No proxiedIPs defined, nothing to do
            return true;
        }

        $processes = [];
        $relevant = 0;
        info('Looking for tcp proxy processes that need cleaning up...');
        // Fetch all socat processes so that we can filter "ours"
        $socat = explode(PHP_EOL, trim((new Exec())->run('`which pgrep` -fl socat')));
        foreach ($socat as $process) {
            // Each of these has 2 processes, the sudo one and the actual socat, take the sudo ones that match our proxiedIPs
            if (preg_match("/bind=([^\s]+)/", $process, $bind)) {
                if (in_array($bind[1], $proxiedIPs)) {
                    // It's ours, keep the process ID
                    $parts = explode(' ', $process, 2);
                    $processes[] = reset($parts);

                    if (strpos($process, 'sudo') !== false) {
                        // Just count the ones that have sudo, for logging purposes
                        $relevant ++;
                    }
                }
            }
        }

        if (count($processes)) {
            info("$relevant processes found, killing.");
            (new Exec())->run(di('sudo') . ' kill -9 ' . implode(' ', $processes));
        }
        else {
            debug('Nothing found...');
        }

        return true;
    }

    /**
     * Parses both the environment and the docker config for proxied IP definitions
     * Globally DOCKER_PROXY_IP can be used, per service PROXY_IP is supported in the environment section
     * @return array
     */
    protected function proxiedIPs(): array
    {
        di('env');
        $proxied = [getenv('DOCKER_PROXY_IP')];

        foreach (di('compose-config')->get('services') as $name => $config) {
            $proxied[] = array_get($config, 'environment.PROXY_IP');
        }

        return array_unique(array_filter($proxied));
    }
}