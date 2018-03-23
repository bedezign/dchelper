<?php

namespace DCHelper\Commands\Helpers;


use DCHelper\Exceptions\HelperFailedException;
use DCHelper\Tools\External\Docker;
use DCHelper\Tools\External\Exec;

class ScriptRunner
{
    private $doEnvSubst;
    private $service;
    private $lockFile;
    private $isLocal  = false;
    private $container;
    private $once     = true;
    private $envSubst = true;
    private $root;

    public function run($configuration, $stage = null)
    {
        // By default we only run in post.up, unless otherwise specified
        if ($stage && $stage !== array_get($configuration, 'at', 'post.up')) {
            // Nothing to do
            return true;
        }

        $this->configure($configuration);
        if ($this->once) {
            $this->once();
        }

        // @TODO direct command defined as array: Consider command + arguments

        $direct = array_get($configuration, 'direct', false);
        $always = array_get($configuration, 'always', []);
        $always = !\is_array($always) ? [$always] : $always;
        foreach ($always as $script) {
            $script = ($this->doEnvSubst)($script);
            $this->runScript($script, $direct);
        }
    }

    /**
     * Execute scripts defined as "once" while updating the lock file
     */
    private function once()
    {
        $alreadyRan = [];

        $docker = (new Docker())->mustRun(false);

        $temp = tempnam(sys_get_temp_dir(), 'dchelper');
        $docker->run("cp {$this->container}:{$this->lockFile} {$temp}");
        if ($docker->exit === 0) {
            $alreadyRan = array_map('trim', file($temp));
        }

        $once = !\is_array($this->once) ? [$this->once] : $this->once;

        $executed = 0;
        foreach ($once as $script) {

            $script = ($this->doEnvSubst)($script);

            if (!\in_array($script, $alreadyRan)) {

                $this->runScript($script);

                $alreadyRan[] = $script;
                $executed ++;

                if (!$this->isLocal) {
                    file_put_contents($temp, implode(PHP_EOL, $alreadyRan));
                    $docker->mustRun()->run("cp $temp {$this->container}:{$this->lockFile}");
                }
            }
        }

        debug($executed . ' script(s) executed.');

        @unlink($temp);
    }

    /**
     * @param      $script
     * @param bool $direct
     * @throws \DCHelper\Exceptions\HelperFailedException
     */
    private function runScript($script, $direct = false)
    {
        $script = $script[0] !== DIRECTORY_SEPARATOR ? absolute_path($script, $this->root) : $script;

        info(PHP_EOL . '>>> scriptrunner: Executing "' . $script . '" in "' . $this->service . '".');

        if (!is_readable($script)) {
            throw new HelperFailedException('Script "' . $script . '" could not be read.');
        }

        $content = file_get_contents($script);
        if ($this->envSubst) {
            $content = ($this->doEnvSubst)($content);
        }

        $command = '/bin/sh <<\EOB' . PHP_EOL . $content . PHP_EOL . 'EOB' . PHP_EOL;

        if ($this->service !== 'localhost') {
            // We run the script as a heredoc in the container so that we don't need to figure out whether the script
            // is available within the container or not. This approach works for both
            // Note: Because of "docker-compose exec" woes with stdin (https://github.com/docker/compose/issues/3352), use docker instead
            (new Docker())->passthru()->run('exec -i ' . $this->container . ' ' . $command);
        } else {
            (new Exec())->passthru()->run($direct ? $content : $command);
        }
    }

    private function configure(array $configuration = [])
    {
        $envSubst         = new EnvSubst();
        $this->doEnvSubst = function($string) use ($configuration, $envSubst) {
            return $envSubst->forString($string, $configuration);
        };

        $this->service = array_get($configuration, 'service');
        $this->isLocal = $this->service === 'localhost';
        if (!$this->service) {
            throw new HelperFailedException('Please specify a service to run against (localhost to run on this machine)');
        }

        $this->container = containerFromService($this->service);
        if (!$this->isLocal && !$this->container) {
            throw new HelperFailedException('Unable to determine the container to run in');
        }

        // Should scripts only be ran once?
        $this->once     = array_get($configuration, 'once');
        $this->lockFile = array_get($configuration, 'lock-file', '/.dchelper.scripts');
        if ($this->once && $this->isLocal) {
            throw new HelperFailedException('"once" is not supported on localhost at this moment.');
        }

        if ($this->once && !$this->lockFile) {
            throw new HelperFailedException('To keep track of the scripts that already ran, please specify the "lock-file" option');
        }

        // Replace in script files (only non-direct)
        $this->envSubst = array_get($configuration, 'envsubst', true);

        $this->root = array_get($configuration, 'root') ?? getcwd();
    }

}