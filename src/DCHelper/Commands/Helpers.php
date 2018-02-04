<?php

namespace DCHelper\Commands;

use DCHelper\Exceptions\CommandFailedException;
use DCHelper\Exceptions\HelperFailedException;

class Helpers extends Command
{
    private static $helpers = [
        'envsubst'     => Helpers\EnvSubst::class,
        'scriptrunner' => Helpers\ScriptRunner::class,
    ];

    public function run(...$arguments)
    {
        // See if anything was defined that should be ran through the helpers
        $helpers = di('compose-config-raw')->get('x-dchelper');
        if (!$helpers) {
            // Nothing to do
            return;
        }

        $root = null;
        foreach ($helpers as $helper => $configuration) {
            $helper = explode('.', $helper)[0];
            if ($helper === 'root') {
                $root = $configuration;
            }
            else {
                try {
                    // root override if needed
                    $configuration['root'] = array_get($configuration, 'root', $root);
                    $this->triggerHelper($helper, $configuration, $arguments);
                } catch (HelperFailedException $e) {
                    throw new CommandFailedException('Failed to run helper ' . $helper . ': ' . $e->getMessage(), 0, $e);
                }
            }
        }
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
            $helper->run($config, $stage);
        }
    }
}