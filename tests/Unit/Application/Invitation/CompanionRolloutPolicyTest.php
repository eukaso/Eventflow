<?php

namespace EventFlow\Tests\Unit\Application\Invitation;

use EventFlow\Application\Import\ImportMapping;
use EventFlow\Application\Import\ImportNormalizer;
use EventFlow\Application\Invitation\CompanionRolloutPolicy;
use EventFlow\Application\Invitation\CreateInvitation;
use EventFlow\Application\Persistence\EventScope;
use PHPUnit\Framework\TestCase;

final class CompanionRolloutPolicyTest extends TestCase
{
    public function testNewInvitationDefaultsToOneCompanionPlace(): void
    {
        $invitation = new CreateInvitation(new EventScope(1), 'Primary Guest');

        self::assertSame(2, CompanionRolloutPolicy::DEFAULT_TOTAL_CAPACITY);
        self::assertSame(2, $invitation->capacity);
    }

    public function testImportGivesEveryGuestExactlyOneCompanionPlace(): void
    {
        $normalizer = new ImportNormalizer();
        $mapping = new ImportMapping(['primary_name' => 'Name', 'capacity' => 'Seats']);

        foreach (['1', '2', '7'] as $requestedCapacity) {
            $result = $normalizer->normalize(['Name' => 'Guest', 'Seats' => $requestedCapacity], $mapping);

            self::assertSame([], $result['errors']);
            self::assertSame(2, $result['normalized']['capacity']);
        }
    }

    public function testAdminCanStillCreateApprovedFamilyException(): void
    {
        $invitation = new CreateInvitation(new EventScope(1), 'Approved Family', 5);

        self::assertSame(5, $invitation->capacity);
    }
}
