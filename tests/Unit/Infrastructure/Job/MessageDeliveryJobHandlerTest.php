<?php

namespace EventFlow\Tests\Unit\Infrastructure\Job;

use EventFlow\Application\Job\JobException;
use EventFlow\Infrastructure\Job\MessageDeliveryJobHandler;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

final class MessageDeliveryJobHandlerTest extends TestCase
{
    public function testPayloadValidationDoesNotDependOnJsonObjectKeyOrder(): void
    {
        $handler = (new ReflectionClass(MessageDeliveryJobHandler::class))->newInstanceWithoutConstructor();

        $handler->validatePayload(['provider' => 'brevo', 'message_id' => 42]);

        self::assertTrue(true);
    }

    public function testPayloadValidationStillRejectsUnexpectedFields(): void
    {
        $handler = (new ReflectionClass(MessageDeliveryJobHandler::class))->newInstanceWithoutConstructor();

        $this->expectException(JobException::class);
        $handler->validatePayload(['provider' => 'twilio', 'message_id' => 42, 'extra' => true]);
    }
}
