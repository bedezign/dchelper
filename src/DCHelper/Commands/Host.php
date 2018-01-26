<?php

namespace DCHelper\Commands;

class Host implements Command
{
    public function shouldRun(...$arguments): bool
    {
        di('env');
        return getenv('DOCKER_HOSTNAME') !== false;
    }

    public function run(...$arguments): bool
    {
        $aliasedIPs = di('aliased_ips');
        $hostname = getenv('DOCKER_HOSTNAME');

        if (!count($aliasedIPs) || !$hostname) {
            return true;
        }

        $hosts = $this->getHosts();
        return true;
    }

    private function getHosts()
    {
        return file('/etc/hosts');
    }
}