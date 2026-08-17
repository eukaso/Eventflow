<?php

namespace EventFlow\Infrastructure\Observability;

use EventFlow\Application\Observability\LogSink;

final readonly class WordPressStructuredLogSink implements LogSink
{
    public function write(array $record): void
    {
        error_log(json_encode($record, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
    }
}
