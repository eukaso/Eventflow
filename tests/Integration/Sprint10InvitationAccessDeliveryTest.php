<?php

namespace EventFlow\Tests\Integration;

use PHPUnit\Framework\TestCase;

final class Sprint10InvitationAccessDeliveryTest extends TestCase
{
    public function testAcceptedRoutesAreDocumentedAndReadyModeComposed(): void
    {
        $readme = $this->source('README-IMP-052.md');
        foreach ([
            'GET/POST /wp-json/eventflow/v1/events/{event_id}/invitations',
            'GET/PATCH /wp-json/eventflow/v1/events/{event_id}/invitations/{invitation_id}',
            '/archive',
            '/restore',
            'If-Match',
            'Idempotency-Key',
        ] as $expected) {
            self::assertStringContainsString($expected, $readme);
        }
        self::assertStringContainsString(
            'new InvitationAccessRouteRegistrar(',
            $this->source('src/Bootstrap/ApplicationBootstrap.php'),
        );
    }

    public function testControllerDependsOnAcceptedPortAndPresenterProtectsPiiResponses(): void
    {
        $controller = $this->source('src/Presentation/Api/InvitationAccessController.php');
        self::assertStringContainsString('InvitationOperations', $controller);
        self::assertStringNotContainsString('InvitationAccessService', $controller);

        $presenter = $this->source('src/Presentation/Api/InvitationPresenter.php');
        self::assertStringContainsString("'ETag'", $presenter);
        self::assertStringContainsString('no-store, max-age=0', $presenter);
        self::assertStringNotContainsString('token_lookup', $presenter);
    }

    public function testPatchUsesDualMutationPreconditions(): void
    {
        $controller = $this->source('src/Presentation/Api/InvitationAccessController.php');
        self::assertStringContainsString('MutationPreconditionPolicy::IF_MATCH_AND_IDEMPOTENCY_KEY', $controller);
        self::assertStringContainsString('requiredExpectedVersion()', $controller);
        self::assertStringContainsString('requiredIdempotencyKey()', $controller);
    }

    private function source(string $path): string
    {
        $source = file_get_contents(dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $path));
        self::assertNotFalse($source, $path);
        return $source;
    }
}
