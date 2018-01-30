<?php

namespace DCHelper\Commands;

use DCHelper\Tools\External\Exec;

class Shell extends Command
{
    public function run(...$arguments): bool
    {
        $service = $this->getContainer();
        $command = array_get(di('arguments'), 'shell-cmd', ($env = dcgetenv('COMPOSE_SHELL_CMD')) ? $env : 'bash -l');
        $title = $this->getTitle($service);
        $titleCMD = '';
        if ($title) {
            $titleCMD = 'echo "\033]0;' . $title . '\007" && ';
        }

        $container = containerFromService($service);
        if ($container) {
            (new Exec())->passthru()->tty()->run($titleCMD . di('docker') . ' exec -ti ' . $container . ' ' . $command);
        }

        return false;
    }

    public function help(): array
    {
        return [
            'command'        => 'shell',
            'description'    => 'Trigger a shell in the given container using "docker exec -it". Without container it will use the default or (if no default set) the first one in the config.',
            'usage'          => '[--shell-cmd=COMMAND] shell [CONTAINER]',
            'global-options' => [
                'shell-cmd' => [
                    'value'       => 'COMMAND',
                    'description' => 'Execute the given command instead of "bash -".',
                ],
            ],
            'environment'    => [
                'COMPOSE_SHELL_DEFAULT' => 'Name of the container to shell into when nothing was specified',
                'COMPOSE_SHELL_TITLE'   => '(supporting terminals only) Template for window tab-title. {CONTAINER} will be replaced',
                'COMPOSE_SHELL_CMD'     => 'Like the --shel-cmd argument, but can be set in the environment',
                'SHELL_DEFAULT=1'       => '(service) The default container if none specified.',
                'SHELL_TITLE'           => '(service) (supporting terminals only) Set this as the window tab-title.'
            ]
        ];
    }

    /**
     * Determines the name of the service we should "shell" into
     * @return string
     */
    private function getService(): string
    {
        $arguments = di('arguments');

        // Actually specified
        if ($container = array_get($arguments, 1)) {
            return $container;
        }

        // Secondly, see if the environment specifies a container
        if ($container = dcgetenv('COMPOSE_SHELL_DEFAULT')) {
            return $container;
        }

        // 3rd: investigate the configuration for one that has a "SHELL_DEFAULT" parameter
        foreach (di('compose-config')->get('services') as $name => $service) {
            if (array_get($service, 'environment.SHELL_DEFAULT')) {
                return $name;
            }
        }

        $services = di('compose-config-raw')->get('services');
        return key($services);
    }

    private function getTitle($container)
    {
        if ($title = di('compose-config')->get('services.' . $container . 'environment.SHELL_TITLE')) {
            // Title specified in service environment
            return $title;
        }

        // Global format specified?
        if ($format = dcgetenv('COMPOSE_SHELL_TITLE')) {
            return str_replace('{CONTAINER}', $container, $format);
        }

        // Nothing, don't set a title
        return null;
    }
}