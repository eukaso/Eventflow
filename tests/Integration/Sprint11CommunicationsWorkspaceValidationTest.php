<?php

namespace EventFlow\Tests\Integration;

use PHPUnit\Framework\TestCase;

final class Sprint11CommunicationsWorkspaceValidationTest extends TestCase
{
    public function testWorkspaceUsesAcceptedEventScopedCommunicationRoutes(): void
    {
        $script = $this->source('assets/admin/eventflow-admin.js');
        foreach (['/communication-templates', '/preview', "templateAction(template, 'publish')", "templateAction(template, 'new-version')", "templateAction(template, 'archive')", '/campaigns', '/audience-preview', "campaignCommand(campaign, 'schedule'", "campaignCommand(campaign, 'queue'", "campaignCommand(campaign, 'cancel'", '/messages', '/retry'] as $route) {
            self::assertStringContainsString($route, $script);
        }
        self::assertStringNotContainsString('wp_eventflow_', $script);
    }

    public function testMutationsUseCryptographicIdempotencyAndFreshEtagsWhereRequired(): void
    {
        $script = $this->source('assets/admin/eventflow-admin.js');
        self::assertStringContainsString('const communicationMutation', $script);
        self::assertStringContainsString('headers: mutationHeaders(etag)', $script);
        self::assertStringContainsString('const current = await requestJson(path)', $script);
        self::assertStringContainsString('const { payload, etag } = await requestJson', $script);
        self::assertStringContainsString('window.crypto.getRandomValues', $script);
        self::assertStringNotContainsString('Math.random', $script);
    }

    public function testCampaignDeliveryIsDisabledUntilAudiencePreviewAndRequiresConfirmation(): void
    {
        $script = $this->source('assets/admin/eventflow-admin.js');
        foreach (['scheduleButton.disabled = true', 'sendButton.disabled = true', 'scheduleButton.disabled = false', 'sendButton.disabled = reviewedRecipientCount < 1', 'Review the current audience', 'window.confirm(confirmation)'] as $expected) {
            self::assertStringContainsString($expected, $script);
        }
        self::assertStringContainsString('No messages were scheduled or sent.', $script);
    }

    public function testTemplateAndMessageBodiesAreRenderedOnlyAsTextAndExplicitlyCleared(): void
    {
        $script = $this->source('assets/admin/eventflow-admin.js');
        foreach (['templatePreviewBody.textContent', 'messageDetailContent.textContent', 'clearCommunicationDetails()', 'messageDetailContent.textContent = \'\''] as $expected) {
            self::assertStringContainsString($expected, $script);
        }
        foreach (['innerHTML', 'insertAdjacentHTML', 'document.write', 'eval('] as $forbidden) {
            self::assertStringNotContainsString($forbidden, $script);
        }
    }

    public function testIndependentDomainsAndMessageFiltersRemainBounded(): void
    {
        $script = $this->source('assets/admin/eventflow-admin.js');
        self::assertStringContainsString('Promise.allSettled([', $script);
        foreach (['Templates unavailable.', 'Campaigns unavailable.', 'Messages unavailable.'] as $message) {
            self::assertStringContainsString($message, $script);
        }
        self::assertStringContainsString("const messageQuery = ['limit=100']", $script);
        self::assertStringContainsString('messageQuery.push(`campaign_id=${encodeURIComponent(campaignId)}`)', $script);
        self::assertStringContainsString('messageQuery.push(`status=${encodeURIComponent(messageStatus)}`)', $script);
    }

    public function testShellProvidesPersistentLabelsTabsStatusAndResponsiveLayout(): void
    {
        $view = $this->source('src/Presentation/Admin/AdminShellView.php');
        foreach (['role="tablist"', 'role="tabpanel"', 'role="status"', 'Personalization fields', 'Recipient group', 'Filter messages'] as $expected) {
            self::assertStringContainsString($expected, $view);
        }
        $styles = $this->source('assets/admin/eventflow-admin.css');
        self::assertStringContainsString('.eventflow-communication-form', $styles);
        self::assertStringContainsString('@media (max-width: 600px)', $styles);
    }

    private function source(string $path): string
    {
        $source = file_get_contents(dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $path));
        self::assertIsString($source, $path);
        return $source;
    }
}
