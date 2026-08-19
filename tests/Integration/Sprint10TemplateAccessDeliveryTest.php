<?php

namespace EventFlow\Tests\Integration;

use PHPUnit\Framework\TestCase;

final class Sprint10TemplateAccessDeliveryTest extends TestCase
{
    public function testCatalogueRoutesAreCompletedOnlyInReadyMode():void
    {
        $routes=$this->source('src/Presentation/Api/TemplateAccessRouteRegistrar.php');self::assertSame(2,substr_count($routes,'registerAuthenticatedGet'));self::assertSame(1,substr_count($routes,'registerAuthenticatedPatch'));self::assertSame(3,substr_count($routes,'registerAuthenticatedPost'));foreach(['/new-version','/archive','/preview']as$route)self::assertStringContainsString($route,$routes);$bootstrap=$this->source('src/Bootstrap/ApplicationBootstrap.php');$ready=strpos($bootstrap,'if ($bootstrap->ready && $container->database !== null)');$registration=strpos($bootstrap,'new TemplateAccessRouteRegistrar(');self::assertNotFalse($ready);self::assertNotFalse($registration);self::assertGreaterThan($ready,$registration);self::assertStringContainsString('$container->database->templateAccess',$bootstrap);
    }
    public function testStrictMappingPreconditionsAndNoStorePresentationAreExplicit():void
    {
        $controller=$this->source('src/Presentation/Api/TemplateAccessController.php');self::assertSame(3,substr_count($controller,'MutationPreconditionPolicy::NONE'));self::assertSame(2,substr_count($controller,'MutationPreconditionPolicy::IF_MATCH_AND_IDEMPOTENCY_KEY'));$mapper=$this->source('src/Presentation/Api/TemplateAccessRequestMapper.php');foreach(["'allowed_fields'","'values'",'requireEmptyBody']as$field)self::assertStringContainsString($field,$mapper);$presenter=$this->source('src/Presentation/Api/TemplateAccessPresenter.php');foreach(["'revision'","'created_at'","'published_at'","'ETag'","'Cache-Control'=>'no-store, max-age=0'"]as$field)self::assertStringContainsString($field,$presenter);
    }
    public function testSchemaAndDeliveryBoundaryAreDocumented():void{$readme=$this->source('README-IMP-064.md');self::assertStringContainsString('existing create and publish commands',$readme);self::assertStringContainsString('If-Match',$readme);self::assertStringContainsString('Idempotency-Key',$readme);self::assertStringContainsString('schema 12',$readme);}
    private function source(string$path):string{$source=file_get_contents(dirname(__DIR__,2).DIRECTORY_SEPARATOR.str_replace('/',DIRECTORY_SEPARATOR,$path));self::assertNotFalse($source,$path);return$source;}
}
