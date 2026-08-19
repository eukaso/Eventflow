<?php

namespace EventFlow\Tests\Integration;

use EventFlow\Application\Membership\{MembershipQueries, MembershipQueryService};
use PHPUnit\Framework\TestCase;

final class Sprint10MembershipQueryDeliveryTest extends TestCase
{
    public function testQueryServicePublishesNarrowPortAndReadyModeComposition(): void
    {
        self::assertContains(MembershipQueries::class, class_implements(MembershipQueryService::class));
        $foundation = $this->source('src/Bootstrap/DatabaseFoundation.php');
        self::assertStringContainsString('public MembershipQueryService $membershipQueries', $foundation);
        self::assertStringContainsString('new WpdbMembershipQueryRepository(', $foundation);
        self::assertStringContainsString('new MembershipQueryRouteRegistrar(', $this->source('src/Bootstrap/ApplicationBootstrap.php'));
    }

    public function testAcceptedLeastPrivilegeRouteIsDocumented(): void
    {
        $readme = $this->source('README-IMP-050.md');
        foreach (['GET /wp-json/eventflow/v1/events/{event_id}/memberships', 'manage_staff_memberships', 'after', 'no-store'] as $expected) {
            self::assertStringContainsString($expected, $readme);
        }
        self::assertStringContainsString('Capability::MANAGE_STAFF_MEMBERSHIPS', $this->source('src/Application/Membership/MembershipQueryService.php'));
    }

    private function source(string $path): string
    {
        $source = file_get_contents(dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $path));
        self::assertNotFalse($source, $path);
        return $source;
    }
}
