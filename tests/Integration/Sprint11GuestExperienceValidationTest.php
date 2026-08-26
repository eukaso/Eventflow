<?php

namespace EventFlow\Tests\Integration;

use PHPUnit\Framework\TestCase;

final class Sprint11GuestExperienceValidationTest extends TestCase
{
    public function testCredentialComesOnlyFromFragmentAndHistoryIsCleanedBeforeBootstrap(): void
    {
        $script = $this->source('assets/guest/eventflow-guest.js');
        self::assertStringContainsString('window.location.hash', $script);
        self::assertStringContainsString("parameters.get('eventflow-invitation')", $script);
        self::assertStringContainsString("await bootstrap(invitationCredential, 'message_link')", $script);
        self::assertStringContainsString("await bootstrap(invitationCredential, 'invitation')", $script);
        self::assertStringContainsString("openingPhase === 'bootstrap'", $script);
        self::assertStringContainsString('Your secure link was accepted, but the invitation details could not be loaded.', $script);
        self::assertStringContainsString('This secure session has expired. Reopen your original invitation email and click the personalized link again.', $script);
        self::assertStringContainsString('window.history.replaceState', $script);
        self::assertStringContainsString('/^[a-f0-9]{64}$/', $script);
        $clean = strpos($script, 'const invitationCredential = cleanCredentialFragment()');
        $bootstrap = strpos($script, "await bootstrap(invitationCredential, 'message_link')");
        self::assertIsInt($clean);
        self::assertIsInt($bootstrap);
        self::assertLessThan($bootstrap, $clean);
        self::assertStringNotContainsString("searchParams.get('eventflow", $script);
        self::assertStringContainsString("get('eventflow-preview') === '1'", $script);
        self::assertStringContainsString('Test invitation link verified.', $script);
        self::assertStringContainsString('This safe preview does not save an RSVP', $script);
    }

    public function testGuestSecretsRemainOutOfStorageCookiesAndLocalizedConfiguration(): void
    {
        $script = $this->source('assets/guest/eventflow-guest.js');
        foreach (['localStorage', 'sessionStorage', 'document.cookie', 'console.', 'location.href =', 'location.search ='] as $forbidden) {
            self::assertStringNotContainsString($forbidden, $script);
        }
        $hooks = $this->source('src/Presentation/WordPress/WordPressGuestHooks.php');
        foreach (['credential', 'csrf', 'sessionToken', 'nonce'] as $forbidden) {
            self::assertStringNotContainsString($forbidden, $hooks);
        }
        foreach (['restUrl', 'version', 'bootstrapState', 'ready'] as $allowed) {
            self::assertStringContainsString($allowed, $hooks);
        }
    }

    public function testRsvpMutationCarriesEveryAcceptedSecurityPrecondition(): void
    {
        $script = $this->source('assets/guest/eventflow-guest.js');
        foreach (["method: 'PUT'", "'Idempotency-Key': idempotencyKey()", "'If-Match': responseEtag", "'X-EventFlow-CSRF': csrfToken", "credentials: 'same-origin'", "cache: 'no-store'"] as $expected) {
            self::assertStringContainsString($expected, $script);
        }
        self::assertStringContainsString('window.crypto.getRandomValues', $script);
        self::assertStringNotContainsString('Math.random', $script);
    }

    public function testGuestSessionReadsAreSequentialAfterBootstrap(): void
    {
        $script = $this->source('assets/guest/eventflow-guest.js');
        $context = strpos($script, "const contextResult = await request('public/invitation')");
        $response = strpos($script, "const responseResult = await request('public/invitation/response')");

        self::assertIsInt($context);
        self::assertIsInt($response);
        self::assertLessThan($response, $context);
        self::assertStringNotContainsString("Promise.all([\n      request('public/invitation')", $script);
    }

    public function testRsvpPayloadPreservesIdentityCapacityAndDeclineRules(): void
    {
        $script = $this->source('assets/guest/eventflow-guest.js');
        foreach (['attendee_id:', 'display_name:', 'role:', 'email:', 'phone:', 'dietary_requirements:', 'accessibility_requirements:'] as $field) {
            self::assertStringContainsString($field, $script);
        }
        self::assertStringContainsString("responseStatus === 'accepted' ? attendeePayload() : []", $script);
        self::assertStringContainsString('attendeeList.children.length >= Number(invitationContext?.capacity || 1)', $script);
        self::assertStringContainsString("attendeeRow({ display_name: context.primary_name }, 'primary')", $script);
    }

    public function testPublicRenderingIsAccessibleAndNeverParsesApiContentAsHtml(): void
    {
        $script = $this->source('assets/guest/eventflow-guest.js');
        self::assertStringContainsString('textContent', $script);
        foreach (['innerHTML', 'insertAdjacentHTML', 'document.write', 'eval('] as $forbidden) {
            self::assertStringNotContainsString($forbidden, $script);
        }
        $view = $this->source('src/Presentation/Guest/GuestShellView.php');
        foreach (['aria-busy="true"', 'role="status"', 'Will you attend?', 'Tell us who is coming'] as $expected) {
            self::assertStringContainsString($expected, $view);
        }
    }

    public function testShortcodeAndAssetsAreComposedFromApplicationBootstrap(): void
    {
        $hooks = $this->source('src/Presentation/WordPress/WordPressGuestHooks.php');
        self::assertStringContainsString("SHORTCODE = 'eventflow_rsvp'", $hooks);
        self::assertStringContainsString('add_shortcode', $hooks);
        self::assertStringContainsString('assets/guest/eventflow-guest.js', $hooks);
        self::assertStringContainsString('assets/guest/eventflow-guest.css', $hooks);
        self::assertStringContainsString('hash_file(\'sha256\', $absolutePath)', $hooks);
        self::assertStringContainsString('$this->version . \'-\' . substr($digest, 0, 12)', $hooks);
        $bootstrap = $this->source('src/Bootstrap/ApplicationBootstrap.php');
        self::assertStringContainsString('new WordPressGuestHooks(', $bootstrap);
        self::assertStringContainsString('new GuestShellView()', $bootstrap);
    }

    private function source(string $path): string
    {
        $source = file_get_contents(dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $path));
        self::assertIsString($source, $path);
        return $source;
    }
}
