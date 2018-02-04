<?php

namespace DCHelper\Commands;

class Start extends DockerCompose
{
    public function run(...$arguments)
    {
        parent::run('start');
    }
}