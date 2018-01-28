<?php

namespace DCHelper\Configurations;

use DCHelper\Tools\External\DockerComposeConfig;

/**
 * Class DockerCompose
 * @package DCHelper\Configurations
 *
 * Represents a "docker-compose config" (aka fully parsed) configuration
 */
class DockerCompose extends Yaml
{
    public function __construct($source = null)
    {
        parent::__construct(function() use ($source) {
            $configuration = $this->loadFromText((new DockerComposeConfig())->mustRun()->run());

            // Parse port statements
            foreach (array_get($configuration, 'services', []) as $name => $container) {
                $configuration['services'][$name]['name'] = $name;

                $ports          = [];
                $specifiedPorts = array_get($container, 'ports', []);
                foreach ($specifiedPorts as $port) {
                    $port                 = $this->parsePortBinding($port);
                    $ports[$port['name']] = $port;
                }
                $configuration['services'][$name]['ports'] = $ports;
            }

            return $configuration;
        });
    }

    private function parsePortBinding($bind)
    {
        $remoteIp = $remotePorts = $protocol = $containerPort = null;
        if (\is_array($bind)) {
            $remoteIp      = array_get($bind, 'external_ip');
            $remotePorts   = array_get($bind, 'published');
            $containerPort = array_get($bind, 'target');
            $protocol = array_get($bind, 'protocol', 'tcp');
        } else {
            $lastColon = strrpos($bind, ':');
            if ($lastColon !== false) {
                // Remote and container part specified
                $remote = explode(':', substr($bind, 0, $lastColon));
                if (count($remote) === 2) {
                    $remoteIp    = reset($remote);
                    $remotePorts = end($remote);
                } else {
                    $remotePorts = reset($remote);
                }

                $bind = substr($bind, $lastColon + 1);
            }

            list($containerPort, $protocol) = explode('/', $bind);
        }

        return [
            'name'        => $containerPort . '/' . $protocol,
            'protocol'    => $protocol,
            'port'        => $containerPort,
            'remote_ip'   => $remoteIp,
            'remote_port' => $remotePorts,
        ];
    }
}