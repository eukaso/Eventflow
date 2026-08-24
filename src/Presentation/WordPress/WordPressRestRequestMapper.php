<?php

namespace EventFlow\Presentation\WordPress;

use EventFlow\Application\Import\UploadedFile;
use EventFlow\Presentation\Api\{RequestInputException, RestRequest};
use JsonException;

final readonly class WordPressRestRequestMapper
{
    private const MAX_JSON_BYTES = 1048576;

    public function map(mixed $wordpressRequest): RestRequest
    {
        if (!is_object($wordpressRequest)) {
            throw new RequestInputException('malformed_json');
        }
        $headers = $this->headers(method_exists($wordpressRequest, 'get_headers') ? $wordpressRequest->get_headers() : []);
        $routes = $this->routes(method_exists($wordpressRequest, 'get_url_params') ? $wordpressRequest->get_url_params() : []);
        $query = $this->routes(method_exists($wordpressRequest, 'get_query_params') ? $wordpressRequest->get_query_params() : []);
        $cookies = $this->cookies(method_exists($wordpressRequest, 'get_cookie_params') ? $wordpressRequest->get_cookie_params() : []);
        $raw = method_exists($wordpressRequest, 'get_body') ? $wordpressRequest->get_body() : '';
        $files = $this->files(method_exists($wordpressRequest,'get_file_params')?$wordpressRequest->get_file_params():[]);
        if (!is_string($raw) || ($files===[]&&strlen($raw)>self::MAX_JSON_BYTES)) {
            throw new RequestInputException('validation_failed');
        }
        $json = [];
        $providerWebhook = array_key_exists('provider', $routes);
        if ($files===[]&&trim($raw) !== '' && (!$providerWebhook || $this->looksLikeJson($raw, $headers))) {
            try {
                $decoded = json_decode($raw, true, 64, JSON_THROW_ON_ERROR);
            } catch (JsonException) {
                throw new RequestInputException('malformed_json');
            }
            if (!is_array($decoded) || ($decoded !== [] && array_is_list($decoded))) {
                throw new RequestInputException('validation_failed');
            }
            $json = $decoded;
        }
        return new RestRequest($headers, $json, $routes, $this->clientAddress(), $cookies, $this->sameOrigin($headers), $query, $raw,$files);
    }

    /** @return array<string,UploadedFile> */
    private function files(mixed$source):array{if(!is_array($source))return[];$files=[];foreach($source as$name=>$file){if(!is_string($name)||!is_array($file))continue;$filename=$file['name']??null;$path=$file['tmp_name']??null;$size=$file['size']??null;$error=$file['error']??null;if(is_string($filename)&&is_string($path)&&is_int($size)&&is_int($error))$files[$name]=new UploadedFile($filename,$path,$size,$error);}return$files;}

    /** @return array<string, string> */
    private function headers(mixed $source): array
    {
        if (!is_array($source)) return [];
        $headers = [];
        foreach ($source as $name => $value) {
            if (!is_string($name)) continue;
            $candidate = is_array($value) ? reset($value) : $value;
            if (is_string($candidate)) {
                // WP_REST_Request canonicalizes HTTP header names by replacing
                // hyphens with underscores. Restore their wire representation so
                // RestRequest can enforce Idempotency-Key, If-Match, signatures,
                // and other security-sensitive headers consistently.
                $headers[str_replace('_', '-', $name)] = $candidate;
            }
        }
        return $headers;
    }

    /** @return array<string, string> */
    private function routes(mixed $source): array
    {
        if (!is_array($source)) return [];
        $routes = [];
        foreach ($source as $name => $value) {
            if (is_string($name) && (is_string($value) || is_int($value))) $routes[$name] = (string) $value;
        }
        return $routes;
    }

    private function clientAddress(): ?string
    {
        $candidate = $_SERVER['REMOTE_ADDR'] ?? null;
        if (!is_string($candidate) || filter_var($candidate, FILTER_VALIDATE_IP) === false) return null;
        return $candidate;
    }

    /** @return array<string, string> */
    private function cookies(mixed $source): array
    {
        if (!is_array($source)) return [];
        $cookies = [];
        foreach ($source as $name => $value) {
            if (is_string($name) && is_string($value)) $cookies[$name] = $value;
        }
        return $cookies;
    }

    /** @param array<string, string> $headers */
    private function sameOrigin(array $headers): bool
    {
        if (!function_exists('home_url')) return false;
        $origin = null;
        foreach ($headers as $name => $value) {
            if (strcasecmp($name, 'Origin') === 0) { $origin = $value; break; }
        }
        if ($origin === null) return false;
        $site = home_url('/');
        if (!is_string($site)) return false;
        return $this->origin($origin) !== null && $this->origin($origin) === $this->origin($site);
    }

    private function origin(string $url): ?string
    {
        $parts = parse_url($url);
        if (!is_array($parts) || !isset($parts['scheme'], $parts['host'])) return null;
        $scheme = strtolower((string) $parts['scheme']);
        if ($scheme !== 'http' && $scheme !== 'https') return null;
        $explicitPort = isset($parts['port']) ? (int) $parts['port'] : null;
        $port = $explicitPort !== null && !(($scheme === 'https' && $explicitPort === 443) || ($scheme === 'http' && $explicitPort === 80))
            ? ':' . $explicitPort
            : '';
        return $scheme . '://' . strtolower((string) $parts['host']) . $port;
    }

    /** @param array<string, string> $headers */
    private function looksLikeJson(string $raw, array $headers): bool
    {
        if (trim($raw) === '') return false;
        foreach ($headers as $name => $value) {
            if (strcasecmp($name, 'Content-Type') === 0 && str_contains(strtolower($value), 'json')) return true;
        }
        return in_array(ltrim($raw)[0] ?? '', ['{', '['], true);
    }
}
