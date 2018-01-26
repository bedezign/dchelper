<?php

namespace DCHelper\Commands;

use DCHelper\Tools\External\DockerCompose;

class Up implements Command
{
    public function shouldRun(...$arguments): bool
    {
        return true;
    }

    public function run(...$arguments): bool
    {
        $arguments = assembleArguments('up');
        if (strpos($arguments, '-d ') === false) {
            warning('Please note that, since you have not specified the "detach" argument (-d), dchelper will not be able to set up any proxied-ips (since that can only happen afther the containers run)');
            set_time_limit(0);
        }

        ($command = new DockerCompose())->passthru()->run('up', $arguments);
        return $command->exit === 0;
    }
}