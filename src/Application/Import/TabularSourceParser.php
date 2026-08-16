<?php

namespace EventFlow\Application\Import;

interface TabularSourceParser
{
    public function parse(string $path): ParsedImportSource;
}
