<?php

namespace DCHelper\Commands\Helpers;


use DCHelper\Tools\External\DockerCompose;
use DCHelper\Tools\External\Exec;

class ScriptRunner
{
    private $ran = [];

    public function run($configuration, $stage = null)
    {
        // By default we only run in post.up, unless otherwise specified
        $at = array_get($configuration, 'at', 'post.up');
        if ($stage && $stage !== $at) {
            // Nothing to do
            return true;
        }

        // Should scripts only be ran once?
        $once     = array_get($configuration, 'once', true);
        $lockFile = array_get($configuration, 'lock-file');
        if ($once && !$lockFile) {
            error('scriptrunner: To keep track of the scripts that already ran, please specify the "lock-file" option');
            return false;
        }

        $root = array_get($configuration, 'root', '');
        if ($root && substr($root, - 1) !== DIRECTORY_SEPARATOR) {
            $root .= DIRECTORY_SEPARATOR;
        }

        $service = array_get($configuration, 'service');
        if (!$service) {
            error('scriptrunner: Please specify a service to run against');
            return false;
        }

        $once = !is_array($once) ? [$once] : $once;
        foreach ($once as $script) {
            if (!$this->runScript($service, $script[0] !== DIRECTORY_SEPARATOR ? absolute_path($root . $script) : $script)) {
                return false;
            }
        }
        return true;
    }

    private function runScript($service, $script)
    {
        if (!is_readable($script)) {
            error('scriptrunner: Script "' . $script . '" could not be read.');
            return false;
        }
        $contents  = file_get_contents($script);
        $container = containerFromService($service);

        info('scriptrunner: Executing "' . $script . '" in "' . $service . '".');
        // We run the script as a heredoc in the container so that we don't need to figure out whether the script
        // is available within the container or not. This approach works for both
        // Note: Because of "docker-compose exec" woes with stdin (https://github.com/docker/compose/issues/3352), use docker instead
        (new Exec())->passthru()->run(di('docker') . ' exec -i ' . $container . ' /bin/sh <<\EOB' . PHP_EOL . $contents . PHP_EOL . 'EOB' . PHP_EOL);
        return true;
    }

}