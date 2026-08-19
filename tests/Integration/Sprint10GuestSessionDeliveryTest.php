<?php

namespace EventFlow\Tests\Integration;

use PHPUnit\Framework\TestCase;

final class Sprint10GuestSessionDeliveryTest extends TestCase
{
    public function testGuestSessionRoutesAreComposedInsideReadyMode(): void
    {
        $bootstrap = $this->source('src/Bootstrap/ApplicationBootstrap.php');
        $ready = strpos($bootstrap, 'if ($bootstrap->ready && $container->database !== null)');
        $route = strpos($bootstrap, 'new GuestSessionAccessRouteRegistrar(');
        self::assertNotFalse($ready);
        self::assertNotFalse($route);
        self::assertGreaterThan($ready, $route);
        self::assertStringContainsString('$container->database->guestSessionAccess', $bootstrap);
    }

    public function testGuestReadsAndLogoutHaveSeparatedSecurityContexts(): void
    {
        $factory = $this->source('src/Presentation/Api/GuestRequestContextFactory.php');
        self::assertStringContainsString('function readOnly(', $factory);
        self::assertStringContainsString('function csrfProtected(', $factory);
        $controller = $this->source('src/Presentation/Api/GuestSessionAccessController.php');
        self::assertStringContainsString('->readOnly($request)', $controller);
        self::assertStringContainsString('->csrfProtected($request)', $controller);
        self::assertStringContainsString('requireEmptyBody($request)', $controller);
    }

    public function testResponseCachingAndCookieExpiryAreExplicit(): void
    {
        $presenter = $this->source('src/Presentation/Api/GuestSessionAccessPresenter.php');
        self::assertStringContainsString("'Cache-Control' => 'no-store, max-age=0'", $presenter);
        self::assertStringContainsString("['ETag']", $presenter);
        self::assertStringContainsString('Max-Age=0', $presenter);
        self::assertStringContainsString('Path=/wp-json/eventflow/v1/public; Secure; HttpOnly; SameSite=Lax', $presenter);
    }

    private function source(string $path): string
    {
        $source = file_get_contents(dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $path));
        self::assertNotFalse($source, $path);
        return $source;
    }
}
