<?php

namespace DCHelper\Tools\External;

class Passthru extends Command
{
    public function run(...$arguments): string
    {
        $this->passthru = true;
        $this->mustRun = false;

        return $this->_execute(...$arguments);
    }
}