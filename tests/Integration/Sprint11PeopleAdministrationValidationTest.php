<?php

namespace EventFlow\Tests\Integration;

use PHPUnit\Framework\TestCase;

final class Sprint11PeopleAdministrationValidationTest extends TestCase
{
    public function testPeopleCollectionsAreBoundedAndEventScoped(): void
    {
        $script = $this->script();
        foreach (['requestAllPages(`${eventPath}/memberships`', 'requestAllPages(`${eventPath}/invitations`', 'requestAllPages(`${eventPath}/attendees`'] as $route) {
            self::assertStringContainsString($route, $script);
        }
        self::assertStringContainsString('for (let page = 0; page < 100; page += 1)', $script);
        self::assertStringContainsString('const query = `limit=100', $script);
        self::assertStringContainsString("throw new Error('pagination_limit_exceeded')", $script);
        self::assertStringContainsString("const eventPath = `events/\${encodeURIComponent(String(activeEvent.id))}`", $script);
        self::assertStringContainsString('Promise.allSettled', $script);
        foreach (['Team access unavailable.', 'Invitation access unavailable.', 'Attendee access unavailable.'] as $message) {
            self::assertStringContainsString($message, $script);
        }
    }

    public function testPeopleMutationsUseAcceptedCommandsAndIdempotency(): void
    {
        $script = $this->script();
        foreach (['suspend', 'reactivate', 'revoke', 'archive', 'restore', 'rotate-token', 'activate', 'cancel'] as $command) {
            self::assertStringContainsString($command, $script);
        }
        self::assertStringContainsString("'Idempotency-Key': idempotencyKey()", $script);
        self::assertStringContainsString("method: 'POST'", $script);
        self::assertStringContainsString('invitation_id: Number(', $script);
    }

    public function testInvitationProfileEditReadsEtagBeforeRevisionGuardedPatch(): void
    {
        $script = $this->script();
        self::assertStringContainsString('editingInvitationEtag = result.etag', $script);
        self::assertStringContainsString("method: 'PATCH'", $script);
        self::assertStringContainsString('headers: mutationHeaders(editingInvitationEtag)', $script);
        foreach (['primary_name:', 'primary_email:', 'primary_phone:', 'capacity:', 'organizer_notes:'] as $field) {
            self::assertStringContainsString($field, $script);
        }
    }

    public function testReturnOnceCredentialIsEphemeralAndNeverPersistedOrLogged(): void
    {
        $script = $this->script();
        self::assertStringContainsString('credentialToken.value = token', $script);
        self::assertStringContainsString("credentialToken.value = ''", $script);
        self::assertStringContainsString('window.setTimeout(clearCredential, 300000)', $script);
        self::assertStringContainsString('navigator.clipboard.writeText(credentialToken.value)', $script);
        foreach (['localStorage', 'sessionStorage', 'console.', 'location.hash', 'URLSearchParams'] as $forbidden) {
            self::assertStringNotContainsString($forbidden, $script);
        }
    }

    public function testAuthorizedPiiUsesTextNodesInsteadOfHtmlParsing(): void
    {
        $script = $this->script();
        self::assertStringContainsString('description.textContent', $script);
        self::assertStringContainsString('option.textContent', $script);
        self::assertStringNotContainsString('innerHTML', $script);
        self::assertStringNotContainsString('insertAdjacentHTML', $script);
        self::assertStringNotContainsString('document.write', $script);
    }

    private function script(): string
    {
        $source = file_get_contents(dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'assets' . DIRECTORY_SEPARATOR . 'admin' . DIRECTORY_SEPARATOR . 'eventflow-admin.js');
        self::assertIsString($source);
        return $source;
    }
}
