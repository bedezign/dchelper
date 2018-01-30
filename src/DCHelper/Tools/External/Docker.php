<?php

namespace DCHelper\Tools\External;

class Docker extends Command
{
    public function run(...$arguments): string
    {
        // Docker binary
        array_unshift($arguments, di('docker'));
        return $this->_execute(implode(' ', $arguments));
    }
}