<?php

namespace EventFlow\Tests\Integration;

use PHPUnit\Framework\TestCase;

final class Sprint9DeliveryValidationTest extends TestCase
{
    private const EVIDENCE = 'catalogues/EventFlow-Delivery-Validation-Evidence-v1.0.csv';
    private const DEFERRALS = 'catalogues/EventFlow-Delivery-Deferred-Routes-v1.0.csv';

    public function testEverySprint9PackageHasPassingExecutableEvidence(): void
    {
        $evidence = $this->csv(self::EVIDENCE);
        self::assertSame(array_map(static fn (int $id): string => sprintf('IMP-%03d', $id), range(28, 42)), array_column($evidence, 'Package'));
        self::assertCount(count(array_unique(array_column($evidence, 'Package'))), $evidence);
        foreach ($evidence as $row) {
            self::assertSame('PASS', $row['Status'], $row['Package']);
            [$file, $method] = explode('::', $row['Evidence'], 2);
            self::assertStringContainsString('function ' . $method . '(', $this->source($file), $row['Evidence']);
        }
    }

    public function testPublicRouteRegistrarsAreAnExplicitMinimalAllowlist(): void
    {
        $directory = $this->root('src/Presentation/Api');
        $public = [];
        foreach (glob($directory . DIRECTORY_SEPARATOR . '*RouteRegistrar.php') ?: [] as $file) {
            if (str_contains((string) file_get_contents($file), 'registerPublic')) $public[] = basename($file);
        }
        sort($public, SORT_STRING);
        self::assertSame([
            'GuestBootstrapRouteRegistrar.php',
            'GuestSessionAccessRouteRegistrar.php',
            'ProviderWebhookRouteRegistrar.php',
            'RsvpRouteRegistrar.php',
            'SystemRouteRegistrar.php',
        ], $public);
    }

    public function testEveryConcreteRegistrarIsComposedAndProductRoutesAreReadyModeGated(): void
    {
        $bootstrap = $this->source('src/Bootstrap/ApplicationBootstrap.php');
        $guard = strpos($bootstrap, 'if ($bootstrap->ready && $container->database !== null)');
        self::assertNotFalse($guard);
        $directory = $this->root('src/Presentation/Api');
        foreach (glob($directory . DIRECTORY_SEPARATOR . '*RouteRegistrar.php') ?: [] as $file) {
            $class = pathinfo($file, PATHINFO_FILENAME);
            if ($class === 'RestRouteRegistrar') continue;
            $position = strpos($bootstrap, 'new ' . $class . '(');
            self::assertNotFalse($position, $class);
            if ($class !== 'SystemRouteRegistrar') self::assertGreaterThan($guard, $position, $class);
        }
    }

    public function testProductControllersDependOnApplicationPortsNotConcreteServices(): void
    {
        $directory = $this->root('src/Presentation/Api');
        foreach (glob($directory . DIRECTORY_SEPARATOR . '*Controller.php') ?: [] as $file) {
            if (basename($file) === 'SystemStatusController.php') continue;
            $source = (string) file_get_contents($file);
            self::assertDoesNotMatchRegularExpression('/use EventFlow\\\\Application\\\\[^;]*Service/', $source, basename($file));
        }
    }

    public function testDeferredCatalogueRoutesHaveControlledContractGapsNotPlaceholderHandlers(): void
    {
        $rows = $this->csv(self::DEFERRALS);
        self::assertSame([
            'Events', 'Venues and configuration', 'Memberships', 'Invitations', 'Attendees', 'Guest session',
            'Seating', 'Communication Templates', 'Campaigns', 'Messages', 'Imports', 'Audit and migrations',
        ], array_column($rows, 'Area'));
        foreach ($rows as $row) {
            self::assertSame('DEFERRED', $row['Status'], $row['Area']);
            self::assertNotSame('', trim($row['Deferred_endpoints']), $row['Area']);
            self::assertNotSame('', trim($row['Reason']), $row['Area']);
            self::assertNotSame('', trim($row['Required_contract']), $row['Area']);
        }
        $api = implode("\n", array_map(static fn (string $file): string => (string) file_get_contents($file), glob($this->root('src/Presentation/Api') . DIRECTORY_SEPARATOR . '*.php') ?: []));
        foreach (['MessageRouteRegistrar', 'AuditRouteRegistrar', 'MigrationRouteRegistrar'] as $placeholder) {
            self::assertStringNotContainsString($placeholder, $api);
        }
    }

    /** @return list<array<string, string>> */
    private function csv(string $path): array
    {
        $handle = fopen($this->root($path), 'rb');
        self::assertIsResource($handle, $path);
        $headers = fgetcsv($handle, escape: '');
        self::assertIsArray($headers, $path);
        $rows = [];
        while (($values = fgetcsv($handle, escape: '')) !== false) {
            if ($values === [null]) continue;
            self::assertCount(count($headers), $values, $path);
            $row = array_combine($headers, $values);
            self::assertIsArray($row, $path);
            $rows[] = $row;
        }
        fclose($handle);
        return $rows;
    }

    private function source(string $path): string
    {
        $source = file_get_contents($this->root($path));
        self::assertNotFalse($source, $path);
        return $source;
    }

    private function root(string $path): string
    {
        return dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $path);
    }
}
