<?php

namespace EventFlow\Tests\Unit\Presentation\Api;

use DateTimeImmutable;
use DateTimeZone;
use EventFlow\Application\Authorization\PrincipalContext;
use EventFlow\Application\CheckIn\{BulkCheckInResult, CheckInAction, CheckInCommands, CheckInMethod, ReceptionAttendee, ReceptionSearch};
use EventFlow\Application\Error\RequestIdFactory;
use EventFlow\Application\Idempotency\{IdempotencyOutcome, IdempotencyResultReference};
use EventFlow\Application\Persistence\EventScope;
use EventFlow\Application\Security\SecureRandom;
use EventFlow\Presentation\Api\{ApiResponse, AuthenticatedPrincipalResolver, AuthenticatedRequestContextFactory, CheckInController, CheckInPresenter, CheckInRequestMapper, CheckInRouteRegistrar, RequestInputException, RestRequest, RestRouteRegistry};
use PHPUnit\Framework\TestCase;

final class CheckInControllerTest extends TestCase
{
    public function testRegistrarExposesCompleteApprovedSurface(): void
    {
        $routes = new CheckInMemoryRoutes();
        (new CheckInRouteRegistrar($this->controller(new CheckInPort())))->register($routes);
        self::assertSame([
            'GET eventflow/v1/events/(?P<event_id>\d+)/reception/attendees',
            'GET eventflow/v1/events/(?P<event_id>\d+)/reception/lookup',
            'POST eventflow/v1/events/(?P<event_id>\d+)/check-ins',
            'POST eventflow/v1/events/(?P<event_id>\d+)/check-ins/bulk',
            'POST eventflow/v1/events/(?P<event_id>\d+)/check-ins/(?P<checkin_id>\d+)/reverse',
        ], $routes->registered);
    }

    public function testSearchMapsQueryAndReturnsOnlyReceptionProjection(): void
    {
        $port = new CheckInPort();
        $response = $this->controller($port)->search(new RestRequest(
            routeParameters: ['event_id' => '9'], queryParameters: ['q' => ' Guest ', 'limit' => '12'],
        ));
        self::assertSame(['Guest', 12], $port->searchInput);
        self::assertSame('Guest One', $response->body()['data'][0]['display_name']);
        self::assertSame(str_repeat('a', 64), $response->body()['data'][0]['lookup_code']);
        self::assertArrayNotHasKey('email', $response->body()['data'][0]);
        self::assertSame('no-store, max-age=0', $response->headers()['Cache-Control']);
    }

    public function testIndividualCheckInMapsCommandAndPresentsAction(): void
    {
        $port = new CheckInPort();
        $response = $this->controller($port)->checkIn(new RestRequest(
            ['Idempotency-Key' => 'checkin-key-001'],
            ['attendee_id' => 71, 'station_id' => 3, 'method' => 'search', 'notes' => 'Arrived'],
            ['event_id' => '9'],
        ));
        self::assertSame([[71], 3, CheckInMethod::SEARCH, 'checkin-key-001', 'Arrived'], $port->checkInInput);
        self::assertSame(201, $response->status());
        self::assertSame(81, $response->body()['data']['id']);
        self::assertSame('search', $response->body()['data']['method']);
        self::assertSame('/wp-json/eventflow/v1/events/9/check-ins/81', $response->headers()['Location']);
    }

    public function testQrLookupMapsCredentialAndReturnsDuplicateSafeState(): void
    {
        $port = new CheckInPort();
        $response = $this->controller($port)->lookup(new RestRequest(
            routeParameters: ['event_id' => '9'], queryParameters: ['code' => strtoupper(str_repeat('a', 64))],
        ));
        self::assertSame(str_repeat('a', 64), $port->lookupInput);
        self::assertSame('Guest One', $response->body()['data']['display_name']);
        self::assertFalse($response->body()['data']['checked_in']);
        self::assertSame('no-store, max-age=0', $response->headers()['Cache-Control']);
    }

    public function testBulkPreservesInputForAuthoritativeAtomicService(): void
    {
        $port = new CheckInPort();
        $response = $this->controller($port)->bulk(new RestRequest(
            ['Idempotency-Key' => 'checkin-bulk-001'],
            ['attendee_ids' => [72, 71], 'method' => 'guest_list'],
            ['event_id' => '9'],
        ));
        self::assertSame([[72, 71], null, CheckInMethod::GUEST_LIST, 'checkin-bulk-001', null], $port->bulkInput);
        self::assertSame('operation-001', $response->body()['data']['operation_id']);
        self::assertCount(2, $response->body()['data']['actions']);
    }

    public function testReversalMapsRouteReasonAndIdempotency(): void
    {
        $port = new CheckInPort();
        $response = $this->controller($port)->reverse(new RestRequest(
            ['Idempotency-Key' => 'reverse-key-001'], ['reason' => 'Wrong guest'], ['event_id' => '9', 'checkin_id' => '81'],
        ));
        self::assertSame([81, 'Wrong guest', 'reverse-key-001'], $port->reverseInput);
        self::assertSame('reversal', $response->body()['data']['action_type']);
        self::assertSame(81, $response->body()['data']['reversal_of']);
    }

    public function testInvalidQueryBodiesMethodsAndRoutesFailBeforePort(): void
    {
        $port = new CheckInPort();
        foreach ([
            fn () => $this->controller($port)->search(new RestRequest(routeParameters: ['event_id' => '9'])),
            fn () => $this->controller($port)->search(new RestRequest(routeParameters: ['event_id' => '9'], queryParameters: ['q' => 'Guest', 'limit' => '51'])),
            fn () => $this->controller($port)->lookup(new RestRequest(routeParameters: ['event_id' => '9'], queryParameters: ['code' => 'not-a-qr-code'])),
            fn () => $this->controller($port)->checkIn(new RestRequest(['Idempotency-Key' => 'checkin-key-002'], ['attendee_id' => '71', 'method' => 'search'], ['event_id' => '9'])),
            fn () => $this->controller($port)->checkIn(new RestRequest(['Idempotency-Key' => 'checkin-key-003'], ['attendee_id' => 71, 'method' => 'unknown'], ['event_id' => '9'])),
            fn () => $this->controller($port)->bulk(new RestRequest(['Idempotency-Key' => 'checkin-key-004'], ['attendee_ids' => [71], 'method' => 'manual', 'admin' => true], ['event_id' => '9'])),
            fn () => $this->controller($port)->reverse(new RestRequest(['Idempotency-Key' => 'reverse-key-002'], ['reason' => 'x'], ['event_id' => '9', 'checkin_id' => '../81'])),
        ] as $operation) {
            try { $operation(); self::fail('Expected controlled input failure.'); }
            catch (RequestInputException $failure) { self::assertContains($failure->safeCode, ['validation_failed', 'resource_not_found']); }
        }
        self::assertSame(0, $port->calls);
    }

    private function controller(CheckInPort $port): CheckInController
    {
        return new CheckInController(
            $port,
            $port,
            new AuthenticatedRequestContextFactory(new CheckInPrincipalResolver(), new RequestIdFactory(new CheckInRandom())),
            new CheckInRequestMapper(),
            new CheckInPresenter(),
        );
    }
}

final class CheckInMemoryRoutes implements RestRouteRegistry
{
    public array $registered = [];
    public function registerPublicGet(string $namespace, string $route, callable $handler): void {}
    public function registerPublicPost(string $namespace, string $route, callable $handler): void {}
    public function registerPublicPut(string $namespace, string $route, callable $handler): void {}
    public function registerAuthenticatedGet(string $namespace, string $route, callable $handler): void { $this->registered[] = 'GET ' . $namespace . $route; }
    public function registerAuthenticatedPost(string $namespace, string $route, callable $handler): void { $this->registered[] = 'POST ' . $namespace . $route; }
    public function registerAuthenticatedPatch(string $namespace, string $route, callable $handler): void {}
}

final readonly class CheckInPrincipalResolver implements AuthenticatedPrincipalResolver
{
    public function resolve(RestRequest $request): PrincipalContext { return PrincipalContext::wordpressUser(7); }
}

final readonly class CheckInRandom implements SecureRandom
{
    public function hex(int $bytes): string { return str_repeat('7', $bytes * 2); }
}

final class CheckInPort implements ReceptionSearch, CheckInCommands
{
    public int $calls = 0;
    public array $searchInput = [];
    public ?string $lookupInput = null;
    public array $checkInInput = [];
    public array $bulkInput = [];
    public array $reverseInput = [];

    public function search(PrincipalContext $principal, EventScope $scope, string $query, int $limit = 20): array
    {
        $this->calls++; $this->searchInput = [$query, $limit];
        return [new ReceptionAttendee(71, 'Guest One', 'confirmed', 'Table 1', 'A', false, null, str_repeat('a', 64))];
    }

    public function lookup(PrincipalContext $principal, EventScope $scope, string $code): ReceptionAttendee
    {
        $this->calls++; $this->lookupInput = $code;
        return new ReceptionAttendee(71, 'Guest One', 'confirmed', 'Table 1', 'A', false, null, $code);
    }

    public function checkIn(PrincipalContext $principal, EventScope $scope, int $attendeeId, ?int $stationId, CheckInMethod $method, string $key, ?string $notes = null): IdempotencyOutcome
    {
        $this->calls++; $this->checkInInput = [[$attendeeId], $stationId, $method, $key, $notes];
        $action = $this->action(81, $attendeeId, $method, $stationId, null, 'operation-001');
        return new IdempotencyOutcome(false, new IdempotencyResultReference('check_in', 81, 201), new BulkCheckInResult('operation-001', [$action]));
    }

    public function bulk(PrincipalContext $principal, EventScope $scope, array $attendeeIds, ?int $stationId, CheckInMethod $method, string $key, ?string $notes = null): IdempotencyOutcome
    {
        $this->calls++; $this->bulkInput = [$attendeeIds, $stationId, $method, $key, $notes];
        $actions = [$this->action(81, 72, $method, $stationId), $this->action(82, 71, $method, $stationId)];
        return new IdempotencyOutcome(false, new IdempotencyResultReference('checkin_operation', 9, 201), new BulkCheckInResult('operation-001', $actions));
    }

    public function reverse(PrincipalContext $principal, EventScope $scope, int $checkInId, string $reason, string $key): IdempotencyOutcome
    {
        $this->calls++; $this->reverseInput = [$checkInId, $reason, $key];
        $action = $this->action(91, 71, CheckInMethod::MANUAL, 3, $checkInId);
        return new IdempotencyOutcome(false, new IdempotencyResultReference('check_in', 91, 201), $action);
    }

    private function action(int $id, int $attendeeId, CheckInMethod $method, ?int $stationId, ?int $reversalOf = null, ?string $operationId = 'operation-001'): CheckInAction
    {
        return new CheckInAction(
            $id, $attendeeId, $reversalOf === null ? 'check_in' : 'reversal', $method,
            $stationId, $reversalOf, $operationId, new DateTimeImmutable('2026-08-17 20:00:00', new DateTimeZone('UTC')),
        );
    }
}
