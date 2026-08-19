<?php

namespace EventFlow\Tests\Integration;

use EventFlow\Application\Authorization\PrincipalContext;
use EventFlow\Application\GuestAccess\{GuestSessionAccess, GuestSessionAccessService};
use EventFlow\Application\Persistence\EventScope;
use PHPUnit\Framework\TestCase;

final class Sprint10GuestSessionAccessContractTest extends TestCase
{
    public function testGuestPrincipalCarriesExactSessionScope(): void
    {
        $principal = PrincipalContext::guest(12, new EventScope(44), 81);
        self::assertSame(12, $principal->guestSessionId);
        self::assertSame(44, $principal->eventScope?->eventId);
        self::assertSame(81, $principal->invitationId);
    }

    public function testNarrowPortAndReadyFoundationCompositionAreAccepted(): void
    {
        self::assertContains(GuestSessionAccess::class, class_implements(GuestSessionAccessService::class));
        $foundation = $this->source('src/Bootstrap/DatabaseFoundation.php');
        self::assertStringContainsString('public GuestSessionAccessService $guestSessionAccess', $foundation);
        self::assertStringContainsString('new WpdbGuestSessionAccessRepository(', $foundation);
    }

    public function testPurposeScopedPermissionsAndTransportDeferralAreDocumented(): void
    {
        $service = $this->source('src/Application/GuestAccess/GuestSessionAccessService.php');
        foreach (['GuestPermission::VIEW_INVITATION', 'GuestPermission::MANAGE_RSVP', 'GuestPermission::LOG_OUT'] as $permission) {
            self::assertStringContainsString($permission, $service);
        }
        $readme = $this->source('README-IMP-055.md');
        self::assertStringContainsString('exactly one active guest-session row', $readme);
        self::assertStringContainsString('intentionally adds no HTTP routes', $readme);
        self::assertStringContainsString('IMP-056', $readme);
        self::assertStringContainsString('cancelled/declined history remains hidden', $readme);
    }

    private function source(string $path): string
    {
        $source = file_get_contents(dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $path));
        self::assertNotFalse($source, $path);
        return $source;
    }
}
