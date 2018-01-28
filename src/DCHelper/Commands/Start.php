<?php

namespace DCHelper\Commands;

class Start extends DockerCompose
{
    public function run(...$arguments): bool
    {
        return parent::run('start');
    }
}