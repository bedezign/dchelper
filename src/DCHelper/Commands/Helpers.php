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

        // We support a mix of numeric entries (in case you want to use a command more than once) and regular command entries, normalize this first
        if (!is_numeric(key($helpers))) {
            $helpers = array_map(function($helper, $key) {
                return is_numeric($key) ? $helper : [$key => $helper];
            }, $helpers, array_keys($helpers));
        }

        foreach ($helpers as $helper) {
            if (!$this->triggerHelper(key($helper), reset($helper), $arguments)) {
                return false;
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