<?php

namespace DCHelper\Commands;

use DCHelper\Tools\External\Exec;

class Hosts extends Command
{
    public function shouldRun(...$arguments): bool
    {
        return true;
    }

    public function run(...$arguments)
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
                $parts = preg_split('/[\s#]+/', $line, -1, PREG_SPLIT_NO_EMPTY);
                if ($parts[0] === $ip && strtolower(array_get($parts, 1, '')) === $lcHostname) {
                    $found = true;
                    break;
                }
            }

            if (!$found) {
                $hostFile = di('hosts');
                info('Updating "' . $hostFile . '"...');
                $hosts[] = $ip . "\t" . $hostname . PHP_EOL;
                // Since we need sudo to be able to overwrite the hosts file, and PHP can't do that, abuse "the power of tee!"
                // @TODO perhaps we should create an alternate file first, verify and then replace the original?
                (new Exec())->run(di('sudo') . " tee '$hostFile' <<'EOF'" . PHP_EOL . implode('', $hosts) . PHP_EOL . 'EOF' . PHP_EOL);
            }
        }
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