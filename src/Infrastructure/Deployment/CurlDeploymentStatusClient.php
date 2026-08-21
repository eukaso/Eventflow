<?php

namespace EventFlow\Infrastructure\Deployment;

use EventFlow\Application\Deployment\DeploymentStatusClient;
use EventFlow\Application\Deployment\DeploymentStatusResponse;
use JsonException;
use RuntimeException;

final readonly class CurlDeploymentStatusClient implements DeploymentStatusClient
{
    public function __construct(
        private int $connectTimeoutSeconds = 5,
        private int $timeoutSeconds = 15,
        private int $maximumBytes = 131072,
    ) {
    }

    public function get(string $url): DeploymentStatusResponse
    {
        if (!extension_loaded('curl')) {
            throw new RuntimeException('deployment_curl_unavailable');
        }
        $handle = curl_init($url);
        if ($handle === false) {
            throw new RuntimeException('deployment_request_unavailable');
        }
        $headers = [];
        $body = '';
        curl_setopt_array($handle, [
            CURLOPT_CONNECTTIMEOUT => $this->connectTimeoutSeconds,
            CURLOPT_TIMEOUT => $this->timeoutSeconds,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_MAXREDIRS => 0,
            CURLOPT_PROTOCOLS => CURLPROTO_HTTP | CURLPROTO_HTTPS,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_HTTPHEADER => ['Accept: application/json'],
            CURLOPT_USERAGENT => 'EventFlow-Deployment-Preflight/1.0',
            CURLOPT_HEADERFUNCTION => static function ($curl, string $line) use (&$headers): int {
                $parts = explode(':', $line, 2);
                if (count($parts) === 2) {
                    $headers[strtolower(trim($parts[0]))] = trim($parts[1]);
                }
                return strlen($line);
            },
            CURLOPT_WRITEFUNCTION => function ($curl, string $chunk) use (&$body): int {
                if (strlen($body) + strlen($chunk) > $this->maximumBytes) {
                    return 0;
                }
                $body .= $chunk;
                return strlen($chunk);
            },
        ]);
        $executed = curl_exec($handle);
        $status = (int) curl_getinfo($handle, CURLINFO_RESPONSE_CODE);
        $error = curl_errno($handle);
        curl_close($handle);
        if ($executed === false || $error !== 0 || $status < 100) {
            throw new RuntimeException('deployment_request_failed');
        }
        if (!str_starts_with(strtolower($headers['content-type'] ?? ''), 'application/json')) {
            throw new RuntimeException('deployment_response_invalid');
        }
        try {
            $decoded = json_decode($body, true, 32, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new RuntimeException('deployment_response_invalid', 0, $exception);
        }
        if (!is_array($decoded)) {
            throw new RuntimeException('deployment_response_invalid');
        }
        return new DeploymentStatusResponse($status, $headers, $decoded);
    }
}
