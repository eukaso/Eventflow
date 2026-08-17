<?php
namespace EventFlow\Application\Provider;
final readonly class ProviderEventRecord{public function __construct(public int $providerEventId,public int $messageId,public string $dedupeKey,public string $normalizedType,public bool $duplicate=false){}}
