<?php

namespace EventFlow\Presentation\WordPress;

use EventFlow\Application\Error\RequestIdFactory;
use EventFlow\Presentation\Api\{ApiErrorTranslator, ApiResponse, RequestInputException, RestRouteRegistry};
use RuntimeException;
use Throwable;

final readonly class WordPressRestRouteRegistry implements RestRouteRegistry
{
    public function __construct(
        private WordPressRestRequestMapper $requests,
        private RequestIdFactory $requestIds,
        private ApiErrorTranslator $errors,
    ) {
    }

    public function registerPublicGet(string $namespace, string $route, callable $handler): void
    {
        if (!function_exists('register_rest_route') || !class_exists('WP_REST_Response')) {
            throw new RuntimeException('wordpress_rest_unavailable');
        }
        register_rest_route($namespace, $route, [
            'methods' => 'GET',
            'permission_callback' => '__return_true',
            'callback' => function (mixed $wordpressRequest) use ($handler): \WP_REST_Response {
                $candidate = is_object($wordpressRequest) && method_exists($wordpressRequest, 'get_header')
                    ? $wordpressRequest->get_header('X-Request-ID')
                    : null;
                $requestId = $this->requestIds->fromUntrusted(is_string($candidate) ? $candidate : null);
                try {
                    $request = $this->requests->map($wordpressRequest);
                    $response = $handler($request);
                } catch (Throwable $failure) {
                    $details = $failure instanceof RequestInputException ? $failure->details : null;
                    $response = $this->errors->translate($failure, $requestId, $details);
                }
                if (!$response instanceof ApiResponse) {
                    throw new RuntimeException('api_response_contract_invalid');
                }
                return new \WP_REST_Response($response->body(), $response->status(), $response->headers());
            },
        ]);
    }
}
