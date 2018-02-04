<?php

namespace DCHelper\Commands;

interface CommandInterface
{
    /**
     * Returns a list of the command line options applicable for this command
     */
    public function help(): array;

    public function shouldRun(...$arguments) : bool;

    public function run(...$arguments);
}