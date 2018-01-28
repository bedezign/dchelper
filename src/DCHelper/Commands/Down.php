<?php

namespace DCHelper\Commands;

class Down extends DockerCompose
{
    public function run(...$arguments): bool
    {
        return parent::run('down');
    }
}