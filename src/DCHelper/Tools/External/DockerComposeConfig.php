<?php

namespace DCHelper\Tools\External;

class DockerComposeConfig extends DockerCompose
{
    public function run(...$arguments): string
    {
        return parent::run('config');
    }
}