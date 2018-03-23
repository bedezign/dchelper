<?php

namespace DCHelper\Configurations;

/**
 * Class RunningContainers
 * @package DCHelper\Configuration
 *
 * Returns a configuration object of the running containers
 */
class RunningContainers extends Base
{
    public function __construct($source = null)
    {
        parent::__construct(function() {
            $output = (new \DCHelper\Tools\External\DockerCompose())->mustRun(true)->run('ps');
            // Skip the header lines
            $lines = array_slice(explode(PHP_EOL, trim($output)), 2);

            $containers = [];
            foreach ($lines as $container) {
                list($name, $command, $status, $ports) = array_pad(preg_split("/\s{3,}/", trim($container)), 4, null);
                if ($ports) {
                    $portTexts = explode(',', $ports);

                    $ports = [];
                    foreach ($portTexts as $port) {
                        $port                 = $this->parsePortBinding($port);
                        $ports[$port['name']] = $port;
                    }
                }
                else {
                    $ports = [];
                }

                $containers[$name] = [
                    'name'    => $name,
                    'command' => $command,
                    'status'  => $status,
                    'ports'   => $ports,
                ];
            }

            return $containers;
        });
    }

    private function parsePortBinding($bind)
    {
        if (!$bind) {
            return [];
        }

        // We can have either a remote + container specification or just a container specification
        // 9000/tcp, 0.0.0.0:32771->80/tcp, 0.0.0.0:32770->443/tcp
        $parts    = explode('->', trim($bind));
        $remoteIp = $remotePorts = null;
        if (count($parts) === 2) {
            $remote = explode(':', array_shift($parts));
            if (count($remote) === 2) {
                $remoteIp    = reset($remote);
                $remotePorts = end($remote);
            }
        }
        $container = reset($parts);

        list($containerPort, $protocol) = explode('/', $container);

        return [
            'name'        => $containerPort . '/' . $protocol,
            'protocol'    => $protocol,
            'port'        => $containerPort,    // This can also be a range, no alias support for a range
            'remote_ip'   => $remoteIp,         // either null (port was exposed in Dockerfile but not mapped, 0.0.0.0 = docker VM ip (we have to alias it) or an actual IP (we no touchie)
            'remote_port' => $remotePorts,
        ];
    }
}