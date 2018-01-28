<?php

namespace DCHelper\Commands;

use DCHelper\Tools\External\Exec;

class Hosts extends Command
{
    public function shouldRun(...$arguments): bool
    {
        return true;
    }

    public function run(...$arguments): bool
    {
        di('env');

        // Global defined hostname with
        $hostname = getenv('COMPOSE_HOSTNAME');
        $ip       = getenv('COMPOSE_ALIAS_IP') ?? getenv('COMPOSE_PROXY_IP');

        if ($hostname && $ip) {
            $hosts = $this->getHosts();
            $lcHostname = strtolower($hostname);
            $found = false;
            foreach ($hosts as $line) {
                $trimmed = ltrim($line);
                if (substr($trimmed, 0, strlen($ip)) === $ip
                    && strtolower(substr(trim(substr($trimmed, strlen($ip))), 0, strlen($lcHostname))) === $lcHostname) {
                    $found = true;
                    break;
                }
            }
            if (!$found) {
                $hosts[] = $ip . "\t" . $hostname . PHP_EOL;
                // Since we need sudo to be able to overwrite the hosts file, and PHP can't do that, abuse "the power of cat!"
                // @TODO perhaps we should create an alternate file first, verify and then replace the original?
                (new Exec())->run(di('sudo') . " cat << 'EOF' > ". di('hosts') . PHP_EOL . implode('', $hosts) . PHP_EOL . 'EOF' . PHP_EOL);
            }
        }

        return true;
    }

    public function help(): array
    {
        return [
            'command'     => 'hosts',
            'description' => '(Automatically ran as part of "up") Makes sure the hosts-file contains entries for the current project',
            'environment' => [
                'COMPOSE_HOSTNAME' => 'The hostname to register in your hosts file for this project. Will be combined with COMPOSE_ALIAS_IP or COMPOSE_PROXY_IP'
            ]
        ];
    }


    private function getHosts()
    {
        return file(di('hosts'));
    }
}