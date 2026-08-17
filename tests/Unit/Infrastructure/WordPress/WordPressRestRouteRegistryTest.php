<?php

namespace {
    if (!function_exists('register_rest_route')) {
        function register_rest_route(string $namespace, string $route, array $options): bool
        {
            $GLOBALS['eventflow_test_rest_route'] = compact('namespace', 'route', 'options');
            return true;
        }
    }

    if (!class_exists('WP_REST_Response')) {
        final class WP_REST_Response
        {
            public function __construct(public mixed $data, public int $status, public array $headers)
            {
            }
        }
    }
}

namespace EventFlow\Tests\Unit\Infrastructure\WordPress {
    use EventFlow\Bootstrap\Container;
    use EventFlow\Infrastructure\Config\Config;
    use EventFlow\Presentation\WordPress\WordPressRestRouteRegistry;
    use EventFlow\Presentation\WordPress\WordPressRestRequestMapper;
    use EventFlow\Presentation\Api\{RestRequest, SystemStatusResponse};
    use PHPUnit\Framework\TestCase;

    final class WordPressRestRouteRegistryTest extends TestCase
    {
        protected function tearDown(): void
        {
            unset($GLOBALS['eventflow_test_rest_route']);
        }

        public function testPublicGetRouteConvertsWordPressRequestAndResponseAtBoundary(): void
        {
            $seenRequestId = null;
            $this->registry()->registerPublicGet(
                'eventflow/v1',
                '/system/health',
                static function (RestRequest $request) use (&$seenRequestId): SystemStatusResponse {
                    $seenRequestId = $request->header('X-Request-ID');
                    return new SystemStatusResponse(200, ['healthy' => true], ['X-Request-ID' => $seenRequestId]);
                },
            );

            $registered = $GLOBALS['eventflow_test_rest_route'];
            self::assertSame('eventflow/v1', $registered['namespace']);
            self::assertSame('/system/health', $registered['route']);
            self::assertSame('GET', $registered['options']['methods']);
            self::assertSame('__return_true', $registered['options']['permission_callback']);

            $request = new class {
                public function get_header(string $name): string
                {
                    return $name === 'X-Request-ID' ? 'req_0123456789abcdef0123456789abcdef' : '';
                }
                public function get_headers(): array { return ['X-Request-ID' => ['req_0123456789abcdef0123456789abcdef']]; }
                public function get_url_params(): array { return []; }
                public function get_body(): string { return ''; }
            };
            $response = $registered['options']['callback']($request);
            self::assertInstanceOf(\WP_REST_Response::class, $response);
            self::assertSame('req_0123456789abcdef0123456789abcdef', $seenRequestId);
            self::assertSame(200, $response->status);
            self::assertSame(['healthy' => true], $response->data);
        }

        public function testMalformedJsonIsTranslatedBeforeControllerInvocation(): void
        {
            $called = false;
            $this->registry()->registerPublicGet('eventflow/v1', '/test', static function () use (&$called): SystemStatusResponse {
                $called = true;
                return new SystemStatusResponse(200, [], []);
            });
            $request = new class {
                public function get_header(string $name): string { return 'req_0123456789abcdef0123456789abcdef'; }
                public function get_headers(): array { return []; }
                public function get_url_params(): array { return []; }
                public function get_body(): string { return '{bad'; }
            };
            $response = $GLOBALS['eventflow_test_rest_route']['options']['callback']($request);
            self::assertFalse($called);
            self::assertSame(400, $response->status);
            self::assertSame('malformed_json', $response->data['code']);
            self::assertSame('req_0123456789abcdef0123456789abcdef', $response->headers['X-Request-ID']);
        }

        public function testAuthenticatedPostUsesWordPressAuthenticationPermission(): void
        {
            $this->registry()->registerAuthenticatedPost('eventflow/v1', '/events', static fn (): SystemStatusResponse => new SystemStatusResponse(200, [], []));
            $registered = $GLOBALS['eventflow_test_rest_route'];
            self::assertSame('POST', $registered['options']['methods']);
            self::assertSame('is_user_logged_in', $registered['options']['permission_callback']);
        }

        public function testAuthenticatedPatchUsesWordPressAuthenticationPermission(): void
        {
            $this->registry()->registerAuthenticatedPatch('eventflow/v1', '/events/1/memberships/2', static fn (): SystemStatusResponse => new SystemStatusResponse(200, [], []));
            $registered = $GLOBALS['eventflow_test_rest_route'];
            self::assertSame('PATCH', $registered['options']['methods']);
            self::assertSame('is_user_logged_in', $registered['options']['permission_callback']);
        }

        private function registry(): WordPressRestRouteRegistry
        {
            $container = Container::createFoundation(new Config('testing', '0.9.0', 6, 'error', false));
            return new WordPressRestRouteRegistry(
                new WordPressRestRequestMapper(),
                $container->services->requestIds,
                $container->services->apiErrors,
                $container->services->observability,
            );
        }
    }
}
