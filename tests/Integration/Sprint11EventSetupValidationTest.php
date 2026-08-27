<?php

namespace EventFlow\Tests\Integration;

use PHPUnit\Framework\TestCase;

final class Sprint11EventSetupValidationTest extends TestCase
{
    public function testSetupUsesAcceptedEventVenueAndConfigurationRoutes(): void
    {
        $script = $this->source('assets/admin/eventflow-admin.js');
        self::assertStringContainsString("requestJson(`events/\${encodeURIComponent(String(eventId))}`", $script);
        self::assertStringContainsString("requestJson(`events/\${eventId}/configuration`)", $script);
        self::assertStringContainsString("requestJson('venues?limit=100')", $script);
        self::assertStringContainsString("requestJson('venues'", $script);
    }

    public function testRevisionGuardedFormsPreserveRequiredMutationHeaders(): void
    {
        $script = $this->source('assets/admin/eventflow-admin.js');
        self::assertStringContainsString("headers['If-Match'] = etag", $script);
        self::assertStringContainsString("'Idempotency-Key': idempotencyKey()", $script);
        self::assertStringContainsString("headers: mutationHeaders(activeEventEtag)", $script);
        self::assertStringContainsString("headers: mutationHeaders(activeConfigurationEtag)", $script);
        self::assertStringContainsString("headers: mutationHeaders()", $script);
    }

    public function testSetupSerializesOnlyAcceptedFieldsAndNeverParsesApiHtml(): void
    {
        $script = $this->source('assets/admin/eventflow-admin.js');
        foreach (['name:', 'slug:', 'timezone:', 'starts_at:', 'ends_at:', 'venue_id:', 'invitation_media_id:', 'welcome_message:', 'confirmation_message:', 'confirmation_opens_at:', 'confirmation_closes_at:', 'seating_mode:', 'allow_guest_edits:', 'automatic_seating_enabled:', 'address_line_1:', 'address_line_2:', 'region:', 'postal_code:', 'country_code:', 'default_capacity:'] as $field) {
            self::assertStringContainsString($field, $script);
        }
        self::assertStringContainsString('textContent', $script);
        self::assertStringNotContainsString('innerHTML', $script);
        self::assertStringNotContainsString('insertAdjacentHTML', $script);
    }

    public function testReadOnlyAndReconciliationStatesAreExplicit(): void
    {
        $script = $this->source('assets/admin/eventflow-admin.js');
        self::assertStringContainsString("event.status !== 'draft'", $script);
        self::assertStringContainsString('Promise.allSettled', $script);
        self::assertStringContainsString('await refreshSetup(eventId', $script);
        self::assertStringContainsString('Refresh before retrying', $script);
        self::assertStringContainsString('Venue access is unavailable.', $script);
        self::assertStringContainsString('resolveConfiguredInvitationImage', $script);
    }

    private function source(string $path): string
    {
        $source = file_get_contents(dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $path));
        self::assertIsString($source, $path);
        return $source;
    }
}
