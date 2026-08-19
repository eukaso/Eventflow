<?php

namespace EventFlow\Presentation\Api;

use DateTimeImmutable;
use Exception;
use EventFlow\Application\Communication\{AudienceMode, CampaignPurpose, CampaignRecord, CampaignReplacement, CommunicationChannel};
use EventFlow\Application\Persistence\EventScope;

final readonly class CampaignAccessRequestMapper
{
    public function scope(RestRequest $request): EventScope { return new EventScope($this->routeId($request, 'event_id')); }
    public function campaignId(RestRequest $request): int { return $this->routeId($request, 'campaign_id'); }

    /** @return array{int,?int} */
    public function page(RestRequest $request): array
    {
        return [
            $this->queryInt($request->query('limit'), 50, 1, 100),
            $request->query('after') === null ? null : $this->queryInt($request->query('after'), null, 1, PHP_INT_MAX),
        ];
    }

    public function replacement(RestRequest $request, CampaignRecord $current, int $expectedRevision): CampaignReplacement
    {
        $json = $this->only($request->json(), ['template_id', 'name', 'channel', 'purpose', 'audience_mode', 'audience']);
        if ($json === []) throw new RequestInputException('validation_failed');
        $channel = array_key_exists('channel', $json) ? $this->channel($json['channel']) : $current->channel;
        $purpose = array_key_exists('purpose', $json) ? $this->purpose($json['purpose']) : $current->purpose;
        $mode = array_key_exists('audience_mode', $json) ? $this->mode($json['audience_mode']) : $current->audienceMode;
        $audience = array_key_exists('audience', $json) ? $this->audience($json['audience']) : $current->audienceDefinition;
        unset($audience['mode']);
        return new CampaignReplacement(
            array_key_exists('template_id', $json) ? $this->positiveInt($json['template_id']) : $current->templateId,
            array_key_exists('name', $json) ? $this->string($json['name']) : $current->name,
            $channel, $purpose, $mode, $audience, $expectedRevision,
        );
    }

    public function scheduledAt(RestRequest $request): DateTimeImmutable
    {
        $json = $this->only($request->json(), ['scheduled_at']);
        if (array_keys($json) !== ['scheduled_at'] || !is_string($json['scheduled_at']) || !preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}(?:Z|[+-]\d{2}:\d{2})$/', $json['scheduled_at'])) throw new RequestInputException('validation_failed');
        try { $date = new DateTimeImmutable($json['scheduled_at']); } catch (Exception) { throw new RequestInputException('validation_failed'); }
        $canonical = str_ends_with($json['scheduled_at'], 'Z') ? substr($json['scheduled_at'], 0, -1) . '+00:00' : $json['scheduled_at'];
        if ($date->format('Y-m-d\TH:i:sP') !== $canonical) throw new RequestInputException('validation_failed');
        return $date;
    }

    public function requireEmptyBody(RestRequest $request): void
    {
        if ($request->json() !== []) throw new RequestInputException('validation_failed');
    }

    /** @return array{filter:string,invitation_ids:list<int>} */
    private function audience(mixed $value): array
    {
        if (!is_array($value) || array_is_list($value)) throw new RequestInputException('validation_failed');
        $audience = $this->only($value, ['filter', 'invitation_ids']);
        $filter = $audience['filter'] ?? 'active_invitations';
        $ids = $audience['invitation_ids'] ?? [];
        if (!is_string($filter) || !is_array($ids) || !array_is_list($ids)) throw new RequestInputException('validation_failed');
        return ['filter' => $filter, 'invitation_ids' => array_map($this->positiveInt(...), $ids)];
    }

    private function channel(mixed $value): CommunicationChannel { return is_string($value) ? CommunicationChannel::tryFrom($value) ?? throw new RequestInputException('validation_failed') : throw new RequestInputException('validation_failed'); }
    private function purpose(mixed $value): CampaignPurpose { return is_string($value) ? CampaignPurpose::tryFrom($value) ?? throw new RequestInputException('validation_failed') : throw new RequestInputException('validation_failed'); }
    private function mode(mixed $value): AudienceMode { return is_string($value) ? AudienceMode::tryFrom($value) ?? throw new RequestInputException('validation_failed') : throw new RequestInputException('validation_failed'); }
    private function string(mixed $value): string { if (!is_string($value)) throw new RequestInputException('validation_failed'); return trim($value); }
    private function positiveInt(mixed $value): int { if (!is_int($value) || $value < 1) throw new RequestInputException('validation_failed'); return $value; }
    /** @param array<string,mixed> $source @param list<string> $allowed @return array<string,mixed> */
    private function only(array $source, array $allowed): array { if (array_diff(array_keys($source), $allowed) !== []) throw new RequestInputException('validation_failed'); return $source; }
    private function routeId(RestRequest $request, string $name): int { $candidate=$request->route($name);if($candidate===null||!ctype_digit($candidate))throw new RequestInputException('resource_not_found');$value=filter_var($candidate,FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]);if($value===false)throw new RequestInputException('resource_not_found');return$value; }
    private function queryInt(?string $value, ?int $default, int $min, int $max): int { if($value===null)return$default??throw new RequestInputException('validation_failed');if(!preg_match('/^[1-9][0-9]*$/',$value))throw new RequestInputException('validation_failed');$result=filter_var($value,FILTER_VALIDATE_INT,['options'=>['min_range'=>$min,'max_range'=>$max]]);if($result===false)throw new RequestInputException('validation_failed');return$result; }
}
