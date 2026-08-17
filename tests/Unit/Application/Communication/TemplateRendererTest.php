<?php

namespace EventFlow\Tests\Unit\Application\Communication;

use EventFlow\Application\Communication\CommunicationException;
use EventFlow\Application\Communication\TemplateRenderer;
use PHPUnit\Framework\TestCase;

final class TemplateRendererTest extends TestCase
{
    public function testPreviewAlwaysUsesNonFunctionalGuestCredential(): void
    {
        $renderer = new TemplateRenderer();
        $output = $renderer->render(
            'Hello {{recipient_name}} — {{guest_link}}',
            ['recipient_name', 'guest_link'],
            ['recipient_name' => '<Guest>', 'guest_link' => 'https://real.example/secret'],
            true,
        );
        self::assertStringContainsString('&lt;Guest&gt;', $output);
        self::assertStringContainsString(TemplateRenderer::PREVIEW_GUEST_LINK, $output);
        self::assertStringNotContainsString('real.example', $output);
    }

    public function testUnknownMergeFieldsFailClosed(): void
    {
        $this->expectException(CommunicationException::class);
        (new TemplateRenderer())->render('{{unknown_secret}}', ['recipient_name'], []);
    }

    public function testProductionRenderingEscapesValuesAndLeavesNoPlaceholders(): void
    {
        $output = (new TemplateRenderer())->render('Hi {{recipient_name}}', ['recipient_name'], ['recipient_name' => 'A & B']);
        self::assertSame('Hi A &amp; B', $output);
        self::assertStringNotContainsString('{{', $output);
    }
}
