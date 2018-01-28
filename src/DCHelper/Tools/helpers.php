<?php

/**
 * Returns the dependency injection container (or a concrete value from it if an argument was specified)
 * @param string|null  $alias
 * @param array ...$arguments
 * @return \League\Container\Container|mixed
 */
function di($alias = null, ...$arguments)
{
    static $container = null;

    if (!$container) {
        $container = new League\Container\Container;
    }
    return $alias ? $container->get($alias, $arguments) : $container;
}

/**
 * "recursively" obtain a value from an array by specifying the "path" in dot notation
 * @param array      $value
 * @param string     $key
 * @param mixed|null $default
 * @param string     $delimiter
 * @return mixed
 */
function array_get($value, $key = null, $default = null, $delimiter = '.')
{
    if ($key === null || !is_array($value) || is_null($value)) {
        return $value;
    }

    if (array_key_exists($key, $value))
        return $value[$key];

    foreach (explode($delimiter, $key) as $segment) {
        if (is_array($value) && array_key_exists($segment, $value))
            $value = $value[$segment];
        else
            return $default;
    }

    return $value;
}

/**
 * Checks both the arguments and the environment and returns the matching value, if any
 * @param string $argument
 * @param string $environment
 * @return string|null
 */
function argOrEnv($argument, $environment)
{
    di('env');
    $arguments = di('arguments');
    return $arguments[$argument] ?? getenv($environment);
}

/**
 * Re-assemble the docker-compose command line for the given command
 * @param string $type   Either global or the name of a docker-compose command
 * @return string
 */
function assembleArguments($type)
{
    $commandLine = di('arguments');

    // Fetch possible global parameters
    $options = di('usage')->get($type . '.options');

    // Re-assemble arguments string
    $arguments = [];
    foreach ($commandLine as $argument => $value) {
        if (array_key_exists($argument, $options)) {
            $name = (strlen($argument) === 1 ? '-' : '--') . $argument;
            if ($options[$argument]) {
                $arguments[] = $name . ' ' . $value;
            }
            else {
                $arguments[] = $name;
            }
        }
        elseif ($type !== 'global' && is_int($argument) && $value !== $type) {
            // Raw parameter that isn't the command we're investigating, pass on
            $arguments[] = $value;
        }
    }

    return implode(' ', $arguments);
}

function debug($message, $eol = true)
{
    di('log')->debug($message . ($eol ? PHP_EOL : ''), []);
}

function info($message, $eol = true)
{
    di('log')->info($message . ($eol ? PHP_EOL : ''), []);
}

function warning($message, $eol = true)
{
    di('log')->warning($message . ($eol ? PHP_EOL : ''), []);
}

function error($message, $eol = true)
{
    di('log')->error($message . ($eol ? PHP_EOL : ''), []);
}

function verbose()
{
    return di('level') === 'debug';
}