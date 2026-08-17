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
    use EventFlow\Presentation\WordPress\WordPressRestRouteRegistry;
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
            (new WordPressRestRouteRegistry())->registerPublicGet(
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
            };
            $response = $registered['options']['callback']($request);
            self::assertInstanceOf(\WP_REST_Response::class, $response);
            self::assertSame('req_0123456789abcdef0123456789abcdef', $seenRequestId);
            self::assertSame(200, $response->status);
            self::assertSame(['healthy' => true], $response->data);
        }
    }
}
