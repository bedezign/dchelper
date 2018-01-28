<?php

namespace DCHelper\Commands;

class Up extends DockerCompose
{
    public function run(...$arguments): bool
    {
        $arguments = assembleArguments('up');
        if (strpos($arguments, '-d ') === false) {
            warning('Please note that, since you have not specified the "detach" argument (-d), it is impossible set up any proxied-ips (this only works with running containers)');
            set_time_limit(0);
        }

        return parent::run('up');
    }
}