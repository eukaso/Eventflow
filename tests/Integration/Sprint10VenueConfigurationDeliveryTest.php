<?php
namespace EventFlow\Tests\Integration;
use PHPUnit\Framework\TestCase;
final class Sprint10VenueConfigurationDeliveryTest extends TestCase
{
    public function testAcceptedRoutesAreDocumentedAndComposed():void{$readme=$this->source('README-IMP-049.md');foreach(['GET/POST /wp-json/eventflow/v1/venues','GET/PATCH /wp-json/eventflow/v1/venues/{venue_id}','GET/PATCH /wp-json/eventflow/v1/events/{event_id}/configuration','If-Match','Idempotency-Key'] as $expected)self::assertStringContainsString($expected,$readme);$bootstrap=$this->source('src/Bootstrap/ApplicationBootstrap.php');foreach(['new VenueRouteRegistrar(','new EventConfigurationRouteRegistrar('] as $registrar)self::assertStringContainsString($registrar,$bootstrap);}
    public function testControllersDependOnlyOnAcceptedPorts():void{$venue=$this->source('src/Presentation/Api/VenueController.php');$configuration=$this->source('src/Presentation/Api/EventConfigurationController.php');self::assertStringContainsString('VenueOperations',$venue);self::assertStringContainsString('EventConfigurationOperations',$configuration);self::assertStringNotContainsString('VenueService',$venue);self::assertStringNotContainsString('EventConfigurationService',$configuration);}
    public function testPresentersEmitRevisionEtagAndNoStore():void{foreach(['src/Presentation/Api/VenuePresenter.php','src/Presentation/Api/EventConfigurationPresenter.php'] as $file){$source=$this->source($file);self::assertStringContainsString("'ETag'",$source);self::assertStringContainsString('no-store, max-age=0',$source);}}
    private function source(string $path):string{$source=file_get_contents(dirname(__DIR__,2).DIRECTORY_SEPARATOR.str_replace('/',DIRECTORY_SEPARATOR,$path));self::assertNotFalse($source,$path);return $source;}
}
