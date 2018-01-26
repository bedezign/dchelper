<?php

namespace DCHelper\Commands;

interface Command
{
    public function shouldRun(...$arguments) : bool;

    public function run(...$arguments) : bool;
}