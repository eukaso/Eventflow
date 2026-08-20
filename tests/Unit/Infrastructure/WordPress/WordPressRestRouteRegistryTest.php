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

    if (!function_exists('home_url')) {
        function home_url(string $path = ''): string
        {
            return 'https://eventflow.test' . $path;
        }
    }

    if (!function_exists('add_filter')) {
        function add_filter(string $hook, callable $callback, int $priority = 10, int $acceptedArgs = 1): bool
        {
            $GLOBALS['eventflow_test_filter'] = compact('hook', 'callback', 'priority', 'acceptedArgs');
            return true;
        }
    }
}

namespace EventFlow\Tests\Unit\Infrastructure\WordPress {
    use EventFlow\Bootstrap\Container;
    use EventFlow\Infrastructure\Config\Config;
    use EventFlow\Presentation\WordPress\WordPressRestRouteRegistry;
    use EventFlow\Presentation\WordPress\WordPressRestRequestMapper;
    use EventFlow\Presentation\Api\{BinaryApiResponse, RestRequest, SystemStatusResponse};
    use PHPUnit\Framework\TestCase;

    final class WordPressRestRouteRegistryTest extends TestCase
    {
        protected function tearDown(): void
        {
            unset($GLOBALS['eventflow_test_rest_route']);
            unset($GLOBALS['eventflow_test_filter']);
        }

        public function testPublicGetRouteConvertsWordPressRequestAndResponseAtBoundary(): void
        {
            $seenRequestId = null;
            $seenClientAddress = null;
            $seenGuestCookie = null;
            $seenSameOrigin = false;
            $this->registry()->registerPublicGet(
                'eventflow/v1',
                '/system/health',
                static function (RestRequest $request) use (&$seenRequestId, &$seenClientAddress, &$seenGuestCookie, &$seenSameOrigin): SystemStatusResponse {
                    $seenRequestId = $request->header('X-Request-ID');
                    $seenClientAddress = $request->clientAddress();
                    $seenGuestCookie = $request->cookie('eventflow_guest_session');
                    $seenSameOrigin = $request->sameOrigin();
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
                public function get_headers(): array { return ['X-Request-ID' => ['req_0123456789abcdef0123456789abcdef'], 'X-Forwarded-For' => ['198.51.100.4'], 'Origin' => ['https://eventflow.test']]; }
                public function get_url_params(): array { return []; }
                public function get_cookie_params(): array { return ['eventflow_guest_session' => 'guest-cookie']; }
                public function get_body(): string { return ''; }
            };
            $_SERVER['REMOTE_ADDR'] = '203.0.113.4';
            $response = $registered['options']['callback']($request);
            unset($_SERVER['REMOTE_ADDR']);
            self::assertInstanceOf(\WP_REST_Response::class, $response);
            self::assertSame('req_0123456789abcdef0123456789abcdef', $seenRequestId);
            self::assertSame('203.0.113.4', $seenClientAddress);
            self::assertSame('guest-cookie', $seenGuestCookie);
            self::assertTrue($seenSameOrigin);
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

        public function testAuthenticatedGetUsesWordPressAuthenticationPermission(): void
        {
            $this->registry()->registerAuthenticatedGet('eventflow/v1', '/events/1/seating/readiness', static fn (): SystemStatusResponse => new SystemStatusResponse(200, [], []));
            $registered = $GLOBALS['eventflow_test_rest_route'];
            self::assertSame('GET', $registered['options']['methods']);
            self::assertSame('is_user_logged_in', $registered['options']['permission_callback']);
        }

        public function testPublicPostAllowsCredentialBootstrapWithoutWordPressLogin(): void
        {
            $this->registry()->registerPublicPost('eventflow/v1', '/public/invitations/bootstrap', static fn (): SystemStatusResponse => new SystemStatusResponse(201, [], []));
            $registered = $GLOBALS['eventflow_test_rest_route'];
            self::assertSame('POST', $registered['options']['methods']);
            self::assertSame('__return_true', $registered['options']['permission_callback']);
        }

        public function testAuthenticatedPatchUsesWordPressAuthenticationPermission(): void
        {
            $this->registry()->registerAuthenticatedPatch('eventflow/v1', '/events/1/memberships/2', static fn (): SystemStatusResponse => new SystemStatusResponse(200, [], []));
            $registered = $GLOBALS['eventflow_test_rest_route'];
            self::assertSame('PATCH', $registered['options']['methods']);
            self::assertSame('is_user_logged_in', $registered['options']['permission_callback']);
        }

        public function testPublicPutDefersGuestAuthenticationToController(): void
        {
            $this->registry()->registerPublicPut('eventflow/v1', '/public/invitation/response', static fn (): SystemStatusResponse => new SystemStatusResponse(200, [], []));
            $registered = $GLOBALS['eventflow_test_rest_route'];
            self::assertSame('PUT', $registered['options']['methods']);
            self::assertSame('__return_true', $registered['options']['permission_callback']);
        }

        public function testBinaryServingFilterBypassesJsonSerialization(): void
        {
            $this->registry()->registerBinaryServing();
            $filter = $GLOBALS['eventflow_test_filter'];
            self::assertSame('rest_pre_serve_request', $filter['hook']);
            $result = new class {
                public function get_data(): BinaryApiResponse { return new BinaryApiResponse('raw-bytes', ['Content-Type'=>'text/csv']); }
            };
            ob_start();
            $served = $filter['callback'](false, $result);
            $output = ob_get_clean();
            self::assertTrue($served);
            self::assertSame('raw-bytes', $output);
        }

        private function registry(): WordPressRestRouteRegistry
        {
            $container = Container::createFoundation(new Config('testing', '1.0.0', 6, 'error', false));
            return new WordPressRestRouteRegistry(
                new WordPressRestRequestMapper(),
                $container->services->requestIds,
                $container->services->apiErrors,
                $container->services->observability,
            );
        }
    }
}
