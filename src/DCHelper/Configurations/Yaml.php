<?php

namespace DCHelper\Configurations;

use Symfony\Component\Yaml\Yaml as Parser;

class Yaml extends Base
{
    protected function loadFromFile($path)
    {
        return Parser::parseFile($path);
    }

    protected function loadFromText($text)
    {
        return Parser::parse($text);
    }
}