<?php

namespace DCHelper\Commands\Helpers;

class EnvSubst
{
    private $dotEnv;

    public function run($configuration)
    {
        $environment  = $this->assembleEnvironment($configuration);
        $replacements = $this->buildReplacements($environment);
        $files        = array_get($configuration, 'files');
        if (!\is_array($files)) {
            $files = [$files];
        }
        foreach ($files as $file) {
            if (!$this->fileReplacement($file, $replacements)) {
                return false;
            }
        }
        return true;
    }

    private function fileReplacement($file, $replacements)
    {
        list($from, $to) = explode(':', $file, 2);
        $fileName = basename($to);

        $fromAbsolute = realpath($from);
        $toAbsolute   = realpath($toDir = \dirname($to));

        if (!$fromAbsolute) {
            error('envsubst: Source file "' . $from . '" does not exist.');
            return false;
        }

        if (!\is_readable($fromAbsolute)) {
            error('envsubst: Unable to read from source file "' . $from . '".');
            return false;
        }

        if (!$toAbsolute) {
            // Directory doesn't exist, try to create
            // https://github.com/kalessil/phpinspectionsea/blob/master/docs/probable-bugs.md#mkdir-race-condition
            !is_dir($toDir) && !mkdir($toDir) && !is_dir($toDir);
            $toAbsolute = realpath($toDir);
        }

        if (!$toAbsolute) {
            error('envsubst: Target directory "' . $toDir . '" does not exist and cannot be created. Please create it first.');
            return false;
        }

        // If we're still here then we can do the substitution and save the file
        info('envsubst: Generating "' . $to . '" from "' . $from . '"');
        $content = file_get_contents($fromAbsolute);
        foreach ($replacements as $function => $parameters) {
            $parameters[] = $content;
            $content = $function(...$parameters);
        }
        return file_put_contents($toAbsolute . DIRECTORY_SEPARATOR . $fileName, $content);
    }

    private function assembleEnvironment($configuration)
    {
        $values      = [];
        $environment = array_get($configuration, 'environment', ['.env']);
        foreach ($environment as $name) {
            if ($name === '.env') {
                $values = array_merge($values, $this->getDotEnv());
            } else {
                $values = array_merge($values, di('compose-config')->get('services.' . $name . '.environment', []));
            }
        }
        return $values;
    }

    private function buildReplacements($environment)
    {
        return [
            'str_replace'  => [
                // Initially replace the ${VARIABLE} variants
                array_map(function($value) { return '${' . $value . '}'; }, array_keys($environment)),
                array_values($environment)
            ],
            'preg_replace' => [
                // After that replace all $VARIABLE variants. To not replace too much (eg $hostname when we have $host)
                // we match on an extra non-word character and put the 2nd match back
                array_map(function($value) { return '/($' . $value . ')([^\w])/'; }, array_keys($environment)),
                array_map(function($value) { return $value . '$2'; }, array_values($environment))
            ]
        ];

    }

    private function getDotEnv()
    {
        if (!$this->dotEnv) {
            $this->dotEnv = [];
            $names        = ($dotenv = array_get($_SERVER, 'SYMFONY_DOTENV_VARS')) ? explode(',', $dotenv) : array_keys($_SERVER);
            foreach ($names as $name) {
                $this->dotEnv[$name] = array_get($_SERVER, $name);
            }
        }
        return $this->dotEnv;
    }
}