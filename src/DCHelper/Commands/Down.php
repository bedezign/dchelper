<?php

namespace DCHelper\Commands;

class Down extends DockerCompose
{
    public function run(...$arguments)
    {
        parent::run('down');
    }
}