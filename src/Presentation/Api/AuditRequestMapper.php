<?php

namespace EventFlow\Presentation\Api;

use DateTimeImmutable;
use EventFlow\Application\Persistence\EventScope;

final readonly class AuditRequestMapper
{
    private const PAGE_FIELDS = ['limit','after','action','entity_type','entity_id','actor_user_id','source','occurred_from','occurred_until'];

    public function scope(RestRequest $request): EventScope
    {
        return new EventScope($this->routeId($request, 'event_id'));
    }

    public function auditLogId(RestRequest $request): int
    {
        return $this->routeId($request, 'audit_log_id');
    }

    public function requireNoQuery(RestRequest $request): void
    {
        if ($request->queries() !== []) throw new RequestInputException('validation_failed');
    }

    /** @return array{int,?int,?string,?string,?int,?int,?string,?DateTimeImmutable,?DateTimeImmutable} */
    public function page(RestRequest $request): array
    {
        if (array_diff(array_keys($request->queries()), self::PAGE_FIELDS) !== []) {
            throw new RequestInputException('validation_failed');
        }

        return [
            $this->queryInt($request->query('limit'), 50),
            $this->optionalInt($request->query('after')),
            $this->optionalText($request->query('action')),
            $this->optionalText($request->query('entity_type')),
            $this->optionalInt($request->query('entity_id')),
            $this->optionalInt($request->query('actor_user_id')),
            $this->optionalText($request->query('source')),
            $this->date($request->query('occurred_from')),
            $this->date($request->query('occurred_until')),
        ];
    }

    private function routeId(RestRequest $request, string $name): int
    {
        $candidate = $request->route($name);
        if ($candidate === null || !preg_match('/^[1-9][0-9]*$/', $candidate)) {
            throw new RequestInputException('resource_not_found');
        }
        $value = filter_var($candidate, FILTER_VALIDATE_INT, ['options'=>['min_range'=>1]]);
        if ($value === false) throw new RequestInputException('resource_not_found');
        return $value;
    }

    private function queryInt(?string $value, int $default): int
    {
        if ($value === null) return $default;
        $result = $this->positiveInt($value);
        if ($result > 100) throw new RequestInputException('validation_failed');
        return $result;
    }

    private function optionalInt(?string $value): ?int
    {
        return $value === null ? null : $this->positiveInt($value);
    }

    private function positiveInt(string $value): int
    {
        if (!preg_match('/^[1-9][0-9]*$/', $value)) throw new RequestInputException('validation_failed');
        $result = filter_var($value, FILTER_VALIDATE_INT, ['options'=>['min_range'=>1]]);
        if ($result === false) throw new RequestInputException('validation_failed');
        return $result;
    }

    private function optionalText(?string $value): ?string
    {
        if ($value === null) return null;
        if ($value === '' || strlen($value) > 100) throw new RequestInputException('validation_failed');
        return $value;
    }

    private function date(?string $value): ?DateTimeImmutable
    {
        if ($value === null) return null;
        if (!preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}(?:\.\d{1,6})?(?:Z|[+-]\d{2}:\d{2})$/', $value)) {
            throw new RequestInputException('validation_failed');
        }
        $candidate = str_ends_with($value, 'Z') ? substr($value, 0, -1).'+00:00' : $value;
        $format = str_contains($candidate, '.') ? '!Y-m-d\TH:i:s.uP' : '!Y-m-d\TH:i:sP';
        $date = DateTimeImmutable::createFromFormat($format, $candidate);
        $errors = DateTimeImmutable::getLastErrors();
        if ($date !== false && ($errors === false || ($errors['warning_count'] === 0 && $errors['error_count'] === 0))) {
            return $date;
        }
        throw new RequestInputException('validation_failed');
    }
}
