<?php

namespace EventFlow\Bootstrap;

final class RuntimeValidator
{
    /**
     * @return list<string>
     */
    public function validate(): array
    {
        $errors = [];

        if (version_compare(PHP_VERSION, RuntimeRequirements::MIN_PHP_VERSION, '<')) {
            $errors[] = 'unsupported_php_version';
        }

        global $wp_version;

        if (
            isset($wp_version) &&
            version_compare((string) $wp_version, RuntimeRequirements::MIN_WORDPRESS_VERSION, '<')
        ) {
            $errors[] = 'unsupported_wordpress_version';
        }

        foreach (['json', 'openssl', 'hash'] as $extension) {
            if (!extension_loaded($extension)) {
                $errors[] = 'missing_php_extension_' . $extension;
            }
        }

        return $errors;
    }
}
