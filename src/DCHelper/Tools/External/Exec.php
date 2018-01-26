<?php

namespace DCHelper\Tools\External;

class Exec extends Command
{
    public function run(...$arguments): string
    {
        return $this->_execute(...$arguments);
    }
}