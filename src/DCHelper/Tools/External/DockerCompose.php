<?php

namespace DCHelper\Tools\External;

class DockerCompose extends Command
{
    public function run(...$arguments): string
    {
        // Keep global arguments
        array_unshift($arguments, assembleArguments('global'));
        // Docker-compose binary
        array_unshift($arguments, di('docker-compose'));
        return $this->_execute(implode(' ', $arguments));
    }
}