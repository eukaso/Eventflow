<?php

namespace {
    if (!function_exists('add_action')) {
        function add_action(string $hook, callable $callback): bool
        {
            $GLOBALS['eventflow_test_admin_actions'][$hook] = $callback;
            return true;
        }
    }
    if (!function_exists('add_menu_page')) {
        function add_menu_page(mixed ...$arguments): string
        {
            $GLOBALS['eventflow_test_admin_menu'] = $arguments;
            return 'toplevel_page_eventflow';
        }
    }
    if (!function_exists('add_shortcode')) {
        function add_shortcode(string $tag, callable $callback): void
        {
            $GLOBALS['eventflow_test_admin_shortcode'] = compact('tag', 'callback');
        }
    }
    if (!function_exists('plugin_dir_url')) {
        function plugin_dir_url(string $file): string { return 'https://eventflow.test/plugins/eventflow/'; }
    }
    if (!function_exists('wp_enqueue_style')) {
        function wp_enqueue_style(mixed ...$arguments): void { $GLOBALS['eventflow_test_admin_style'] = $arguments; }
    }
    if (!function_exists('wp_enqueue_script')) {
        function wp_enqueue_script(mixed ...$arguments): void { $GLOBALS['eventflow_test_admin_script'] = $arguments; }
    }
    if (!function_exists('wp_localize_script')) {
        function wp_localize_script(mixed ...$arguments): bool
        {
            $GLOBALS['eventflow_test_admin_config'] = $arguments;
            return true;
        }
    }
    if (!function_exists('rest_url')) {
        function rest_url(string $path): string { return 'https://eventflow.test/wp-json/' . $path; }
    }
    if (!function_exists('wp_create_nonce')) {
        function wp_create_nonce(string $action): string { return $action === 'wp_rest' ? 'rest-nonce' : ''; }
    }
    if (!function_exists('current_user_can')) {
        function current_user_can(string $capability): bool { return $capability === 'read'; }
    }
}

namespace EventFlow\Tests\Unit\Presentation\WordPress {
    use EventFlow\Bootstrap\{BootstrapResult, BootstrapState};
    use EventFlow\Presentation\Admin\AdminShellView;
    use EventFlow\Presentation\Guest\GuestShellView;
    use EventFlow\Presentation\WordPress\{WordPressAdminHooks, WordPressGuestHooks};
    use PHPUnit\Framework\TestCase;

    final class WordPressAdminHooksTest extends TestCase
    {
        protected function tearDown(): void
        {
            foreach (array_keys($GLOBALS) as $key) {
                if (str_starts_with($key, 'eventflow_test_admin_')) unset($GLOBALS[$key]);
            }
        }

        public function testRegistersCapabilityGatedTopLevelMenu(): void
        {
            $hooks = $this->hooks();
            $hooks->register();

            self::assertArrayHasKey('admin_menu', $GLOBALS['eventflow_test_admin_actions']);
            self::assertArrayHasKey('admin_enqueue_scripts', $GLOBALS['eventflow_test_admin_actions']);
            ($GLOBALS['eventflow_test_admin_actions']['admin_menu'])();
            self::assertSame('EventFlow', $GLOBALS['eventflow_test_admin_menu'][0]);
            self::assertSame('read', $GLOBALS['eventflow_test_admin_menu'][2]);
            self::assertSame('eventflow', $GLOBALS['eventflow_test_admin_menu'][3]);
        }

        public function testAssetsAndMinimalRuntimeConfigLoadOnlyOnEventFlowScreen(): void
        {
            $hooks = $this->hooks();
            $hooks->enqueueAssets('dashboard_page_home');
            self::assertArrayNotHasKey('eventflow_test_admin_script', $GLOBALS);

            $hooks->enqueueAssets(WordPressAdminHooks::HOOK_SUFFIX);
            self::assertSame('https://eventflow.test/plugins/eventflow/assets/admin/eventflow-admin.css', $GLOBALS['eventflow_test_admin_style'][1]);
            self::assertSame('https://eventflow.test/plugins/eventflow/assets/admin/eventflow-admin.js', $GLOBALS['eventflow_test_admin_script'][1]);
            self::assertTrue($GLOBALS['eventflow_test_admin_script'][4]);
            self::assertSame('EventFlowAdmin', $GLOBALS['eventflow_test_admin_config'][1]);
            self::assertSame([
                'restUrl' => 'https://eventflow.test/wp-json/eventflow/v1/',
                'nonce' => 'rest-nonce',
                'version' => '1.2.0',
                'bootstrapState' => 'ready',
                'ready' => true,
            ], $GLOBALS['eventflow_test_admin_config'][2]);
        }

        public function testRendererEmitsOnlyTheShellForAuthorizedUser(): void
        {
            ob_start();
            $this->hooks()->render();
            $html = ob_get_clean();

            self::assertIsString($html);
            self::assertStringContainsString('id="eventflow-admin"', $html);
            self::assertStringNotContainsString('<script', $html);
        }

        public function testGuestShortcodeEnqueuesOnlyPublicAssetsAndNonSensitiveConfig(): void
        {
            $hooks = new WordPressGuestHooks(
                new GuestShellView(),
                '/plugins/eventflow/eventflow.php',
                '1.2.0',
                new BootstrapResult(BootstrapState::READY, true, true, []),
            );
            $hooks->register();
            self::assertSame('eventflow_rsvp', $GLOBALS['eventflow_test_admin_shortcode']['tag']);
            $html = ($GLOBALS['eventflow_test_admin_shortcode']['callback'])();

            self::assertStringContainsString('id="eventflow-guest"', $html);
            self::assertSame('https://eventflow.test/plugins/eventflow/assets/guest/eventflow-guest.css', $GLOBALS['eventflow_test_admin_style'][1]);
            self::assertSame('https://eventflow.test/plugins/eventflow/assets/guest/eventflow-guest.js', $GLOBALS['eventflow_test_admin_script'][1]);
            self::assertSame([
                'restUrl' => 'https://eventflow.test/wp-json/eventflow/v1/',
                'version' => '1.2.0',
                'bootstrapState' => 'ready',
                'ready' => true,
            ], $GLOBALS['eventflow_test_admin_config'][2]);
        }

        private function hooks(): WordPressAdminHooks
        {
            return new WordPressAdminHooks(
                new AdminShellView(),
                '/plugins/eventflow/eventflow.php',
                '1.2.0',
                new BootstrapResult(BootstrapState::READY, true, true, []),
            );
        }
    }
}
