<?php

namespace EventFlow\Tests\Integration;

use PHPUnit\Framework\TestCase;

final class Sprint12OrganizerInvitationWorkspaceValidationTest extends TestCase
{
    public function testOrganizerGetsOneScreenComposerTestRecipientReviewAndSendFlow(): void
    {
        $view = $this->source('src/Presentation/Admin/AdminShellView.php');
        foreach ([
            'Create, test, and send from one screen',
            'Invitation-card image',
            'Official invitation card',
            'Choose official card',
            'Send yourself a test',
            'Choose recipients',
            'Canada/US (+1)',
            'International (not +1)',
            'Review recipients',
            'Send invitations',
            'Advanced communication records and scheduling',
        ] as $text) {
            self::assertStringContainsString($text, $view);
        }
    }

    public function testQuickFlowUsesExistingProtectedTemplateCampaignAndAudienceContracts(): void
    {
        $script = $this->source('assets/admin/eventflow-admin.js');
        foreach ([
            '/communication-templates',
            '/activate',
            '/campaigns',
            '/audience-preview',
            '/queue',
            "audience_mode: 'snapshot'",
            'selectedInvitationRecipients',
            'window.confirm(`Send this personalized',
            'Nothing has been sent yet.',
            'Error: ${error.code}.',
            'Path: ${error.path}.',
            'Response: ${error.status',
            "payload.code === 'rest_no_route'",
            "cache: 'no-store'",
            'eventflow_no_cache=',
            "verification.payload.data?.status !== 'published'",
        ] as $contract) {
            self::assertStringContainsString($contract, $script);
        }
        self::assertStringContainsString('invitationSend.disabled = true', $script);
    }

    public function testPersonalTestMessageIsIsolatedAuditedAndDispatchedThroughWorker(): void
    {
        $service = $this->source('src/Application/Communication/MessageAccessService.php');
        foreach ([
            "'message.send_test'",
            "'message.delivery.test'",
            'Capability::QUEUE_CAMPAIGN',
            'AuditAction::MESSAGE_TEST_QUEUED',
            'createTestMessage',
            'assertEnabled($provider)',
        ] as $contract) {
            self::assertStringContainsString($contract, $service);
        }
        $repository = $this->source('src/Infrastructure/Persistence/WordPress/WpdbCommunicationRepository.php');
        self::assertStringContainsString('campaign_id,invitation_id,attendee_id', $repository);
        self::assertStringContainsString('VALUES (%d,NULL,NULL,NULL', $repository);
        $foundation = $this->source('src/Bootstrap/DatabaseFoundation.php');
        self::assertStringContainsString("new MessageDeliveryJobHandler(\$providerService, 'message.delivery.test')", $foundation);
    }

    public function testMediaPickerAndTextOnlyAdminRenderingRemainSafe(): void
    {
        $hooks = $this->source('src/Presentation/WordPress/WordPressAdminHooks.php');
        self::assertStringContainsString('wp_enqueue_media()', $hooks);
        $script = $this->source('assets/admin/eventflow-admin.js');
        self::assertStringContainsString('escapeInvitationHtml', $script);
        self::assertStringContainsString("library: { type: 'image' }", $script);
        foreach (['innerHTML', 'insertAdjacentHTML', 'document.write', 'eval('] as $forbidden) {
            self::assertStringNotContainsString($forbidden, $script);
        }
        self::assertStringContainsString("/confirm/#eventflow-preview=1", $script);
        self::assertStringContainsString('September 2, 2026', $script);
        self::assertStringContainsString('font-size:18px;font-weight:700', $script);
        self::assertStringContainsString('`${html}<p style="margin-top:24px;">', $script);
        self::assertStringContainsString('invitationCardImageFromTemplates', $script);
        self::assertStringContainsString("new DOMParser().parseFromString(String(template.body), 'text/html')", $script);
        self::assertStringContainsString("candidate.alt || ''", $script);
        self::assertStringContainsString('officialInvitationCardRequired', $script);
        self::assertStringContainsString('Choose the official invitation-card image before sending this event email.', $script);
        self::assertStringContainsString('officialInvitationImageUrl', $script);
        self::assertStringContainsString("title: 'Choose official invitation card'", $script);
    }

    private function source(string $path): string
    {
        $source = file_get_contents(dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $path));
        self::assertIsString($source, $path);
        return $source;
    }
}
