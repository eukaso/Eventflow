<?php

namespace EventFlow\Tests\Integration;

use PHPUnit\Framework\TestCase;

final class Sprint10ReleaseCandidateTest extends TestCase
{
    public function testCandidateDocumentationRetainsCiPromotionGate(): void
    {
        $acceptance=$this->source('docs/10-testing/Sprint-10-API-Completion-Acceptance-Report.md');
        foreach(['Result: LOCAL PASS — CI PENDING','PHP 8.2 and PHP 8.3 PENDING','Stable `1.1.0` metadata','v1.1.0-api-completion']as$expected)self::assertStringContainsString($expected,$acceptance);

        $release=$this->source('docs/11-releases/1.1.0-sprint-10-api-completion.md');
        foreach(['**Status:** Release candidate — CI pending','**Target release tag:** `v1.1.0-api-completion`','**Input release:** `v1.0.0-delivery-adapters`','EventFlow schema: 15','plugin remains `1.1.0-dev`']as$expected)self::assertStringContainsString($expected,$release);
    }

    public function testStableMetadataCannotAdvanceBeforeCiApproval(): void
    {
        $plugin=$this->source('eventflow.php');
        self::assertStringContainsString('Version: 1.1.0-dev',$plugin);
        self::assertStringContainsString("define('EVENTFLOW_VERSION', '1.1.0-dev');",$plugin);
        self::assertStringContainsString("define('EVENTFLOW_SCHEMA_VERSION', 15);",$plugin);
        self::assertStringNotContainsString('## [1.1.0]',$this->source('CHANGELOG.md'));
    }

    public function testCandidateRetainsEvidenceDeferralAndCiMatrix(): void
    {
        self::assertCount(32,$this->csv('catalogues/EventFlow-Sprint-10-Validation-Evidence-v1.1.csv'));
        self::assertCount(1,$this->csv('catalogues/EventFlow-Sprint-10-Deferred-Routes-v1.1.csv'));
        $workflow=$this->source('.github/workflows/eventflow-tests.yml');
        foreach(["php: ['8.2', '8.3']",'composer validate --strict --no-check-publish','composer test']as$expected)self::assertStringContainsString($expected,$workflow);
    }

    /** @return list<array<string,string>> */
    private function csv(string$path):array{$handle=fopen($this->root($path),'rb');self::assertIsResource($handle);$headers=fgetcsv($handle,escape:'');self::assertIsArray($headers);$rows=[];while(($values=fgetcsv($handle,escape:''))!==false){if($values===[null])continue;$row=array_combine($headers,$values);self::assertIsArray($row);$rows[]=$row;}fclose($handle);return$rows;}
    private function source(string$path):string{$source=file_get_contents($this->root($path));self::assertIsString($source,$path);return$source;}
    private function root(string$path):string{return dirname(__DIR__,2).DIRECTORY_SEPARATOR.str_replace('/',DIRECTORY_SEPARATOR,$path);}
}
