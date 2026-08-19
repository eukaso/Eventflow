<?php
namespace EventFlow\Tests\Integration;
use PHPUnit\Framework\TestCase;
final class Sprint10CampaignAccessDeliveryTest extends TestCase
{
    public function testCampaignAccessRoutesAreCompleteAndBootstrapped():void
    {
        $routes=$this->source('src/Presentation/Api/CampaignAccessRouteRegistrar.php');self::assertSame(2,substr_count($routes,'registerAuthenticatedGet'));self::assertSame(1,substr_count($routes,'registerAuthenticatedPatch'));self::assertSame(3,substr_count($routes,'registerAuthenticatedPost'));foreach(['/audience-preview','/schedule','/cancel']as$route)self::assertStringContainsString($route,$routes);
        $bootstrap=$this->source('src/Bootstrap/ApplicationBootstrap.php');$ready=strpos($bootstrap,'if ($bootstrap->ready && $container->database !== null)');$registration=strpos($bootstrap,'new CampaignAccessRouteRegistrar(');self::assertNotFalse($ready);self::assertNotFalse($registration);self::assertGreaterThan($ready,$registration);self::assertStringContainsString('$container->database->campaignAccess',$bootstrap);
    }
    public function testRevisionPrivacyAndStrictMappingAreExplicit():void
    {
        $controller=$this->source('src/Presentation/Api/CampaignAccessController.php');self::assertSame(3,substr_count($controller,'MutationPreconditionPolicy::IF_MATCH_AND_IDEMPOTENCY_KEY'));self::assertSame(3,substr_count($controller,'MutationPreconditionPolicy::NONE'));
        $mapper=$this->source('src/Presentation/Api/CampaignAccessRequestMapper.php');foreach(["'scheduled_at'","'audience_mode'",'requireEmptyBody','DateTimeImmutable']as$field)self::assertStringContainsString($field,$mapper);
        $presenter=$this->source('src/Presentation/Api/CampaignAccessPresenter.php');foreach(["'revision'","'audience_fingerprint'","'recipient_count'","'ETag'","'Cache-Control'=>'no-store, max-age=0'"]as$field)self::assertStringContainsString($field,$presenter);
        $readme=$this->source('README-IMP-066.md');self::assertStringContainsString('privacy-minimized',$readme);self::assertStringContainsString('Existing create and queue routes remain authoritative',$readme);
    }
    private function source(string$relative):string{$root=dirname(__DIR__,2);$contents=file_get_contents($root.'/'.$relative);self::assertIsString($contents);return$contents;}
}
