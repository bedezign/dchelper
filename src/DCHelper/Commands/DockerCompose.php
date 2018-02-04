<?php

namespace DCHelper\Commands;

use DCHelper\Tools\External\DockerCompose as DockerComposeRunner;

class DockerCompose extends Command
{
    public function help(): array
    {
        return [
            'global-options' => [
                'compose' => [
                    'value' => 'COMPOSE-BINARY',
                    'description' => 'Location of the docker-compose binary. (default: "' . di('docker-compose') . '")'
                ]
            ],
            'environment' => [
                'COMPOSE_BINARY' => '(Alternative to --compose) Set the original docker-compose binary location.'
            ]
        ];
    }

    public function run(...$arguments)
    {
        $argument = array_shift($arguments);
        (new DockerComposeRunner())->passthru()->run($argument, assembleArguments($argument));
    }

}