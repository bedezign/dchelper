<?php

namespace DCHelper\Configurations;

/**
 * Class DockerCompose
 * @package DCHelper\Configurations
 *
 * Represents the docker-compose config, but unparsed (reads the yml file from disk)
 */
class DockerComposeRaw extends Yaml
{
    public function __construct()
    {
        $arguments = di('arguments');
        $file   = array_get($arguments, 'file', array_get($arguments, 'f', ($file = dcgetenv('COMPOSE_FILE')) ? $file : 'docker-compose.yml'));
        parent::__construct('file://' . $file);
    }
}