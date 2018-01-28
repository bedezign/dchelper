<?php

namespace DCHelper\Commands;

class Helpers extends Command
{
    private static $helpers = [
        'envsubst' => Helpers\EnvSubst::class,
    ];

    public function run(...$arguments): bool
    {
        // See if anything was defined that should be ran through the helpers
        $helpers = di('compose-config-raw')->get('x-dchelper');
        if (!$helpers) {
            // Nothing to do
            return true;
        }

        // If a single helper was specified, wrap it extra so we can loop
        if (!is_numeric(key($helpers))) {
            $helpers = [$helpers];
        }

        foreach ($helpers as $helper) {
            if (!$this->triggerHelper(key($helper), reset($helper))) {
                return false;
            }
        }

        return true;
    }

    private function triggerHelper($command, $config)
    {
        $lcCommand = strtolower(trim($command));
        if (!array_key_exists($lcCommand, self::$helpers)) {
            error('Unknown helper: ' . $command);
            return false;
        }

        $class = self::$helpers[$lcCommand];
        $helper = new $class();
        return $helper->run($config);
    }
}