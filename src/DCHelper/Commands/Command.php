<?php

namespace DCHelper\Commands;

abstract class Command implements CommandInterface
{
    /**
     * Returns a list of the command line options applicable for this command
     */
    public function help(): array
    {
        return [];
    }

    public function shouldRun(...$arguments): bool
    {
        return true;
    }
}