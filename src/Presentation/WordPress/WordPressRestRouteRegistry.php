<?php

namespace EventFlow\Presentation\WordPress;

use EventFlow\Presentation\Api\{ApiResponse, RestRequest, RestRouteRegistry};
use RuntimeException;

final readonly class WordPressRestRouteRegistry implements RestRouteRegistry
{
    public function registerPublicGet(string $namespace, string $route, callable $handler): void
    {
        if (!function_exists('register_rest_route') || !class_exists('WP_REST_Response')) {
            throw new RuntimeException('wordpress_rest_unavailable');
        }
        register_rest_route($namespace, $route, [
            'methods' => 'GET',
            'permission_callback' => '__return_true',
            'callback' => static function (mixed $wordpressRequest) use ($handler): \WP_REST_Response {
                $candidate = is_object($wordpressRequest) && method_exists($wordpressRequest, 'get_header')
                    ? $wordpressRequest->get_header('X-Request-ID')
                    : null;
                $request = new RestRequest(['X-Request-ID' => is_string($candidate) ? $candidate : '']);
                $response = $handler($request);
                if (!$response instanceof ApiResponse) {
                    throw new RuntimeException('api_response_contract_invalid');
                }
                return new \WP_REST_Response($response->body(), $response->status(), $response->headers());
            },
        ]);
    }
}
