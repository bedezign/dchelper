<?php

namespace DCHelper\Tools;

use DCHelper\Configurations\DockerCompose;
use DCHelper\Configurations\DockerComposeRaw;
use DCHelper\Configurations\DockerComposeUsage;
use DCHelper\Configurations\RunningContainers;
use Monolog\Formatter\LineFormatter;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;

$container = di();

$container->share('level', function() {
    return in_array('--verbose', $_SERVER['argv']) ? 'debug' : 'info';
});

$container->share('log', function() {
    $level   = di('level') === 'debug' ? Logger::DEBUG : Logger::INFO;
    $handler = new StreamHandler("php://stdout", $level);
    $handler->setFormatter(
        new LineFormatter("%message%", null, true, true)
    );
    return new Logger('console', [$handler]);
});

$container->share('lo', 'lo0');

$container->share('sudo', function() { return trim(`which sudo`); });
$container->share('socat', function() { return trim(`which socat`); });
$container->share('ifconfig', function() { return trim(`which ifconfig`); });
$container->share('hosts', '/etc/hosts');

$container->share('docker', function() {
    $binary = argOrEnv('docker', 'DOCKER_BINARY');
    if (!$binary) {
        $binary = trim(`which docker`) ?? '/usr/local/bin/docker';
    }
    return $binary;
});

$container->share('docker-compose', function() {
    $binary = argOrEnv('compose', 'COMPOSE_BINARY');
    if (!$binary) {
        $binary = trim(`which docker-compose`) ?? '/usr/local/bin/docker-compose';
    }
    return $binary;
});

$container->share('env', function() {
    $file = getcwd() . '/.env';
    if (is_readable($file)) {
        (new \Symfony\Component\Dotenv\Dotenv())->load($file);
    }
});

$container->share('arguments', function() {
    /**
     * docker-compose does not enforce using an equal sign when specifying values for options.
     * This basically f*s up the entire argument parsing process.
     * This piece of code actually uses the docker-compose help screens to recognize value-options, commands etc
     * and modifies the command line so it can be correctly interpreted.
     */
    static $busy = false;

    if ($busy) {
        // Prevent re-entry: Quickly return regularly parsed version.
        // This can only happen while we are running this function, probably because we are resolving 'docker-compose'
        // Unfortunately, if the user specified "--compose <path>" instead of "--compose=<path>", we'll miss that here
        return \DCHelper\Tools\CommandLine::parseArgs($_SERVER['argv']);
    }
    $busy = true;

    // Re-parse the arguments first, since docker compose likes to act annoying and doesn't use an equal sign
    $arguments = array_slice($_SERVER['argv'], 1);

    // Fetch possible global parameters
    $options  = di('usage')->get('global.options');
    $commands = di('usage')->get('global.commands');

    $newArguments = [''];
    while (count($arguments)) {
        $value    = array_shift($arguments);
        $noDashes = ltrim($value, '-');

        if (array_key_exists($noDashes, $options)) {
            // Global option. Question is: Does it have a value?
            if ($options[$noDashes]) {
                // Yes: value is the next argument, specify as long since this has a value and 1 '-' is interpreted as toggle
                $newArguments[] = '--' . $noDashes . '=' . array_shift($arguments);
            } else {
                // No, toggle
                $newArguments[] = $value;
            }
        } elseif ($value !== 'help' && in_array($value, $commands)) {
            // We've reached the command, add to arguments and toggle the options:
            // (don't do this for help as we can ask for "help on help")
            $newArguments[] = $value;
            if (count($arguments)) {
                // Don't bother if we have nothing left, but if we do, load the command-specific options
                $options = di('usage')->get($value . '.options');
            }
        } else {
            // Probably a correctly specified option with =, just add
            $newArguments[] = $value;
        }
    }

    return CommandLine::parseArgs($newArguments);
});

// Maps onto the docker-compose help texts
$container->share('usage', DockerComposeUsage::class);
// docker-compose config after parsing
$container->share('compose-config', DockerCompose::class);
// Raw docker-compose file, untouched (will still include vendor extensions - https://github.com/docker/cli/pull/452)
$container->share('compose-config-raw', DockerComposeRaw::class);
// Running containers, obtained via docker-compose ps
$container->share('running-containers', RunningContainers::class);