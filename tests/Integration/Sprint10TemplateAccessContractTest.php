<?php

namespace EventFlow\Tests\Integration;

use EventFlow\Application\Communication\{TemplateAccess, TemplateAccessService, TemplatePage, TemplateRecord};
use PHPUnit\Framework\TestCase;

final class Sprint10TemplateAccessContractTest extends TestCase
{
    public function testForwardRevisionMigrationAndCatalogueVersionAreAccepted():void
    {
        $migration=$this->source('database/migrations/0012-communication-template-revision.sql');self::assertStringContainsString('template_revision',$migration);self::assertStringNotContainsString('DROP ',$migration);self::assertStringContainsString("define('EVENTFLOW_SCHEMA_VERSION', 12);",$this->source('eventflow.php'));$catalogue=$this->source('src/Infrastructure/Persistence/Migration/CoreMigrationCatalogue.php');self::assertStringContainsString("key: '0012_communication_template_revision'",$catalogue);self::assertStringContainsString('toSchemaVersion: 12',$catalogue);
    }

    public function testNarrowPortProjectionAndSharedFoundationCompositionAreAccepted():void
    {
        self::assertContains(TemplateAccess::class,class_implements(TemplateAccessService::class));self::assertTrue(property_exists(TemplatePage::class,'nextAfterTemplateId'));self::assertTrue(property_exists(TemplateRecord::class,'revision'));$foundation=$this->source('src/Bootstrap/DatabaseFoundation.php');self::assertStringContainsString('public TemplateAccessService $templateAccess',$foundation);self::assertStringContainsString('$communicationRepository',$foundation);self::assertStringContainsString('$templateRenderer',$foundation);
    }

    public function testConcurrencyImmutabilityAuditAndTransportDeferralAreExplicit():void
    {
        $service=$this->source('src/Application/Communication/TemplateAccessService.php');foreach(['Capability::MANAGE_TEMPLATES',"'template.update'","'template.new_version'","'template.archive'","'resource_modified'","'template_immutable'",'templateHasMutableCampaigns','AuditAction::TEMPLATE_UPDATED','AuditAction::TEMPLATE_VERSION_CREATED','AuditAction::TEMPLATE_ARCHIVED']as$boundary)self::assertStringContainsString($boundary,$service);$readme=$this->source('README-IMP-063.md');self::assertStringContainsString('intentionally adds no HTTP routes',$readme);self::assertStringContainsString('IMP-064',$readme);
    }

    private function source(string $path):string{$source=file_get_contents(dirname(__DIR__,2).DIRECTORY_SEPARATOR.str_replace('/',DIRECTORY_SEPARATOR,$path));self::assertNotFalse($source,$path);return $source;}
}
