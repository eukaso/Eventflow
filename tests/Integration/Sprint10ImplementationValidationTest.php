<?php

namespace EventFlow\Tests\Integration;

use PHPUnit\Framework\TestCase;

final class Sprint10ImplementationValidationTest extends TestCase
{
    private const EVIDENCE='catalogues/EventFlow-Sprint-10-Validation-Evidence-v1.1.csv';
    private const DEFERRALS='catalogues/EventFlow-Sprint-10-Deferred-Routes-v1.1.csv';

    public function testEverySprint10PackageHasPassingExecutableEvidence(): void
    {
        $rows=$this->csv(self::EVIDENCE);
        self::assertSame(array_map(static fn(int$id):string=>sprintf('IMP-%03d',$id),range(46,77)),array_column($rows,'Package'));
        self::assertCount(32,$rows);
        self::assertCount(32,array_unique(array_column($rows,'Evidence')));
        foreach($rows as$row){
            self::assertSame('PASS',$row['Status'],$row['Package']);
            [$file,$method]=explode('::',$row['Evidence'],2);
            self::assertFileExists($this->root($file),$row['Package']);
            self::assertStringContainsString('function '.$method.'(',$this->source($file),$row['Evidence']);
            self::assertFileExists($this->root('README-'.$row['Package'].'.md'),$row['Package']);
        }
    }

    public function testForwardSchemaChainAndStableMetadataAreAccepted(): void
    {
        $plugin=$this->source('eventflow.php');
        self::assertMatchesRegularExpression("/Version: (?:1\\.1\\.0|1\\.[2-9]\\.[0-9]+-dev)/",$plugin);
        self::assertMatchesRegularExpression("/define\\('EVENTFLOW_VERSION', '(?:1\\.1\\.0|1\\.[2-9]\\.[0-9]+-dev)'\\);/",$plugin);
        self::assertStringContainsString("define('EVENTFLOW_SCHEMA_VERSION', 15);",$plugin);
        foreach(range(7,15)as$version){
            $matches=glob($this->root('database/migrations/'.sprintf('%04d',$version).'-*.sql'))?:[];
            self::assertCount(1,$matches,'migration '.$version);
            $sql=(string)file_get_contents($matches[0]);
            self::assertStringNotContainsString('DROP ',$sql,basename($matches[0]));
            self::assertStringNotContainsString('TRUNCATE ',$sql,basename($matches[0]));
        }
    }

    public function testCompletedCatalogueSurfaceLeavesOnlyMigrationStatusDeferred(): void
    {
        $rows=$this->csv(self::DEFERRALS);
        self::assertSame(['Migration status'],array_column($rows,'Area'));
        self::assertSame(['DEFERRED'],array_column($rows,'Status'));
        self::assertStringContainsString('/system/migrations',$rows[0]['Deferred_endpoints']);

        $audit=$this->source('src/Presentation/Api/AuditRouteRegistrar.php');
        self::assertStringContainsString("'/events/(?P<event_id>\\d+)/audit'",$audit);
        $api=implode("\n",array_map(static fn(string$file):string=>(string)file_get_contents($file),glob($this->root('src/Presentation/Api/*.php'))?:[]));
        self::assertStringNotContainsString('MigrationRouteRegistrar',$api);
    }

    public function testSecurityCriticalDeliveryBoundariesRemainExecutable(): void
    {
        foreach([
            'src/Infrastructure/Import/HardenedImportUploadGuard.php'=>['isUploaded','MAX_BYTES'],
            'src/Infrastructure/Export/WordPressProtectedExportStorage.php'=>['realpath','hash_equals'],
            'src/Presentation/Api/AuditPresenter.php'=>['private, no-store','failure_code'],
            'src/Application/Observability/DiagnosticService.php'=>['redactor->redact','diagnostic_source_failed'],
        ]as$file=>$expectations){$source=$this->source($file);foreach($expectations as$expected)self::assertStringContainsString($expected,$source,$file);}
        self::assertStringNotContainsString('raw_log',$this->source('src/Presentation/Api/DiagnosticController.php'));
    }

    /** @return list<array<string,string>> */
    private function csv(string$path):array{$handle=fopen($this->root($path),'rb');self::assertIsResource($handle,$path);$headers=fgetcsv($handle,escape:'');self::assertIsArray($headers);$rows=[];while(($values=fgetcsv($handle,escape:''))!==false){if($values===[null])continue;self::assertCount(count($headers),$values,$path);$row=array_combine($headers,$values);self::assertIsArray($row);$rows[]=$row;}fclose($handle);return$rows;}
    private function source(string$path):string{$source=file_get_contents($this->root($path));self::assertIsString($source,$path);return$source;}
    private function root(string$path):string{return dirname(__DIR__,2).DIRECTORY_SEPARATOR.str_replace('/',DIRECTORY_SEPARATOR,$path);}
}
