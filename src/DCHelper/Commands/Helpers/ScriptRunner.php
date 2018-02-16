<?php

namespace DCHelper\Commands\Helpers;


use DCHelper\Exceptions\HelperFailedException;
use DCHelper\Tools\External\Docker;
use DCHelper\Tools\External\Exec;

class ScriptRunner
{
    private $ran = [];

    public function run($configuration, $stage = null)
    {
        // By default we only run in post.up, unless otherwise specified
        if ($stage && $stage !== array_get($configuration, 'at', 'post.up')) {
            // Nothing to do
            return true;
        }

        $service = array_get($configuration, 'service');
        $isLocal = $service === 'localhost';
        if (!$service) {
            throw new HelperFailedException('Please specify a service to run against (localhost to run on this machine)');
        }
        $container = containerFromService($service);

        // Should scripts only be ran once?
        $once     = array_get($configuration, 'once');
        $lockFile = array_get($configuration, 'lock-file');
        if ($once && $isLocal) {
            throw new HelperFailedException('"once" is not supported on localhost at this moment.');
        }

        if ($once && !$lockFile) {
            throw new HelperFailedException('To keep track of the scripts that already ran, please specify the "lock-file" option');
        }

        $envSubst = function($string) { return $string; };
        $dollarSigns = 0;
        array_walk_recursive($configuration, function($value) use (&$dollarSigns) { $dollarSigns += (is_string($value) && strpos($value, '$') !== false) ? 1 : 0; });
        if ($dollarSigns) {
            $envSubst = function($string) use ($configuration) {
                return (new EnvSubst())->forString($string, $configuration);
            };
        }

        $root = array_get($configuration, 'root') ?? getcwd();

        if ($once) {
            // Obtain the script lock file first
            $temp       = tempnam(sys_get_temp_dir(), 'dchelper');
            $alreadyRan = [];
            $docker     = (new Docker())->mustRun(false);
            $docker->run("cp $container:$lockFile $temp");
            if ($docker->exit === 0) {
                $alreadyRan = array_map('trim', file($temp));
            }

            $once     = !is_array($once) ? [$once] : $once;
            $executed = 0;
            foreach ($once as $script) {
                $script = $envSubst($script);
                if (!in_array($script, $alreadyRan)) {
                    $this->runScript($service, $container, $script[0] !== DIRECTORY_SEPARATOR ? absolute_path($script, $root) : $script);
                    $alreadyRan[] = $script;
                    $executed ++;
                }
            }

            if ($executed && $service) {
                // Create the revised lock file in the container:
                file_put_contents($temp, implode(PHP_EOL, $alreadyRan));
                $docker->mustRun()->run("cp $temp $container:$lockFile");
            }
            @unlink($temp);
        }


        $direct = array_get($configuration, 'direct', false);
        $always = array_get($configuration, 'always', []);
        $always = !is_array($always) ? [$always] : $always;
        foreach ($always as $script) {
            $script = $envSubst($script);
            $this->runScript($service, $container, $script[0] !== DIRECTORY_SEPARATOR ? absolute_path($script, $root) : $script, $direct);
        }
    }

    private function runScript($service, $container, $script, $direct = false)
    {
        info('scriptrunner: Executing "' . $script . '" in "' . $service . '".');
        if (!is_readable($script)) {
            throw new HelperFailedException('Script "' . $script . '" could not be read.');
        }
        $content = file_get_contents($script);
        $command = '/bin/sh <<\EOB' . PHP_EOL . $content . PHP_EOL . 'EOB' . PHP_EOL;

        if ($service !== 'localhost') {
            // We run the script as a heredoc in the container so that we don't need to figure out whether the script
            // is available within the container or not. This approach works for both
            // Note: Because of "docker-compose exec" woes with stdin (https://github.com/docker/compose/issues/3352), use docker instead
            (new Docker())->passthru()->run('exec -i ' . $container . ' ' . $command);
        } else {
            (new Exec())->passthru()->run($direct ? $content : $command);
        }
    }

}