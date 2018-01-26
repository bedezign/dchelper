<?php

namespace DCHelper\Commands;

use DCHelper\Tools\External\Exec;

class Proxy extends DeProxy
{
    public function run(...$arguments): bool
    {
        di('env');

        $globalIP = getenv('DOCKER_PROXY_IP');

        info('Setting up tcp proxies');
        foreach (di('running-containers') as $name => $configuration) {
            // Fetch the PROXY_IP for the container
            $proxyIP = array_get($configuration, 'environment.PROXY_IP', $globalIP);

            foreach (array_get($configuration, 'ports', []) as $port) {
                // We only support proxy'ing for ports "bound to the docker machine"
                if ($port['remote_ip'] === '0.0.0.0') {

                    (new AliasIP())->run($proxyIP);

                    debug("- $proxyIP:{$port['port']} => 127.0.0.1:{$port['remote_port']}");
                    // Note to self: reuseaddr allows a new bind to the socket even if parts of it were in use already, allowing for a new start.
                    // Even while the connection to the previous "version" might still be open
                    $arguments = " tcp4-listen:{$port['port']},reuseaddr,fork,bind=$proxyIP tcp4:127.0.0.1:{$port['remote_port']}";
                    (new Exec())->run(di('sudo') . ' ' . di('socat') . $arguments);

                }
            }
        }
        return true;
    }

    /**
     * Determines if there is a process that still has the connection to a previous socat tunnel open.
     * If a socat process is terminated while it had an active connection, it will leave the socket behind in a CLOSE_WAIT state
     * which only ends whenever the connecting application terminates the connection.
     * Until that happens we cannot start a new socat instance on the port, unless it was started with reuseaddr
     *
     * @param string $ip
     * @param int $port
     * @return bool
     */
    private function isPortFree($ip, $port): bool
    {
        $connections = array_filter(explode(PHP_EOL, trim((new Exec())->run('lsof -nPi tcp:' . $port))));
        foreach ($connections as $connection) {
            // java    26632 steve   31u  IPv4 0x6e8bf6435a26c0f3      0t0  TCP 172.99.0.5:57510->172.99.0.5:3306 (CLOSE_WAIT)
            if (!preg_match('/(.*?)\s+(\d+).*\->(.*):(.*) \((.*)\)/', $connection, $matches))
                continue;

            // If the port is still connected and points to one of our IPs or hosts, it is still in use
            list($connection, $process, $pid, $host, $targetPort, $status) = $matches;
            if ($status === 'CLOSE_WAIT' && $host === $ip) {
                return false;
            }
        }
        return true;
    }
}