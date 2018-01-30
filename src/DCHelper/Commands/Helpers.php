<?php

namespace DCHelper\Commands;

class Helpers extends Command
{
    private static $helpers = [
        'envsubst'     => Helpers\EnvSubst::class,
        'scriptrunner' => Helpers\ScriptRunner::class,
    ];

    public function run(...$arguments): bool
    {
        // See if anything was defined that should be ran through the helpers
        $helpers = di('compose-config-raw')->get('x-dchelper');
        if (!$helpers) {
            // Nothing to do
            return true;
        }

        $root = null;
        foreach ($helpers as $helper => $configuration) {
            $helper = explode('.', $helper)[0];
            if ($helper === 'root') {
                $root = $configuration;
            }
            else {
                // root override if needed
                $configuration['root'] = array_get($configuration, 'root', $root);
                if (!$this->triggerHelper($helper, $configuration, $arguments)) {
                    return false;
                }
            }
        }

        return true;
    }

    private function triggerHelper($command, $config, array $stages = [])
    {
        $lcCommand = strtolower(trim($command));
        if (!array_key_exists($lcCommand, self::$helpers)) {
            error('Unknown helper: ' . $command);
            return false;
        }

        $class  = self::$helpers[$lcCommand];
        $helper = new $class();
        foreach ($stages as $stage) {
            if (!$helper->run($config, $stage)) {
                return false;
            }
        }
        return true;
    }
}