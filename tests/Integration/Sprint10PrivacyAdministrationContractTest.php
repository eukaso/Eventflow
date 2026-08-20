<?php

namespace EventFlow\Tests\Integration;

use EventFlow\Application\Privacy\PrivacyAccess;
use EventFlow\Application\Privacy\PrivacyAccessService;
use EventFlow\Application\Privacy\PrivacyActionPage;
use EventFlow\Application\Privacy\PrivacyCommands;
use EventFlow\Application\Privacy\PrivacyService;
use EventFlow\Application\Privacy\RetentionHoldPage;
use PHPUnit\Framework\TestCase;

final class Sprint10PrivacyAdministrationContractTest extends TestCase
{
    public function testAccessCommandAndPersistenceContractsAreComposed(): void
    {
        self::assertContains(PrivacyAccess::class,class_implements(PrivacyAccessService::class));
        self::assertContains(PrivacyCommands::class,class_implements(PrivacyService::class));
        self::assertTrue(property_exists(PrivacyActionPage::class,'nextAfterActionId'));
        self::assertTrue(property_exists(RetentionHoldPage::class,'nextAfterHoldId'));
        $repository=$this->source('src/Infrastructure/Persistence/WordPress/WpdbPrivacyRepository.php');
        foreach(['PrivacyAccessRepository','listActions','findAction','listHolds','findHold','ORDER BY privacy_action_id ASC','ORDER BY retention_hold_id ASC']as$expected)self::assertStringContainsString($expected,$repository);
        $foundation=$this->source('src/Bootstrap/DatabaseFoundation.php');
        self::assertStringContainsString('public PrivacyAccessService $privacyAccess',$foundation);
        self::assertStringContainsString('$privacyRepository = new WpdbPrivacyRepository',$foundation);
        self::assertStringContainsString('new PrivacyAccessService($privacyRepository, $authorization)',$foundation);
    }

    public function testPrimaryOwnerAuthorizationFiltersMetadataAndHttpDeferralAreExplicit(): void
    {
        $service=$this->source('src/Application/Privacy/PrivacyAccessService.php');
        foreach(['Capability::MANAGE_PRIVACY','privacy_query_invalid','ACTION_STATUSES','ACTION_KINDS','HOLD_STATUSES']as$expected)self::assertStringContainsString($expected,$service);
        self::assertTrue(property_exists(\EventFlow\Application\Privacy\PrivacyActionRecord::class,'requestedAt'));
        self::assertTrue(property_exists(\EventFlow\Application\Privacy\RetentionHoldRecord::class,'placedAt'));
        $readme=$this->source('README-IMP-073.md');
        self::assertStringContainsString('reserved to the Event primary owner',$readme);
        self::assertStringContainsString('IMP-074',$readme);
        self::assertFileDoesNotExist(dirname(__DIR__,2).'/src/Presentation/Api/PrivacyRouteRegistrar.php');
    }

    private function source(string$relative):string{$contents=file_get_contents(dirname(__DIR__,2).'/'.$relative);self::assertIsString($contents);return$contents;}
}
