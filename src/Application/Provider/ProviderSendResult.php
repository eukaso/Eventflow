<?php
namespace EventFlow\Application\Provider;
final readonly class ProviderSendResult{public function __construct(public ProviderOutcome $outcome,public ?string $providerMessageId=null,public ?string $providerRequestId=null,public ?string $responseCode=null,public ?string $errorCode=null){}}
