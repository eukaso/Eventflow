<?php

namespace EventFlow\Tests\Integration;

use EventFlow\Application\Invitation\{InvitationAccessService, InvitationOperations, InvitationRecord};
use PHPUnit\Framework\TestCase;

final class Sprint10InvitationAccessContractTest extends TestCase
{
    public function testForwardMigrationAndVersionAreDeclared(): void
    {
        $migration = $this->source('database/migrations/0009-invitation-revision.sql');
        self::assertStringContainsString('ADD COLUMN invitation_revision', $migration);
        self::assertStringNotContainsString('DROP ', $migration);
        self::assertStringContainsString("define('EVENTFLOW_SCHEMA_VERSION', 9);", $this->source('eventflow.php'));
    }

    public function testNarrowPortRevisionAndCompositionAreAccepted(): void
    {
        self::assertContains(InvitationOperations::class, class_implements(InvitationAccessService::class));
        self::assertTrue(property_exists(InvitationRecord::class, 'revision'));
        $foundation = $this->source('src/Bootstrap/DatabaseFoundation.php');
        self::assertStringContainsString('public InvitationAccessService $invitationAccess', $foundation);
        self::assertStringContainsString('new WpdbInvitationAccessRepository(', $foundation);
        self::assertStringContainsString(
            'invitation_revision = invitation_revision + 1',
            $this->source('src/Infrastructure/Persistence/WordPress/WpdbAttendeeRepository.php'),
        );
    }

    public function testDeliveryRemainsExplicitlyDeferred(): void
    {
        $readme = $this->source('README-IMP-051.md');
        foreach (['MANAGE_INVITATIONS', 'active Attendee count', 'revoked state', 'IMP-052'] as $expected) {
            self::assertStringContainsString($expected, $readme);
        }
        self::assertStringContainsString('intentionally adds no HTTP routes', $readme);
    }

    private function source(string $path): string
    {
        $source = file_get_contents(dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $path));
        self::assertNotFalse($source, $path);
        return $source;
    }
}
