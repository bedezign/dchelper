<?php

namespace DCHelper\Commands\Helpers;

use DCHelper\Exceptions\HelperFailedException;
use DCHelper\Tools\External\Docker;

class EnvSubst
{
    private $environment;
    private $replacements;

    public function run($configuration, $stage = null)
    {
        if ($stage && $stage !== array_get($configuration, 'at', 'pre.up')) {
            // If a stage was specified, we only run in pre.up
            return true;
        }

        $this->prepare($configuration);

        $files = array_get($configuration, 'files');
        $root  = array_get($configuration, 'root');
        if (!\is_array($files)) {
            $files = [$files];
        }
        foreach ($files as $file) {
            if (!$this->fileReplacement($file, $root)) {
                return false;
            }
        }
        return true;
    }

    /**
     * Run the replacements on a given string
     * @param string $string
     * @param array $configuration
     * @return mixed
     */
    public function forString($string, $configuration = [])
    {
        $this->prepare($configuration);
        foreach ($this->replacements as $function => $parameters) {
            $parameters[] = $string;
            $string       = $function(...$parameters);
        }
        return $string;
    }

    private function prepare($configuration)
    {
        if (!$this->environment || !$this->replacements) {
            $this->environment  = $this->assembleEnvironment($configuration);
            $this->replacements = $this->buildReplacements($this->environment);
        }
    }

    /**
     * Assembles an set of environment variables to use for envsubst.
     * @TODO "global" environment? custom values?
     * @param $configuration
     * @return array
     */
    private function assembleEnvironment($configuration)
    {
        $values      = [];

        // Files:
        $files = array_get($configuration, 'environment-file', ['.env']);
        foreach ((array) $files as $file) {
            if (is_readable($file)) {
                $values = array_merge($values, (new \Symfony\Component\Dotenv\Dotenv())->parse(file_get_contents($file), $file));
            }
       }

       // Docker containers:
        $environment = array_get($configuration, 'environment', []);
        foreach ($environment as $name) {
            $values = array_merge($values,
                di('compose-config')->get('services.' . $name . '.environment', [])
            );
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

    /**
     * @param string $fileSpec
     * @param string $root Root directory for relative source entries
     * @throws \DCHelper\Exceptions\HelperFailedException
     */
    private function fileReplacement($fileSpec, $root = null)
    {
        list($from, $to) = explode(':', $fileSpec, 2);
        $fileName = basename($to);

        $service = false;
        if (strpos($to, ':') !== false) {
            // If the "to" part contains another colon it specifies a path into a service and we need to threat this differently
            list($service, $to) = explode(':', $to, 2);
        }

        $fromAbsolute = absolute_path($from, $root);
        $toAbsolute   = absolute_path($toDir = dirname($to));

        if (!\is_readable($fromAbsolute)) {
            throw new HelperFailedException('Source file "' . $from . '" does not exist/cannot be read from.');
        }

        // If we're still here then we can do the substitution and save the file
        info(PHP_EOL . '>>> envsubst: Generating "' . $to . '" from "' . $from . '"');
        $content = file_get_contents($fromAbsolute);
        foreach ($this->replacements as $function => $parameters) {
            $parameters[] = $content;
            $content      = $function(...$parameters);
        }

        // Service name specified, the resulting configuration needs to go in the container.
        // (usually for things that need to be added in the home folder etc)
        if ($service) {

            $temp = tempnam(sys_get_temp_dir(), 'dchelper');
            if (!file_put_contents($temp, $content)) {
                throw new HelperFailedException('Unable to create temporary file.');
            }

            $container = containerFromService($service);
            if (!$container) {
                throw new HelperFailedException('Creating files within a container is only possible when it is running. Use "at: post.up" in your configuration to trigger this after the up command.');
            }

            $docker = (new Docker())->passthru();
            $docker->run('exec ' . $container . ' mkdir -p ' . $toDir);
            $docker->run('cp ' . $temp . " $container:" . $to);
            unlink($temp);

        } else {

            // https://github.com/kalessil/phpinspectionsea/blob/master/docs/probable-bugs.md#mkdir-race-condition
            !is_dir($toAbsolute) && !mkdir($toAbsolute, 0755, true);
            if (!is_dir($toAbsolute)) {
                throw new HelperFailedException('Target directory "' . $toDir . '" does not exist and cannot be created. Please create it first.');
            }

            if (!file_put_contents($toAbsolute . DIRECTORY_SEPARATOR . $fileName, $content)) {
                throw new HelperFailedException('Unable to save the file');
            }

        }
    }
}