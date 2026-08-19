<?php

namespace EventFlow\Application\EventConfiguration;

use DateTimeImmutable;
use InvalidArgumentException;

final readonly class EventConfigurationAttributes
{
    private const DEFAULTS = [
        'logo_media_id'=>null,'invitation_media_id'=>null,'primary_theme'=>null,'secondary_theme'=>null,
        'welcome_message'=>null,'confirmation_message'=>null,'surprise_notice'=>null,'dress_code'=>null,
        'confirmation_opens_at'=>null,'confirmation_closes_at'=>null,'allow_guest_edits'=>false,
        'seating_mode'=>'table','automatic_seating_enabled'=>false,'default_from_name'=>null,
        'reply_to_email'=>null,'default_sms_sender'=>null,
    ];

    /** @var array<string, mixed> */ private array $values;

    /** @param array<string, mixed> $values */
    public function __construct(array $values = [])
    {
        if (array_diff(array_keys($values), array_keys(self::DEFAULTS)) !== []) throw new InvalidArgumentException('event_configuration_invalid');
        $values = array_replace(self::DEFAULTS, $values);
        foreach (['logo_media_id','invitation_media_id'] as $field) {
            if ($values[$field] !== null && (!is_int($values[$field]) || $values[$field] < 1)) throw new InvalidArgumentException('event_configuration_media_invalid');
        }
        foreach (['primary_theme'=>64,'secondary_theme'=>64,'dress_code'=>255,'default_from_name'=>190,'default_sms_sender'=>64] as $field=>$max) {
            $values[$field] = $this->text($values[$field], $max);
        }
        foreach (['welcome_message','confirmation_message','surprise_notice'] as $field) $values[$field] = $this->text($values[$field], 65535);
        foreach (['confirmation_opens_at','confirmation_closes_at'] as $field) {
            if ($values[$field] !== null && !$values[$field] instanceof DateTimeImmutable) throw new InvalidArgumentException('event_configuration_window_invalid');
        }
        if ($values['confirmation_opens_at'] !== null && $values['confirmation_closes_at'] !== null && $values['confirmation_closes_at'] <= $values['confirmation_opens_at']) {
            throw new InvalidArgumentException('event_configuration_window_invalid');
        }
        foreach (['allow_guest_edits','automatic_seating_enabled'] as $field) {
            if (!is_bool($values[$field])) throw new InvalidArgumentException('event_configuration_boolean_invalid');
        }
        if (!in_array($values['seating_mode'], ['table','seat'], true)) throw new InvalidArgumentException('event_configuration_seating_mode_invalid');
        if ($values['reply_to_email'] !== null && (!is_string($values['reply_to_email']) || strlen($values['reply_to_email']) > 190 || filter_var($values['reply_to_email'], FILTER_VALIDATE_EMAIL) === false)) {
            throw new InvalidArgumentException('event_configuration_email_invalid');
        }
        $this->values = $values;
    }

    public function get(string $field): mixed { return $this->values[$field] ?? null; }
    /** @return array<string, mixed> */ public function all(): array { return $this->values; }

    private function text(mixed $value, int $maximum): ?string
    {
        if ($value === null) return null;
        if (!is_string($value)) throw new InvalidArgumentException('event_configuration_invalid');
        $value = trim($value);
        if (strlen($value) > $maximum) throw new InvalidArgumentException('event_configuration_invalid');
        return $value === '' ? null : $value;
    }
}
