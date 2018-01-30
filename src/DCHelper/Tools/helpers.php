<?php

/**
 * Returns the dependency injection container (or a concrete value from it if an argument was specified)
 * @param string|null $alias
 * @param array       ...$arguments
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
 * Make sure to load the .env file first before calling getenv.
 * @param string $varname
 * @return array|false|string
 */
function dcgetenv($varname)
{
    di('env');
    return getenv($varname);
}

/**
 * Checks both the arguments and the environment and returns the matching value, if any
 * @param string $argument
 * @param string $environment
 * @return string|null
 */
function argOrEnv($argument, $environment)
{
    return di('arguments')[$argument] ?? dcgetenv($environment);
}

/**
 * Re-assemble the docker-compose command line for the given command
 * @param string $type Either global or the name of a docker-compose command
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
            } else {
                $arguments[] = $name;
            }
        } elseif ($type !== 'global' && is_int($argument) && $value !== $type) {
            // Raw parameter that isn't the command we're investigating, pass on
            $arguments[] = $value;
        }
    }

    return implode(' ', $arguments);
}

/**
 * Like realpath, but doesn't care if the path exists or not.
 * Path with starting "." or "~" will get $root/cwd or home prepended.
 * Inspired by http://php.net/manual/en/function.realpath.php#84012
 * @param string      $path
 * @param null|string $root
 * @return string
 */
function absolute_path($path, $root = null): string
{
    $split = function($path) {
        $path = trim(str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $path));
        return array_filter(explode(DIRECTORY_SEPARATOR, $path), 'strlen');
    };

    $path      = trim($path);
    $parts     = $split($path);
    $firstChar = $path[0];
    $absolutes = $firstChar === '.' ? $split($root ?? getcwd()) : ($firstChar == '~' ? $split($_SERVER['HOME']) : []);
    foreach ($parts as $index => $part) {
        if ('.' === $part || '~' === $part) {
            continue;
        }

        if ('..' === $part) {
            array_pop($absolutes);
        } else {
            $absolutes[] = $part;
        }
    }

    return (strlen(ltrim($path, '\\/~.')) !== strlen($path) ? DIRECTORY_SEPARATOR : '') .
        implode(DIRECTORY_SEPARATOR, $absolutes);
}

function containerFromService($serviceName)
{
    // Since we need to trigger docker itself for this, we need the full container name
    foreach (di('running-containers')->get() as $runningContainer) {
        $name = array_get($runningContainer, 'name');
        if (strpos($name, "_{$serviceName}_") !== false) {
            return $name;
        }
    }
    return null;
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
