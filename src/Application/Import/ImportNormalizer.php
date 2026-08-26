<?php

namespace EventFlow\Application\Import;

use EventFlow\Application\Invitation\CompanionRolloutPolicy;

final readonly class ImportNormalizer
{
    /** @return array{normalized: array<string, mixed>|null, errors: list<string>, warnings: list<string>} */
    public function normalize(array $raw, ImportMapping $mapping): array
    {
        $value = static fn (string $target): ?string => isset($mapping->columns[$target]) && isset($raw[$mapping->columns[$target]]) ? trim((string) $raw[$mapping->columns[$target]]) : null;
        $name = $value('primary_name'); $email = $value('primary_email'); $phone = $value('primary_phone'); $capacityRaw = $value('capacity');
        $errors = []; $warnings = [];
        if ($name === null || $name === '' || strlen($name) > 190) $errors[] = 'primary_name_invalid';
        if ($email !== null && $email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL) === false) $errors[] = 'primary_email_invalid';
        if ($phone !== null && strlen($phone) > 40) $errors[] = 'primary_phone_invalid';
        $requestedCapacity = $capacityRaw === null || $capacityRaw === '' ? 1 : filter_var($capacityRaw, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 65535]]);
        if ($requestedCapacity === false) $errors[] = 'capacity_invalid';
        $capacity = $requestedCapacity === false ? false : CompanionRolloutPolicy::importedCapacity();
        if (is_int($requestedCapacity) && $requestedCapacity !== $capacity) $warnings[] = 'capacity_adjusted_for_initial_rollout';
        if (($email === null || $email === '') && ($phone === null || $phone === '')) $warnings[] = 'delivery_contact_missing';
        return [
            'normalized' => $errors === [] ? ['primary_name' => $name, 'primary_email' => $email === '' ? null : $email, 'primary_phone' => $phone === '' ? null : $phone, 'capacity' => $capacity] : null,
            'errors' => $errors,
            'warnings' => $warnings,
        ];
    }
}
