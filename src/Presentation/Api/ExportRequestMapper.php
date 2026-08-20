<?php

namespace EventFlow\Presentation\Api;

use EventFlow\Application\Export\ExportFormat;
use EventFlow\Application\Export\ExportType;
use EventFlow\Application\Persistence\EventScope;

final readonly class ExportRequestMapper
{
    public function scope(RestRequest $request): EventScope { return new EventScope($this->routeId($request, 'event_id')); }
    public function exportId(RestRequest $request): int { return $this->routeId($request, 'export_id'); }

    /** @return array{int,?int,?string,?bool} */
    public function page(RestRequest $request): array
    {
        $containsPii = match ($request->query('contains_pii')) {
            null => null,
            'true', '1' => true,
            'false', '0' => false,
            default => throw new RequestInputException('validation_failed'),
        };
        return [
            $this->queryInt($request->query('limit'), 50, 1, 100),
            $request->query('after') === null ? null : $this->queryInt($request->query('after'), null, 1, PHP_INT_MAX),
            $request->query('status'),
            $containsPii,
        ];
    }

    /** @return array{ExportType,ExportFormat,string} */
    public function creation(RestRequest $request): array
    {
        $json = $request->json();
        if (array_diff(array_keys($json), ['type', 'format', 'purpose']) !== [] || count($json) !== 3) {
            throw new RequestInputException('validation_failed');
        }
        $type = is_string($json['type'] ?? null) ? ExportType::tryFrom($json['type']) : null;
        $format = is_string($json['format'] ?? null) ? ExportFormat::tryFrom($json['format']) : null;
        $purpose = is_string($json['purpose'] ?? null) ? trim($json['purpose']) : null;
        if ($type === null || $format === null || $purpose === null || $purpose === '' || strlen($purpose) > 500) {
            throw new RequestInputException('validation_failed');
        }
        return [$type, $format, $purpose];
    }

    private function routeId(RestRequest $request, string $name): int
    {
        $candidate = $request->route($name);
        if ($candidate === null || !ctype_digit($candidate)) throw new RequestInputException('resource_not_found');
        $value = filter_var($candidate, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if ($value === false) throw new RequestInputException('resource_not_found');
        return $value;
    }

    private function queryInt(?string $value, ?int $default, int $min, int $max): int
    {
        if ($value === null) return $default ?? throw new RequestInputException('validation_failed');
        if (!preg_match('/^[1-9][0-9]*$/', $value)) throw new RequestInputException('validation_failed');
        $result = filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => $min, 'max_range' => $max]]);
        if ($result === false) throw new RequestInputException('validation_failed');
        return $result;
    }
}
