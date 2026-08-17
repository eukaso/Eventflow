<?php
namespace EventFlow\Application\Provider;
final readonly class ProviderCapabilities{public function __construct(public bool $idempotentSend,public bool $safeRetry,public bool $supportsReconciliation){}}
