<?php

namespace DCHelper\Commands;

use DCHelper\Tools\External\DockerCompose;

class Down implements Command
{
    public function shouldRun(...$arguments): bool
    {
        return true;
    }

    public function run(...$arguments): bool
    {
        ($command = new DockerCompose())->passthru()->run('down', assembleArguments('down'));
        return $command->exit === 0;
    }
}