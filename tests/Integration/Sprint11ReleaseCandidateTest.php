<?php

namespace EventFlow\Tests\Integration;

use PHPUnit\Framework\TestCase;

final class Sprint11ReleaseCandidateTest extends TestCase
{
    public function testKeyboardAndFormErrorContractsAreExecutable(): void
    {
        $adminScript = $this->source('assets/admin/eventflow-admin.js');
        foreach (['configureTabs', 'ArrowLeft', 'ArrowRight', "keyboardEvent.key === 'Home'", "keyboardEvent.key === 'End'", 'tab.tabIndex = selected ? 0 : -1'] as $expected) {
            self::assertStringContainsString($expected, $adminScript);
        }
        foreach (['reportInvalidControl', "summary.setAttribute('role', 'alert')", "control.setAttribute('aria-invalid', 'true')", "control.setAttribute('aria-describedby'", "root.addEventListener('invalid', reportInvalidControl, true)"] as $expected) {
            self::assertStringContainsString($expected, $adminScript);
        }

        $guestScript = $this->source('assets/guest/eventflow-guest.js');
        foreach (['eventflow-rsvp-error-summary', "summary.setAttribute('role', 'alert')", "form.addEventListener('invalid', reportInvalidControl, true)"] as $expected) {
            self::assertStringContainsString($expected, $guestScript);
        }
    }

    public function testResponsiveFocusContrastAndMotionContractsCoverBothExperiences(): void
    {
        foreach (['assets/admin/eventflow-admin.css', 'assets/guest/eventflow-guest.css'] as $path) {
            $styles = $this->source($path);
            foreach (['@media (max-width: 600px)', ':focus-visible', '@media (prefers-reduced-motion: reduce)', '@media (forced-colors: active)', 'overflow-wrap: anywhere'] as $expected) {
                self::assertStringContainsString($expected, $styles, $path);
            }
            self::assertDoesNotMatchRegularExpression('/url\s*\(\s*["\']?https?:/i', $styles, $path);
        }
    }

    public function testWordPressCompositionKeepsAssetsScopedAndConfigurationMinimal(): void
    {
        $adminHooks = $this->source('src/Presentation/WordPress/WordPressAdminHooks.php');
        self::assertStringContainsString('$hookSuffix !== self::HOOK_SUFFIX', $adminHooks);
        self::assertStringContainsString('assets/admin/eventflow-admin.css', $adminHooks);
        self::assertStringContainsString('assets/admin/eventflow-admin.js', $adminHooks);

        $guestHooks = $this->source('src/Presentation/WordPress/WordPressGuestHooks.php');
        self::assertStringContainsString("SHORTCODE = 'eventflow_rsvp'", $guestHooks);
        self::assertStringContainsString('assets/guest/eventflow-guest.css', $guestHooks);
        self::assertStringContainsString('assets/guest/eventflow-guest.js', $guestHooks);

        foreach (['restUrl', 'version', 'bootstrapState', 'ready'] as $allowed) {
            self::assertStringContainsString($allowed, $guestHooks);
        }
        foreach (['credential', 'csrf', 'sessionToken', 'nonce'] as $forbidden) {
            self::assertStringNotContainsString($forbidden, $guestHooks);
        }
    }

    public function testCandidateDocumentationRetainsTheCiPromotionGate(): void
    {
        $acceptance = $this->source('docs/10-testing/Sprint-11-UI-UX-Acceptance-Report.md');
        foreach (['Result: LOCAL PASS — CI PENDING', 'PHP 8.2 and PHP 8.3 PENDING', 'Stable `1.2.0` metadata', 'v1.2.0-ui-experience'] as $expected) {
            self::assertStringContainsString($expected, $acceptance);
        }

        $release = $this->source('docs/11-releases/1.2.0-sprint-11-ui-experience.md');
        foreach (['**Status:** Release candidate — CI pending', '**Target release tag:** `v1.2.0-ui-experience`', '**Input release:** `v1.1.0-api-completion`', 'EventFlow schema: 15', 'plugin remains `1.2.0-dev`'] as $expected) {
            self::assertStringContainsString($expected, $release);
        }

        $plugin = $this->source('eventflow.php');
        self::assertStringContainsString('Version: 1.2.0-dev', $plugin);
        self::assertStringContainsString("define('EVENTFLOW_VERSION', '1.2.0-dev');", $plugin);
        self::assertStringContainsString("define('EVENTFLOW_SCHEMA_VERSION', 15);", $plugin);
        self::assertStringNotContainsString('## [1.2.0]', $this->source('CHANGELOG.md'));

        $workflow = $this->source('.github/workflows/eventflow-tests.yml');
        foreach (["php: ['8.2', '8.3']", 'composer validate --strict --no-check-publish', 'composer test'] as $expected) {
            self::assertStringContainsString($expected, $workflow);
        }
    }

    private function source(string $path): string
    {
        $source = file_get_contents(dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $path));
        self::assertIsString($source, $path);
        return $source;
    }
}
