<?php

namespace DCHelper\Configurations;

/**
 * Class DockerComposeUsage
 * @package DCHelper\Configurations
 *
 * Since docker-compose uses a not-really-standard way of argumenting (where no equal sign is used to set values)
 * we just parse the help screens in order to determine what is what
 */
class DockerComposeUsage extends Base
{
    private $texts = [];

    public function __construct()
    {
        parent::__construct('');
    }


    public function get($key = null, $default = null, $delimiter = '.')
    {
        list($command, $section) = explode($delimiter, $key);

        if (!array_key_exists($command, $this->texts)) {
            // Don't use the DockerCompose tool, as that will try to fetch the global arguments, which calls this...
            $text     = (new \DCHelper\Tools\External\Exec())->run(di('docker-compose') . ' help ' . ($command !== 'global' ? $command : ''));
            $sections = $this->splitInSections($text);
            if (array_key_exists('options', $sections)) {
                $sections['options'] = $this->processOptionsSection($sections['options']);
            }
            if ($command === 'global') {
                $sections['commands'] = $this->processCommandsSection($sections['commands']);
            }
            $this->texts[$command] = $sections;
        }

        return array_get($this->texts[$command], $section, $default, $delimiter);
    }

    private function splitInSections($text)
    {
        $sections = [];
        if (!is_array($text)) {
            $text = explode(PHP_EOL, $text);
        }

        $section = null;
        while (count($text)) {
            $line = array_shift($text);
            if (substr($line, - 1) == ':') {
                $section            = strtolower(substr($line, 0, - 1));
                $sections[$section] = [];
            } else {
                if (($line = trim($line)) && $section) {
                    $sections[$section][] = $line;
                }
            }
        }
        return $sections;
    }

    private function processOptionsSection($lines)
    {
        $optionList = [];
        foreach ($lines as $line) {
            if (substr($line, 0, 1) === '-') {
                // Skip extra text lines, we only need the ones (there are at least 2 spaces so use that)
                $parts = preg_split('/\s{2,}/', $line, 2);

                // Now split further options (after extra check for 2 parts):
                if (count($parts) === 2) {
                    $options = explode(', ', reset($parts));

                    // Determine if it's a value option or not, the last item will have a space:
                    $hasValue = false;
                    if (($pos = strpos($option = end($options), ' ')) !== false) {
                        $hasValue               = true;
                        $options[key($options)] = substr($option, 0, $pos);
                    }

                    foreach ($options as $option) {
                        $optionList[ltrim($option, '-')] = $hasValue;
                    }
                }
            }
        }
        return $optionList;
    }

    private function processCommandsSection($lines)
    {
        $commands = [];
        foreach ($lines as $line) {
            list($command, $text) = preg_split('/\s{2,}/', $line);
            $commands[] = $command;
        }
        return $commands;
    }
}