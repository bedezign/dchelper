<?php

namespace DCHelper\Commands;

use DCHelper\Tools\External\DockerCompose;

class Start implements Command
{
    public function shouldRun(...$arguments): bool
    {
        return true;
    }

    public function run(...$arguments): bool
    {
        $arguments = assembleArguments('start');
        ($command = new DockerCompose())->passthru()->run('start', $arguments);
        return $command->exit === 0;
    }
}