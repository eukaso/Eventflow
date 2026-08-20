<?php

namespace EventFlow\Tests\Integration;

use PHPUnit\Framework\TestCase;

final class Sprint10ReleasePromotionTest extends TestCase
{
    public function testStableVersionPromotionMatchesAcceptedRelease(): void
    {
        $release=$this->source('docs/11-releases/1.1.0-sprint-10-api-completion.md');
        foreach(['**Status:** Released','**Release tag:** `v1.1.0-api-completion`','run 32360359941','EventFlow schema: 15']as$expected)self::assertStringContainsString($expected,$release);

        $acceptance=$this->source('docs/10-testing/Sprint-10-API-Completion-Acceptance-Report.md');
        self::assertStringContainsString('Result: PASS',$acceptance);
        self::assertStringContainsString('PHP 8.2 and PHP 8.3 PASS',$acceptance);
        self::assertStringContainsString('run 32360359941',$acceptance);
        self::assertStringContainsString('## [1.1.0] - 2026-08-20',$this->source('CHANGELOG.md'));
    }

    public function testReleaseRetainsEvidenceDeferralAndValidatedCiRun(): void
    {
        self::assertCount(32,$this->csv('catalogues/EventFlow-Sprint-10-Validation-Evidence-v1.1.csv'));
        self::assertCount(1,$this->csv('catalogues/EventFlow-Sprint-10-Deferred-Routes-v1.1.csv'));
        $release=$this->source('docs/11-releases/1.1.0-sprint-10-api-completion.md');
        foreach(['catalogues/EventFlow-Sprint-10-Validation-Evidence-v1.1.csv','catalogues/EventFlow-Sprint-10-Deferred-Routes-v1.1.csv','docs/10-testing/Sprint-10-API-Completion-Acceptance-Report.md']as$path){self::assertFileExists($this->root($path));self::assertStringContainsString($path,$release);}
    }

    /** @return list<array<string,string>> */
    private function csv(string$path):array{$handle=fopen($this->root($path),'rb');self::assertIsResource($handle);$headers=fgetcsv($handle,escape:'');self::assertIsArray($headers);$rows=[];while(($values=fgetcsv($handle,escape:''))!==false){if($values===[null])continue;$row=array_combine($headers,$values);self::assertIsArray($row);$rows[]=$row;}fclose($handle);return$rows;}
    private function source(string$path):string{$source=file_get_contents($this->root($path));self::assertIsString($source,$path);return$source;}
    private function root(string$path):string{return dirname(__DIR__,2).DIRECTORY_SEPARATOR.str_replace('/',DIRECTORY_SEPARATOR,$path);}
}
