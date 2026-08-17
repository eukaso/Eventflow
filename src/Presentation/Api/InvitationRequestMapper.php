<?php

namespace EventFlow\Presentation\Api;

use DateTimeImmutable;
use EventFlow\Application\Invitation\CreateInvitation;
use EventFlow\Application\Persistence\EventScope;
use Exception;
use InvalidArgumentException;

final readonly class InvitationRequestMapper
{
    public function create(RestRequest $request): CreateInvitation
    {
        $json = $this->only($request, ['primary_name', 'capacity', 'primary_email', 'primary_phone', 'token_expires_at']);
        try {
            return new CreateInvitation(
                $this->scope($request),
                $this->requiredString($json, 'primary_name'),
                $this->capacity($json['capacity'] ?? 1),
                $this->optionalString($json['primary_email'] ?? null),
                $this->optionalString($json['primary_phone'] ?? null),
                $this->date($json['token_expires_at'] ?? null),
            );
        } catch (RequestInputException $failure) {
            throw $failure;
        } catch (InvalidArgumentException) {
            throw new RequestInputException('validation_failed');
        }
    }

    public function replacementExpiry(RestRequest $request): ?DateTimeImmutable
    {
        $json = $this->only($request, ['token_expires_at']);
        return $this->date($json['token_expires_at'] ?? null);
    }

    public function scope(RestRequest $request): EventScope
    {
        return new EventScope($this->routeId($request, 'event_id'));
    }

    public function invitationId(RestRequest $request): int
    {
        return $this->routeId($request, 'invitation_id');
    }

    public function requireEmptyBody(RestRequest $request): void
    {
        if ($request->json() !== []) throw new RequestInputException('validation_failed');
    }

    /** @param list<string> $allowed @return array<string, mixed> */
    private function only(RestRequest $request, array $allowed): array
    {
        $json = $request->json();
        if (array_diff(array_keys($json), $allowed) !== []) throw new RequestInputException('validation_failed');
        return $json;
    }

    /** @param array<string, mixed> $json */
    private function requiredString(array $json, string $field): string
    {
        $value = $json[$field] ?? null;
        if (!is_string($value)) throw new RequestInputException('validation_failed');
        return trim($value);
    }

    private function optionalString(mixed $value): ?string
    {
        if ($value === null) return null;
        if (!is_string($value)) throw new RequestInputException('validation_failed');
        return trim($value);
    }

    private function capacity(mixed $value): int
    {
        if (!is_int($value) || $value < 1 || $value > 65535) throw new RequestInputException('validation_failed');
        return $value;
    }

    private function routeId(RestRequest $request, string $name): int
    {
        $candidate = $request->route($name);
        if ($candidate === null || !ctype_digit($candidate)) throw new RequestInputException('resource_not_found');
        $value = filter_var($candidate, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if ($value === false) throw new RequestInputException('resource_not_found');
        return $value;
    }

    private function date(mixed $value): ?DateTimeImmutable
    {
        if ($value === null) return null;
        if (!is_string($value) || !preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}(?:Z|[+-]\d{2}:\d{2})$/', $value)) {
            throw new RequestInputException('validation_failed');
        }
        try { $date = new DateTimeImmutable($value); } catch (Exception) { throw new RequestInputException('validation_failed'); }
        $canonical = str_ends_with($value, 'Z') ? substr($value, 0, -1) . '+00:00' : $value;
        if ($date->format('Y-m-d\TH:i:sP') !== $canonical) throw new RequestInputException('validation_failed');
        return $date;
    }
}
