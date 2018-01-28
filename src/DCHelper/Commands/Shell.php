<?php

namespace DCHelper\Commands;

use DCHelper\Configurations\Yaml;
use DCHelper\Tools\External\Exec;

class Shell extends Command
{
    public function run(...$arguments): bool
    {
        di('env');

        $container = $this->getContainer();
        $command = array_get(di('arguments'), 'shell-cmd', ($env = getenv('COMPOSE_SHELL_CMD')) ? $env : 'bash -l');
        $title = $this->getTitle($container);
        $titleCMD = '';
        if ($title) {
            $titleCMD = 'echo "\033]0;' . $title . '\007" && ';
        }

        // Since we need to trigger docker itself for this, we need the full container name
        foreach(di('running-containers')->get() as $runningContainer) {
            $name = array_get($runningContainer, 'name');
            if (strpos($name, "_{$container}_") !== false) {
                // This is the one, trigger the command in it, make sure to allow for a TTY etc, would be pretty useless otherwise
                (new Exec())->passthru()->tty()->run($titleCMD . di('docker') . ' exec -ti ' . $name . ' ' . $command);
                return true;
            }
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
    private function getContainer(): string
    {
        $arguments = di('arguments');

        // Actually specified
        if ($container = array_get($arguments, 1)) {
            return $container;
        }

        // Secondly, see if the environment specifies a container
        di('env');
        if ($container = getenv('COMPOSE_SHELL_DEFAULT')) {
            return $container;
        }

        // 3rd: investigate the configuration for one that has a "SHELL_DEFAULT" parameter
        foreach (di('compose-config')->get('services') as $name => $service) {
            if (array_get($service, 'environment.SHELL_DEFAULT')) {
                return $name;
            }
        }

        // Last but least "pleasant" to do is to take the first specified service.
        // We can't use the compose-config for that, because docker-compose changes the service order in how the containers have to be started,
        // which is not always how they were defined. So we have to get the raw YAML version.
        $file   = array_get($arguments, 'file', array_get($arguments, 'f', 'docker-compose.yml'));
        $config = new Yaml('file://' . $file);

        $services = $config->get('services');
        return key($services);
    }

    private function getTitle($container)
    {
        if ($title = di('compose-config')->get('services.' . $container . 'environment.SHELL_TITLE')) {
            // Title specified in service environment
            return $title;
        }

        // Global format specified?
        di('env');
        if ($format = getenv('COMPOSE_SHELL_TITLE')) {
            return str_replace('{CONTAINER}', $container, $format);
        }

        // Nothing, don't set a title
        return null;
    }
}