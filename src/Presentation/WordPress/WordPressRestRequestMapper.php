<?php

namespace EventFlow\Presentation\WordPress;

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
        $raw = method_exists($wordpressRequest, 'get_body') ? $wordpressRequest->get_body() : '';
        if (!is_string($raw) || strlen($raw) > self::MAX_JSON_BYTES) {
            throw new RequestInputException('validation_failed');
        }
        $json = [];
        if (trim($raw) !== '') {
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
        return new RestRequest($headers, $json, $routes, $this->clientAddress());
    }

    /** @return array<string, string> */
    private function headers(mixed $source): array
    {
        if (!is_array($source)) return [];
        $headers = [];
        foreach ($source as $name => $value) {
            if (!is_string($name)) continue;
            $candidate = is_array($value) ? reset($value) : $value;
            if (is_string($candidate)) $headers[$name] = $candidate;
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
}
