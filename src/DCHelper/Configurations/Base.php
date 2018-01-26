<?php

namespace DCHelper\Configurations;

/**
 * Class Base
 * @package DockerComposerHelper\Configuration
 */
class Base
{
    /** @var string */
    private $source;

    /** @var array */
    protected $content;

    /**
     * Configuration constructor.
     * @param string|callable $source
     */
    public function __construct($source)
    {
        $this->source = $source;
    }

    /**
     * Returns the value from our data specifying a location in dot-notation
     * @param string|null $key
     * @param mixed       $default Value to return if the requested path has not been set
     * @param string      $delimiter
     * @return mixed
     */
    public function get($key = null, $default = null, $delimiter = '.')
    {
        $value = $this->load();

        return array_get($value, $key, $default, $delimiter);
    }

    protected function load()
    {
        if (!$this->content) {
            if (is_callable($this->source)) {
                $this->content = ($this->source)();
            } elseif (substr($this->source, 0, 7) === 'file://') {
                $this->content = $this->loadFromFile(substr($this->source, 7));
            } else {
                $this->content = $this->loadFromText($this->source);
            }
        }

        return $this->content;
    }

    protected function loadFromFile($path)
    {
        return file_get_contents($path);
    }

    protected function loadFromText($text)
    {
        return $text;
    }
}