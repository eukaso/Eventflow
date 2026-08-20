<?php

namespace EventFlow\Presentation\WordPress;

use EventFlow\Bootstrap\BootstrapResult;
use EventFlow\Presentation\Guest\GuestShellView;

final readonly class WordPressGuestHooks
{
    public const SHORTCODE = 'eventflow_rsvp';
    public const SCRIPT_HANDLE = 'eventflow-guest';

    public function __construct(
        private GuestShellView $view,
        private string $pluginFile,
        private string $version,
        private BootstrapResult $bootstrap,
    ) {
    }

    public function register(): void
    {
        if (function_exists('add_shortcode')) {
            add_shortcode(self::SHORTCODE, $this->render(...));
        }
    }

    public function render(): string
    {
        $this->enqueueAssets();
        return $this->view->render($this->bootstrap);
    }

    public function enqueueAssets(): void
    {
        if (!function_exists('plugin_dir_url') || !function_exists('wp_enqueue_style') || !function_exists('wp_enqueue_script')) {
            return;
        }
        $baseUrl = plugin_dir_url($this->pluginFile);
        wp_enqueue_style(self::SCRIPT_HANDLE, $baseUrl . 'assets/guest/eventflow-guest.css', [], $this->version);
        wp_enqueue_script(self::SCRIPT_HANDLE, $baseUrl . 'assets/guest/eventflow-guest.js', [], $this->version, true);
        if (function_exists('wp_localize_script')) {
            wp_localize_script(self::SCRIPT_HANDLE, 'EventFlowGuest', [
                'restUrl' => function_exists('rest_url') ? rest_url('eventflow/v1/') : '/wp-json/eventflow/v1/',
                'version' => $this->version,
                'bootstrapState' => $this->bootstrap->state->value,
                'ready' => $this->bootstrap->ready,
            ]);
        }
    }
}
