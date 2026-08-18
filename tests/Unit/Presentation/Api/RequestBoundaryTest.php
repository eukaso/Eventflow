<?php

namespace EventFlow\Tests\Unit\Presentation\Api;

use EventFlow\Application\Authorization\PrincipalType;
use EventFlow\Application\Error\RequestIdFactory;
use EventFlow\Application\Security\SecureRandom;
use EventFlow\Presentation\Api\{AuthenticatedRequestContextFactory, MutationPreconditionPolicy, RequestInputException, RestRequest};
use EventFlow\Presentation\WordPress\{WordPressAuthenticatedPrincipalResolver, WordPressRestRequestMapper};
use PHPUnit\Framework\TestCase;

final class RequestBoundaryTest extends TestCase
{
    public function testWordPressRequestIsNormalizedWithoutLeakingHostObject(): void
    {
        $request = (new WordPressRestRequestMapper())->map(new BoundaryWordPressRequest(
            '{"name":"Dinner","capacity":12}',
            ['X-Request-ID' => ['req_0123456789abcdef0123456789abcdef'], 'IF-MATCH' => ['W/"7"']],
            ['event_id' => 42],
        ));

        self::assertSame('Dinner', $request->input('name'));
        self::assertSame(12, $request->input('capacity'));
        self::assertSame('42', $request->route('event_id'));
        self::assertSame('guest', $request->query('q'));
        self::assertSame('W/"7"', $request->header('if-match'));
        self::assertSame('{"name":"Dinner","capacity":12}', $request->rawBody());
        self::assertSame('W/"7"', $request->headers()['if-match']);
    }

    public function testOpaqueWebhookBodyIsPreservedWithoutForcedJsonDecoding(): void
    {
        $request = (new WordPressRestRequestMapper())->map(new BoundaryWordPressRequest(
            'id=evt_123&status=delivered', ['Content-Type' => ['application/x-www-form-urlencoded']], ['provider' => 'mail.test'],
        ));
        self::assertSame([], $request->json());
        self::assertSame('id=evt_123&status=delivered', $request->rawBody());
    }

    public function testMalformedAndNonObjectJsonFailWithControlledCodes(): void
    {
        foreach ([['{bad', 'malformed_json'], ['not-json', 'malformed_json'], ['[1,2]', 'validation_failed']] as [$body, $code]) {
            try {
                (new WordPressRestRequestMapper())->map(new BoundaryWordPressRequest($body));
                self::fail('Expected invalid JSON input.');
            } catch (RequestInputException $failure) {
                self::assertSame($code, $failure->safeCode);
            }
        }
    }

    public function testAuthenticatedContextParsesAllAcceptedEntityTagForms(): void
    {
        $factory = $this->factory(17);
        foreach (['12', '"12"', 'W/"12"'] as $ifMatch) {
            $context = $factory->create(new RestRequest([
                'X-Request-ID' => 'req_0123456789abcdef0123456789abcdef',
                'Idempotency-Key' => 'mutation-key-001',
                'If-Match' => $ifMatch,
            ]), MutationPreconditionPolicy::IF_MATCH_AND_IDEMPOTENCY_KEY);
            self::assertSame(PrincipalType::WORDPRESS_USER, $context->principal->type);
            self::assertSame(17, $context->principal->userId);
            self::assertSame('mutation-key-001', $context->idempotencyKey);
            self::assertSame(12, $context->expectedVersion);
        }
    }

    public function testMissingMutationHeadersReturnTypedPreconditionDetails(): void
    {
        $factory = $this->factory(17);
        foreach ([
            [MutationPreconditionPolicy::IDEMPOTENCY_KEY, 'Idempotency-Key'],
            [MutationPreconditionPolicy::IF_MATCH, 'If-Match'],
        ] as [$policy, $header]) {
            try {
                $factory->create(new RestRequest(), $policy);
                self::fail('Expected missing precondition.');
            } catch (RequestInputException $failure) {
                self::assertSame('precondition_required', $failure->safeCode);
                self::assertSame(['required_header' => $header], $failure->details?->toArray());
            }
        }
    }

    public function testInvalidPrincipalAndMutationHeaderValuesFailClosed(): void
    {
        try {
            $this->factory(0)->create(new RestRequest(), MutationPreconditionPolicy::NONE);
            self::fail('Expected authentication failure.');
        } catch (RequestInputException $failure) {
            self::assertSame('authentication_required', $failure->safeCode);
        }

        foreach ([
            ['Idempotency-Key' => 'short'],
            ['If-Match' => '*'],
            ['If-Match' => '"12'],
        ] as $headers) {
            $policy = isset($headers['Idempotency-Key'])
                ? MutationPreconditionPolicy::IDEMPOTENCY_KEY
                : MutationPreconditionPolicy::IF_MATCH;
            try {
                $this->factory(17)->create(new RestRequest($headers), $policy);
                self::fail('Expected invalid mutation header.');
            } catch (RequestInputException $failure) {
                self::assertSame('validation_failed', $failure->safeCode);
            }
        }
    }

    private function factory(int $userId): AuthenticatedRequestContextFactory
    {
        return new AuthenticatedRequestContextFactory(
            new WordPressAuthenticatedPrincipalResolver(static fn (): int => $userId),
            new RequestIdFactory(new BoundaryRandom()),
        );
    }
}

final readonly class BoundaryRandom implements SecureRandom
{
    public function hex(int $bytes): string { return str_repeat('b', $bytes * 2); }
}

final readonly class BoundaryWordPressRequest
{
    public function __construct(private string $body, private array $headers = [], private array $routes = [], private array $query = ['q' => 'guest']) {}
    public function get_body(): string { return $this->body; }
    public function get_headers(): array { return $this->headers; }
    public function get_url_params(): array { return $this->routes; }
    public function get_query_params(): array { return $this->query; }
}
