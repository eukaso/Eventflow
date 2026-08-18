<?php

namespace EventFlow\Tests\Unit\Presentation\Api;

use EventFlow\Application\Authorization\PrincipalContext;
use EventFlow\Application\Communication\{CommunicationChannel, TemplateCommands, TemplateRecord};
use EventFlow\Application\Error\RequestIdFactory;
use EventFlow\Application\Idempotency\{IdempotencyOutcome, IdempotencyResultReference};
use EventFlow\Application\Persistence\EventScope;
use EventFlow\Application\Security\SecureRandom;
use EventFlow\Presentation\Api\{AuthenticatedPrincipalResolver, AuthenticatedRequestContextFactory, RequestInputException, RestRequest, RestRouteRegistry, TemplateController, TemplatePresenter, TemplateRequestMapper, TemplateRouteRegistrar};
use PHPUnit\Framework\TestCase;

final class TemplateControllerTest extends TestCase
{
    public function testRegistrarExposesOnlyAuthoritativeTemplateCommands(): void
    {
        $routes = new TemplateMemoryRoutes();
        (new TemplateRouteRegistrar($this->controller(new TemplatePort())))->register($routes);
        self::assertSame([
            'POST eventflow/v1/events/(?P<event_id>\d+)/communication-templates',
            'POST eventflow/v1/events/(?P<event_id>\d+)/communication-templates/(?P<template_id>\d+)/publish',
        ], $routes->registered);
    }

    public function testCreateMapsDraftAndReturnsCompleteTemplate(): void
    {
        $port = new TemplatePort();
        $response = $this->controller($port)->create(new RestRequest(
            ['Idempotency-Key' => 'template-create-001'],
            [
                'key' => 'rsvp.reminder', 'name' => 'RSVP Reminder', 'channel' => 'email', 'type' => 'reminder',
                'subject' => 'Hello {{recipient_name}}', 'body' => '<p>Please respond</p>',
                'plain_text' => 'Please respond', 'allowed_fields' => ['recipient_name', 'guest_link'],
            ],
            ['event_id' => '9'],
        ));
        self::assertSame('create', $port->operation);
        self::assertSame('rsvp.reminder', $port->draft[0]);
        self::assertSame(CommunicationChannel::EMAIL, $port->draft[2]);
        self::assertSame(['recipient_name', 'guest_link'], $port->draft[7]);
        self::assertSame('template-create-001', $port->draft[8]);
        self::assertSame(201, $response->status());
        self::assertSame('draft', $response->body()['data']['status']);
        self::assertSame('<p>Please respond</p>', $response->body()['data']['body']);
        self::assertSame('/wp-json/eventflow/v1/events/9/communication-templates/41', $response->headers()['Location']);
    }

    public function testPublishRequiresEmptyBodyAndMapsScopedId(): void
    {
        $port = new TemplatePort();
        $response = $this->controller($port)->publish(new RestRequest(
            ['Idempotency-Key' => 'template-publish-001'], [], ['event_id' => '9', 'template_id' => '41'],
        ));
        self::assertSame('publish', $port->operation);
        self::assertSame([41, 'template-publish-001'], $port->publication);
        self::assertSame(200, $response->status());
        self::assertSame('published', $response->body()['data']['status']);
    }

    public function testReplayFallsBackToStableReferenceWithoutFabricatingContent(): void
    {
        $port = new TemplatePort();
        $port->replay = true;
        $response = $this->controller($port)->publish(new RestRequest(
            ['Idempotency-Key' => 'template-publish-002'], [], ['event_id' => '9', 'template_id' => '41'],
        ));
        self::assertTrue($response->body()['meta']['replayed']);
        self::assertSame(['type' => 'communication_template', 'id' => 41], $response->body()['data']);
    }

    public function testWeakTypesUnknownFieldsInvalidEnumsAndRoutesFailBeforePort(): void
    {
        $port = new TemplatePort();
        $base = ['key'=>'notice','name'=>'Notice','channel'=>'email','type'=>'general','body'=>'Body'];
        foreach ([
            fn () => $this->controller($port)->create(new RestRequest(['Idempotency-Key'=>'template-bad-001'], [...$base, 'channel'=>'fax'], ['event_id'=>'9'])),
            fn () => $this->controller($port)->create(new RestRequest(['Idempotency-Key'=>'template-bad-002'], [...$base, 'allowed_fields'=>'recipient_name'], ['event_id'=>'9'])),
            fn () => $this->controller($port)->create(new RestRequest(['Idempotency-Key'=>'template-bad-003'], [...$base, 'admin'=>true], ['event_id'=>'9'])),
            fn () => $this->controller($port)->create(new RestRequest(['Idempotency-Key'=>'template-bad-004'], [...$base, 'body'=>7], ['event_id'=>'9'])),
            fn () => $this->controller($port)->publish(new RestRequest(['Idempotency-Key'=>'template-bad-005'], ['force'=>true], ['event_id'=>'9','template_id'=>'41'])),
            fn () => $this->controller($port)->publish(new RestRequest(['Idempotency-Key'=>'template-bad-006'], [], ['event_id'=>'9','template_id'=>'../41'])),
        ] as $operation) {
            try { $operation(); self::fail('Expected controlled input failure.'); }
            catch (RequestInputException $failure) { self::assertContains($failure->safeCode, ['validation_failed', 'resource_not_found']); }
        }
        self::assertSame(0, $port->calls);
    }

    private function controller(TemplatePort $port): TemplateController
    {
        return new TemplateController(
            $port,
            new AuthenticatedRequestContextFactory(new TemplatePrincipalResolver(), new RequestIdFactory(new TemplateRandom())),
            new TemplateRequestMapper(),
            new TemplatePresenter(),
        );
    }
}

final class TemplateMemoryRoutes implements RestRouteRegistry
{
    public array $registered = [];
    public function registerPublicGet(string $namespace, string $route, callable $handler): void {}
    public function registerPublicPost(string $namespace, string $route, callable $handler): void {}
    public function registerPublicPut(string $namespace, string $route, callable $handler): void {}
    public function registerAuthenticatedGet(string $namespace, string $route, callable $handler): void {}
    public function registerAuthenticatedPost(string $namespace, string $route, callable $handler): void { $this->registered[] = 'POST ' . $namespace . $route; }
    public function registerAuthenticatedPatch(string $namespace, string $route, callable $handler): void {}
}

final readonly class TemplatePrincipalResolver implements AuthenticatedPrincipalResolver
{
    public function resolve(RestRequest $request): PrincipalContext { return PrincipalContext::wordpressUser(7); }
}

final readonly class TemplateRandom implements SecureRandom
{
    public function hex(int $bytes): string { return str_repeat('8', $bytes * 2); }
}

final class TemplatePort implements TemplateCommands
{
    public int $calls = 0;
    public string $operation = '';
    public array $draft = [];
    public array $publication = [];
    public bool $replay = false;

    public function createDraft(PrincipalContext $principal, EventScope $scope, string $key, string $name, CommunicationChannel $channel, string $type, ?string $subject, string $body, ?string $plainText, array $allowedFields, string $idempotencyKey): IdempotencyOutcome
    {
        $this->calls++; $this->operation = 'create';
        $this->draft = [$key, $name, $channel, $type, $subject, $body, $plainText, $allowedFields, $idempotencyKey];
        return $this->outcome('draft', 201);
    }

    public function publish(PrincipalContext $principal, EventScope $scope, int $templateId, string $idempotencyKey): IdempotencyOutcome
    {
        $this->calls++; $this->operation = 'publish'; $this->publication = [$templateId, $idempotencyKey];
        return $this->outcome('published', 200);
    }

    private function outcome(string $status, int $httpStatus): IdempotencyOutcome
    {
        return new IdempotencyOutcome(
            $this->replay,
            new IdempotencyResultReference('communication_template', 41, $httpStatus),
            $this->replay ? null : new TemplateRecord(41, 'rsvp.reminder', 'RSVP Reminder', CommunicationChannel::EMAIL, 1, $status, 'Hello {{recipient_name}}', '<p>Please respond</p>', 'Please respond', ['recipient_name', 'guest_link']),
        );
    }
}
