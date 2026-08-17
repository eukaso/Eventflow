<?php

namespace EventFlow\Presentation\Api;

use DateTimeImmutable;
use EventFlow\Application\Authorization\EventRole;
use EventFlow\Application\Membership\{ChangeMembership, GrantMembership, TransferPrimaryOwner};
use EventFlow\Application\Persistence\EventScope;
use Exception;
use InvalidArgumentException;

final readonly class MembershipRequestMapper
{
    public function grant(RestRequest $request): GrantMembership
    {
        $json = $this->only($request, ['user_id', 'role', 'expires_at']);
        try {
            return new GrantMembership(
                $this->scope($request),
                $this->positiveInt($json['user_id'] ?? null),
                $this->role($json['role'] ?? null),
                $this->date($json['expires_at'] ?? null),
            );
        } catch (RequestInputException $failure) {
            throw $failure;
        } catch (InvalidArgumentException) {
            throw new RequestInputException('validation_failed');
        }
    }

    public function change(RestRequest $request): ChangeMembership
    {
        $json = $this->only($request, ['role', 'expires_at']);
        try {
            return new ChangeMembership(
                $this->scope($request),
                $this->membershipId($request),
                $this->role($json['role'] ?? null),
                $this->date($json['expires_at'] ?? null),
            );
        } catch (RequestInputException $failure) {
            throw $failure;
        } catch (InvalidArgumentException) {
            throw new RequestInputException('validation_failed');
        }
    }

    public function transfer(RestRequest $request): TransferPrimaryOwner
    {
        $json = $this->only($request, ['expected_current_membership_id']);
        try {
            return new TransferPrimaryOwner(
                $this->scope($request),
                $this->positiveInt($json['expected_current_membership_id'] ?? null),
                $this->membershipId($request),
            );
        } catch (RequestInputException $failure) {
            throw $failure;
        } catch (InvalidArgumentException) {
            throw new RequestInputException('validation_failed');
        }
    }

    public function scope(RestRequest $request): EventScope
    {
        return new EventScope($this->routeId($request, 'event_id'));
    }

    public function membershipId(RestRequest $request): int
    {
        return $this->routeId($request, 'membership_id');
    }

    public function requireEmptyBody(RestRequest $request): void
    {
        if ($request->json() !== []) {
            throw new RequestInputException('validation_failed');
        }
    }

    /** @param list<string> $allowed @return array<string, mixed> */
    private function only(RestRequest $request, array $allowed): array
    {
        $json = $request->json();
        if (array_diff(array_keys($json), $allowed) !== []) {
            throw new RequestInputException('validation_failed');
        }
        return $json;
    }

    private function routeId(RestRequest $request, string $name): int
    {
        $candidate = $request->route($name);
        if ($candidate === null || !ctype_digit($candidate)) {
            throw new RequestInputException('resource_not_found');
        }
        $value = filter_var($candidate, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if ($value === false) {
            throw new RequestInputException('resource_not_found');
        }
        return $value;
    }

    private function positiveInt(mixed $value): int
    {
        if (!is_int($value) || $value < 1) {
            throw new RequestInputException('validation_failed');
        }
        return $value;
    }

    private function role(mixed $value): EventRole
    {
        if (!is_string($value)) {
            throw new RequestInputException('validation_failed');
        }
        return EventRole::tryFrom($value) ?? throw new RequestInputException('validation_failed');
    }

    private function date(mixed $value): ?DateTimeImmutable
    {
        if ($value === null) return null;
        if (!is_string($value) || !preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}(?:Z|[+-]\d{2}:\d{2})$/', $value)) {
            throw new RequestInputException('validation_failed');
        }
        try { $date = new DateTimeImmutable($value); } catch (Exception) { throw new RequestInputException('validation_failed'); }
        $canonical = str_ends_with($value, 'Z') ? substr($value, 0, -1) . '+00:00' : $value;
        if ($date->format('Y-m-d\TH:i:sP') !== $canonical) {
            throw new RequestInputException('validation_failed');
        }
        return $date;
    }
}
