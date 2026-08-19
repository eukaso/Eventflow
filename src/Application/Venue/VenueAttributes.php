<?php

namespace EventFlow\Application\Venue;

use InvalidArgumentException;

final readonly class VenueAttributes
{
    private const DEFAULTS = [
        'status' => 'active', 'address_line_1' => null, 'address_line_2' => null, 'city' => null,
        'region' => null, 'postal_code' => null, 'country_code' => null, 'latitude' => null,
        'longitude' => null, 'phone' => null, 'email' => null, 'website_url' => null,
        'default_capacity' => null, 'notes' => null,
    ];

    /** @var array<string, mixed> */
    private array $values;

    /** @param array<string, mixed> $values */
    public function __construct(array $values)
    {
        if (!array_key_exists('name', $values) || array_diff(array_keys($values), ['name', ...array_keys(self::DEFAULTS)]) !== []) {
            throw new InvalidArgumentException('venue_attributes_invalid');
        }
        $values = array_replace(self::DEFAULTS, $values);
        $values['name'] = $this->text($values['name'], 190, false);
        if (!in_array($values['status'], ['active', 'inactive'], true)) throw new InvalidArgumentException('venue_status_invalid');
        foreach (['address_line_1'=>190,'address_line_2'=>190,'city'=>120,'region'=>120,'postal_code'=>32,'phone'=>40,'notes'=>65535] as $field => $max) {
            $values[$field] = $this->text($values[$field], $max, true);
        }
        if ($values['country_code'] !== null && (!is_string($values['country_code']) || !preg_match('/^[A-Z]{2}$/', $values['country_code']))) {
            throw new InvalidArgumentException('venue_country_invalid');
        }
        foreach (['latitude'=>[-90.0,90.0],'longitude'=>[-180.0,180.0]] as $field => [$min,$max]) {
            if ($values[$field] !== null && (!is_float($values[$field]) && !is_int($values[$field]) || $values[$field] < $min || $values[$field] > $max)) {
                throw new InvalidArgumentException('venue_coordinates_invalid');
            }
            if ($values[$field] !== null) $values[$field] = (float) $values[$field];
        }
        if ($values['email'] !== null && (!is_string($values['email']) || strlen($values['email']) > 190 || filter_var($values['email'], FILTER_VALIDATE_EMAIL) === false)) {
            throw new InvalidArgumentException('venue_email_invalid');
        }
        if ($values['website_url'] !== null && (!is_string($values['website_url']) || strlen($values['website_url']) > 500 || filter_var($values['website_url'], FILTER_VALIDATE_URL) === false || !preg_match('/^https?:\/\//i', $values['website_url']))) {
            throw new InvalidArgumentException('venue_website_invalid');
        }
        if ($values['default_capacity'] !== null && (!is_int($values['default_capacity']) || $values['default_capacity'] < 1)) {
            throw new InvalidArgumentException('venue_capacity_invalid');
        }
        $this->values = $values;
    }

    public function get(string $field): mixed { return $this->values[$field] ?? null; }
    /** @return array<string, mixed> */ public function all(): array { return $this->values; }

    private function text(mixed $value, int $maximum, bool $nullable): ?string
    {
        if ($value === null && $nullable) return null;
        if (!is_string($value)) throw new InvalidArgumentException('venue_attributes_invalid');
        $value = trim($value);
        if ((!$nullable && $value === '') || strlen($value) > $maximum) throw new InvalidArgumentException('venue_attributes_invalid');
        return $value === '' && $nullable ? null : $value;
    }
}
