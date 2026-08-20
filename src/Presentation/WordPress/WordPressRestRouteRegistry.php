<?php

namespace EventFlow\Presentation\WordPress;

use EventFlow\Application\Error\RequestIdFactory;
use EventFlow\Application\Observability\ObservabilityService;
use EventFlow\Presentation\Api\{ApiErrorTranslator, ApiResponse, BinaryApiResponse, RequestInputException, RestRouteRegistry};
use RuntimeException;
use Throwable;

final readonly class WordPressRestRouteRegistry implements RestRouteRegistry
{
    public function __construct(
        private WordPressRestRequestMapper $requests,
        private RequestIdFactory $requestIds,
        private ApiErrorTranslator $errors,
        private ObservabilityService $observability,
    ) {
    }

    public function registerBinaryServing(): void
    {
        if (!function_exists('add_filter')) return;
        add_filter('rest_pre_serve_request', static function (mixed $served, mixed $result): bool {
            if ($served === true || !is_object($result) || !method_exists($result, 'get_data')) return $served === true;
            $data = $result->get_data();
            if (!$data instanceof BinaryApiResponse) return false;
            echo $data->content();
            return true;
        }, 10, 2);
    }

    public function registerPublicGet(string $namespace, string $route, callable $handler): void
    {
        $this->register($namespace, $route, 'GET', '__return_true', $handler);
    }

    public function registerPublicPost(string $namespace, string $route, callable $handler): void
    {
        $this->register($namespace, $route, 'POST', '__return_true', $handler);
    }

    public function registerPublicPut(string $namespace, string $route, callable $handler): void
    {
        $this->register($namespace, $route, 'PUT', '__return_true', $handler);
    }

    public function registerAuthenticatedPost(string $namespace, string $route, callable $handler): void
    {
        $this->register($namespace, $route, 'POST', 'is_user_logged_in', $handler);
    }

    public function registerAuthenticatedGet(string $namespace, string $route, callable $handler): void
    {
        $this->register($namespace, $route, 'GET', 'is_user_logged_in', $handler);
    }

    public function registerAuthenticatedPatch(string $namespace, string $route, callable $handler): void
    {
        $this->register($namespace, $route, 'PATCH', 'is_user_logged_in', $handler);
    }

    public function registerAuthenticatedPut(string $namespace, string $route, callable $handler): void
    {
        $this->register($namespace, $route, 'PUT', 'is_user_logged_in', $handler);
    }

    private function register(string $namespace, string $route, string $method, string $permission, callable $handler): void
    {
        if (!function_exists('register_rest_route') || !class_exists('WP_REST_Response')) {
            throw new RuntimeException('wordpress_rest_unavailable');
        }
        register_rest_route($namespace, $route, [
            'methods' => $method,
            'permission_callback' => $permission,
            'callback' => function (mixed $wordpressRequest) use ($handler): \WP_REST_Response {
                $candidate = is_object($wordpressRequest) && method_exists($wordpressRequest, 'get_header')
                    ? $wordpressRequest->get_header('X-Request-ID')
                    : null;
                $requestId = $this->requestIds->fromUntrusted(is_string($candidate) ? $candidate : null);
                try {
                    $request = $this->requests->map($wordpressRequest);
                    $response = $handler($request);
                    if (!$response instanceof ApiResponse) {
                        throw new RuntimeException('api_response_contract_invalid');
                    }
                    $this->observability->requestCompleted('api', true);
                } catch (Throwable $failure) {
                    $details = $failure instanceof RequestInputException ? $failure->details : null;
                    $response = $this->errors->translate($failure, $requestId, $details);
                }
                $data = $response instanceof BinaryApiResponse ? $response : $response->body();
                return new \WP_REST_Response($data, $response->status(), $response->headers());
            },
        ]);
    }
}
