<?php

namespace EventFlow\Presentation\WordPress;

use EventFlow\Bootstrap\BootstrapResult;
use EventFlow\Presentation\Admin\AdminShellView;

final readonly class WordPressAdminHooks
{
    public const MENU_SLUG = 'eventflow';
    public const HOOK_SUFFIX = 'toplevel_page_eventflow';
    public const CAPABILITY = 'read';
    public const SCRIPT_HANDLE = 'eventflow-admin';

    public function __construct(
        private AdminShellView $view,
        private string $pluginFile,
        private string $version,
        private BootstrapResult $bootstrap,
    ) {
    }

    public function register(): void
    {
        if (!function_exists('add_action')) {
            return;
        }

        add_action('admin_menu', $this->registerMenu(...));
        add_action('admin_enqueue_scripts', $this->enqueueAssets(...));
    }

    public function registerMenu(): void
    {
        if (!function_exists('add_menu_page')) {
            return;
        }

        add_menu_page(
            'EventFlow',
            'EventFlow',
            self::CAPABILITY,
            self::MENU_SLUG,
            $this->render(...),
            'dashicons-calendar-alt',
            26,
        );
    }

    public function render(): void
    {
        if (function_exists('current_user_can') && !current_user_can(self::CAPABILITY)) {
            return;
        }

        echo $this->view->render($this->bootstrap);
    }

    public function enqueueAssets(string $hookSuffix): void
    {
        if ($hookSuffix !== self::HOOK_SUFFIX
            || !function_exists('plugin_dir_url')
            || !function_exists('wp_enqueue_style')
            || !function_exists('wp_enqueue_script')
        ) {
            return;
        }

        $baseUrl = plugin_dir_url($this->pluginFile);
        $scriptVersion = $this->assetVersion('assets/admin/eventflow-admin.js');
        if (function_exists('wp_enqueue_media')) {
            wp_enqueue_media();
        }
        wp_enqueue_style(
            self::SCRIPT_HANDLE,
            $baseUrl . 'assets/admin/eventflow-admin.css',
            [],
            $this->version,
        );
        wp_enqueue_script(
            self::SCRIPT_HANDLE,
            $baseUrl . 'assets/admin/eventflow-admin.js',
            [],
            $scriptVersion,
            true,
        );

        if (function_exists('wp_localize_script')) {
            wp_localize_script(self::SCRIPT_HANDLE, 'EventFlowAdmin', [
                'restUrl' => function_exists('rest_url') ? rest_url('eventflow/v1/') : '/wp-json/eventflow/v1/',
                'nonce' => function_exists('wp_create_nonce') ? wp_create_nonce('wp_rest') : '',
                'version' => $this->version,
                'bootstrapState' => $this->bootstrap->state->value,
                'ready' => $this->bootstrap->ready,
            ]);
        }
    }

    private function assetVersion(string $relativePath): string
    {
        $path = dirname($this->pluginFile) . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relativePath);
        $digest = is_file($path) ? hash_file('sha256', $path) : false;
        return is_string($digest) ? $this->version . '-' . substr($digest, 0, 12) : $this->version;
    }
}
