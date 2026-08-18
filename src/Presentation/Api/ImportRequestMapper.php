<?php

namespace EventFlow\Presentation\Api;

use EventFlow\Application\Import\ImportMapping;
use EventFlow\Application\Persistence\EventScope;
use InvalidArgumentException;

final readonly class ImportRequestMapper
{
    public function validation(RestRequest $request): ImportValidationInput
    {
        $json = $this->only($request->json(), ['mapping']);
        $mapping = $json['mapping'] ?? null;
        if (!is_array($mapping) || array_is_list($mapping)) throw new RequestInputException('validation_failed');
        $mapping = $this->only($mapping, ['primary_name', 'primary_email', 'primary_phone', 'capacity']);
        $columns = [];
        foreach ($mapping as $target => $source) {
            if (!is_string($source)) throw new RequestInputException('validation_failed');
            $columns[$target] = trim($source);
        }
        try {
            $typed = new ImportMapping($columns);
        } catch (InvalidArgumentException) {
            throw new RequestInputException('validation_failed');
        }
        return new ImportValidationInput(
            new EventScope($this->routeId($request, 'event_id')),
            $this->routeId($request, 'import_job_id'),
            $typed,
        );
    }

    /** @param array<string, mixed> $source @param list<string> $allowed @return array<string, mixed> */
    private function only(array $source, array $allowed): array
    {
        if (array_diff(array_keys($source), $allowed) !== []) throw new RequestInputException('validation_failed');
        return $source;
    }

    private function routeId(RestRequest $request, string $name): int
    {
        $candidate = $request->route($name);
        if ($candidate === null || !ctype_digit($candidate)) throw new RequestInputException('resource_not_found');
        $value = filter_var($candidate, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if ($value === false) throw new RequestInputException('resource_not_found');
        return $value;
    }
}
