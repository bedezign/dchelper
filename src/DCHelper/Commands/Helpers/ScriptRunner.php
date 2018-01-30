<?php

namespace DCHelper\Commands\Helpers;


use DCHelper\Tools\External\Docker;

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

        $service = array_get($configuration, 'service');
        if (!$service) {
            error('scriptrunner: Please specify a service to run against');
            return false;
        }
        $container = containerFromService($service);

        // Obtain the script lock file first
        $temp = tempnam(sys_get_temp_dir(), 'dchelper');
        $alreadyRan = [];
        $docker = (new Docker())->mustRun(false);
        $docker->run("cp $container:$lockFile $temp");
        if ($docker->exit === 0) {
            $alreadyRan = array_map('trim', file($temp));
        }

        $once = !is_array($once) ? [$once] : $once;
        $executed = 0;
        foreach ($once as $script) {
            if (!in_array($script, $alreadyRan)) {
                info('scriptrunner: Executing "' . $script . '" in "' . $service . '".');
                if (!$this->runScript($container, $script[0] !== DIRECTORY_SEPARATOR ? absolute_path($script, $root) : $script)) {
                    return false;
                }
                $alreadyRan[] = $script;
                $executed ++;
            }
        }

        // Put the script file back for next time:
        if ($executed) {
            file_put_contents($temp, implode(PHP_EOL, $alreadyRan));
            $docker->mustRun()->run("cp $temp $container:$lockFile");
        }
        @unlink($temp);

        return true;
    }

    private function runScript($container, $script)
    {
        if (!is_readable($script)) {
            error('scriptrunner: Script "' . $script . '" could not be read.');
            return false;
        }
        $contents  = file_get_contents($script);

        // We run the script as a heredoc in the container so that we don't need to figure out whether the script
        // is available within the container or not. This approach works for both
        // Note: Because of "docker-compose exec" woes with stdin (https://github.com/docker/compose/issues/3352), use docker instead
        (new Docker())->passthru()->run('exec -i ' . $container . ' /bin/sh <<\EOB' . PHP_EOL . $contents . PHP_EOL . 'EOB' . PHP_EOL);
        return true;
    }

}