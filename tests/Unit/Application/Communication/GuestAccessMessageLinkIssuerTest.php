<?php

namespace EventFlow\Tests\Unit\Application\Communication;

use EventFlow\Application\Communication\CommunicationException;
use EventFlow\Application\Communication\GuestAccessMessageLinkIssuer;
use PHPUnit\Framework\TestCase;

final class GuestAccessMessageLinkIssuerTest extends TestCase
{
    public function testRejectsNonHttpsGuestPage(): void
    {
        $this->expectException(CommunicationException::class);
        $this->expectExceptionMessage('guest_page_url_invalid');
        new GuestAccessMessageLinkIssuer(
            (new \ReflectionClass(\EventFlow\Application\GuestAccess\GuestAccessService::class))->newInstanceWithoutConstructor(),
            $this->createStub(\EventFlow\Application\Clock\Clock::class),
            'http://events.example.test/rsvp',
        );
    }
}
