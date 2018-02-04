<?php

namespace DCHelper\Commands;

use DCHelper\Tools\External\DockerCompose;

class Help extends Command
{
    const PREFIX = '[dch] ';

    public function run(...$arguments)
    {
        $help = $this->collectHelpData();
        if (\count($arguments)) {
            // Help requested for a specific command?
            $command = reset($arguments);
            foreach ($help as $item) {
                if (array_get($item, 'command') === $command) {
                    // One of ours, print help for that.
                    $this->outputInternalCommandHelp($item);
                    return;
                }
            }

            // Still here, just pass on to docker-compose
            (new DockerCompose())->passthru()->run('help ' . $command);
        } else {
            // Global help requested, modify the help page itself
            $this->outputGlobalHelp($help);
        }
    }

    private function collectHelpData()
    {
        $help = [];

        $files = new \FilesystemIterator(__DIR__);
        foreach ($files as $file) {
            $class = preg_replace('/Help$/', $command = $file->getBasename('.php'), __CLASS__);
            if (!in_array($command, ['Help', 'Command', 'CommandInterface'])) {
                $command = new $class();
                $help[]  = $command->help();
            }
        }
        return array_values(array_filter($help));
    }

    private function getOptions($item, $type)
    {
        if ($type !== null) {
            if (!$options = array_get($item, $type)) {
                return null;
            }
        } else {
            $options = $item;
        }

        $length = 0;
        foreach ($options as $option => $data) {
            $data['name']             = $option;
            $options[$option]         = $data;
            $length                   = max($length, \strlen($text = $this->makeOption($data)));
            $options[$option]['text'] = $text;
        }

        $options[0] = $length;
        return $options;
    }

    private function outputInternalCommandHelp($item)
    {
        $command = array_get($item, 'command');
        info(array_get($item, 'description', $command . ' command') . PHP_EOL);

        $usage = array_get($item, 'usage', $command);
        if ($usage) {
            info('Usage: ' . $usage . PHP_EOL);
        }

        // Related options (we define most options globally so you can include them in the alias you create
        if ($options = $this->getOptions($item, 'global-options')) {
            $string    = 'Global related options:' . PHP_EOL;
            $maxLength = $options[0];
            unset($options[0]);

            $maxLength += 4; // Add margin and spacer
            foreach ($options as $option) {
                $string .= $this->makeHelpLine($option, $maxLength);
            }
            $string .= PHP_EOL;

            info($string);
        }
    }

    private function outputGlobalHelp($help)
    {
        $global = di('usage')->get('global');
        $lines  = explode(PHP_EOL, array_get($global, 'text'));

        // Split in sections based on the offsets from the original help
        $sections = [];
        $start    = 0;
        $section  = 'intro';
        foreach ($global['offsets'] as $nextSection => $line) {
            $sections[$section] = array_slice($lines, $start, $line - $start - 1); // - 1 to skip the empty line at the end
            $section            = $nextSection;
            $start              = $line;
        }
        $sections[$section] = array_slice($lines, $start, - 1);


        // Iterate our own help data and aggregate them
        $options = $commands = $environment = [];
        foreach ($help as $command => $item) {
            if (array_get($item, 'command') && !array_get($item, 'hide-from-overview', false)) {
                $commands[] = $item;
            }

            if ($globalOptions = array_get($item, 'global-options')) {
                $options = array_merge($options, $globalOptions);
            }

            if ($globalEnv = array_get($item, 'environment')) {
                $environment = array_merge($environment, $globalEnv);
            }
        }

        // Update some of the sections with our own text
        foreach ($sections as $section => $text) {
            if ($section === 'options' && \count($options)) {
                $options = $this->getOptions($options, null);
                // Add our global options
                $textColumn = $this->determineTextColumn($text[1]);
                foreach ($options as $key => $option) {
                    if ($key !== 0) {
                        $text[] = $this->makeHelpLine($option, $textColumn, self::PREFIX);
                    }
                }

            } elseif ($section === 'commands' && \count($commands)) {
                // Add our commands
                $textColumn = $this->determineTextColumn($text[1]);
                foreach ($commands as $command) {
                    $command['text'] = $command['command'];
                    $text[]          = $this->makeHelpLine($command, $textColumn, self::PREFIX);
                }
            }

            info(implode(PHP_EOL, $text) . PHP_EOL);
        }

        // If we have environment variables to show...
        if (\count($environment)) {
            $section   = ['Environment [DCHelper]:'];
            $maxLength = 0;
            foreach ($environment as $name => $description) {
                $maxLength = max($maxLength, \strlen($name));
            }

            $maxLength += 4; // Margin and spacer
            foreach ($environment as $name => $description) {
                $section[] = $this->makeHelpLine(['text' => $name, 'description' => $description], $maxLength);
            }

            info(implode(PHP_EOL, $section), PHP_EOL);
        }
    }

    private function makeOption($option)
    {
        $name  = array_get($option, 'name');
        $value = array_get($option, 'value');
        $name  = ($value ? '--' : '-') . $name;
        return $name . ($value ? '=' . $value : '');
    }

    private function makeHelpLine($option, $firstColumnWidth, $prefix = '')
    {
        $textLength = 95 - $firstColumnWidth;
        $lines      = $this->splitString($prefix . array_get($option, 'description'), $textLength);
        $spacer     = str_pad('', $firstColumnWidth);
        foreach ($lines as $index => $line) {
            $lines[$index] = (!$index ?
                    str_pad('  ' . $option['text'], $firstColumnWidth) :
                    $spacer
                ) . $line;
        }
        return implode(PHP_EOL, $lines);
    }

    private function splitString($text, $maxLength): array
    {
        $lines = [];
        while (strlen($text) > $maxLength) {
            $split   = strrpos(substr($text, 0, $maxLength), ' ') + 1;
            $lines[] = substr($text, 0, $split);
            $text    = substr($text, $split);  // "Jumped Over The Lazy / Dog"
        }
        if (strlen($text)) {
            $lines[] = $text;
        }

        return $lines;
    }

    private function determineTextColumn($line)
    {
        $parts = preg_split('/\s{2,}/', trim($line), 2);
        return strpos($line, end($parts));
    }
}