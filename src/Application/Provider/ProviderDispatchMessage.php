<?php
namespace EventFlow\Application\Provider;
use EventFlow\Application\Persistence\EventScope;
final readonly class ProviderDispatchMessage{public function __construct(public EventScope $eventScope,public int $messageId,public string $channel,public string $address,public ?string $subject,public string $content,public int $attemptNumber,public string $requestKey){if($messageId<1||$attemptNumber<1||!preg_match('/^[a-f0-9]{64}$/',$requestKey))throw new ProviderException('provider_dispatch_invalid');}}
