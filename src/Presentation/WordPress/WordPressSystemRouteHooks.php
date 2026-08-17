<?php

namespace EventFlow\Presentation\WordPress;

use EventFlow\Presentation\Api\SystemRouteRegistrar;

final readonly class WordPressSystemRouteHooks
{
    public function __construct(private SystemRouteRegistrar $routes)
    {
    }

    public function register(): void
    {
        if (!function_exists('add_action')) {
            return;
        }
        $routes = $this->routes;
        add_action('rest_api_init', static function () use ($routes): void {
            $routes->register(new WordPressRestRouteRegistry());
        });
    }
}
