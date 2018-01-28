<?php

namespace DCHelper\Commands;

/**
 * Class RemoteIPs
 * @package DCHelper\Commands
 *
 * Determines any remote-ips used and if needed, maps an aliased IP for it
 * If you wish to use this functionality, you have to make sure that your port mappings in the config file include IPs
 * COMPOSE_ALIAS_IP in the .env file (or the environment) is also supported, but pretty useless if you aren't using it in your config.
 */
class RemoteIPs extends Command
{
    public function run(...$arguments): bool
    {
        $remoteIPs = [];

        // the format for a config specified port is [[remote_ip:]remote_port[-remote_port]:]port[/protocol]
        info('Checking configuration for remote IPs...');
        foreach (di('compose-config')->get('services') as $name => $config) {
            $ports = array_get($config, 'ports', []);
            foreach ($ports as $port) {
                // null is an exposed but not bound port, 0.0.0.0 is a port bound on localhost
                if (!in_array($ip = array_get($port, 'remote_ip'), [null, '0.0.0.0'])) {
                    $remoteIPs[] = $ip;
                }
            }
        }

        // Since this is an IP alias what the system is concerned, use this as a name for the global as well
        di('env');
        $remoteIPs[] = getenv('COMPOSE_ALIAS_IP');
        $remoteIPs = array_unique(array_filter($remoteIPs));
        if (count($remoteIPs)) {
            debug('Found: ' . implode(', ', $remoteIPs));
        }

        return (new AliasIP())->run(...$remoteIPs);
    }

    public function help(): array
    {
        return [
            'environment' => [
                'COMPOSE_ALIAS_IP'   => 'Shortcut .env setting to use in your services\' port mapping if you want to specify the remote_ip. Also used for the hostname.'
            ]
        ];
    }


}