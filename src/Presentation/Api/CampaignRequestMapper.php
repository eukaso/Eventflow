<?php

namespace EventFlow\Presentation\Api;

use EventFlow\Application\Communication\{AudienceMode, CampaignPurpose, CommunicationChannel};
use EventFlow\Application\Persistence\EventScope;

final readonly class CampaignRequestMapper
{
    public function creation(RestRequest $request): CampaignCreateInput
    {
        $json = $this->only($request->json(), ['template_id', 'name', 'channel', 'purpose', 'audience_mode', 'audience']);
        $channel = is_string($json['channel'] ?? null) ? CommunicationChannel::tryFrom($json['channel']) : null;
        $purpose = is_string($json['purpose'] ?? null) ? CampaignPurpose::tryFrom($json['purpose']) : null;
        $mode = is_string($json['audience_mode'] ?? null) ? AudienceMode::tryFrom($json['audience_mode']) : null;
        $audience = $json['audience'] ?? null;
        if ($channel === null || $purpose === null || $mode === null || !is_array($audience) || array_is_list($audience)) {
            throw new RequestInputException('validation_failed');
        }
        $audience = $this->only($audience, ['filter', 'invitation_ids']);
        $filter = $audience['filter'] ?? 'active_invitations';
        $ids = $audience['invitation_ids'] ?? [];
        if (!is_string($filter) || !is_array($ids) || !array_is_list($ids)) throw new RequestInputException('validation_failed');
        $invitationIds = [];
        foreach ($ids as $id) $invitationIds[] = $this->positiveInt($id);
        return new CampaignCreateInput(
            $this->scope($request),
            $this->positiveInt($json['template_id'] ?? null),
            $this->requiredString($json['name'] ?? null),
            $channel,
            $purpose,
            $mode,
            ['filter' => $filter, 'invitation_ids' => $invitationIds],
        );
    }

    /** @return array{scope: EventScope, campaign_id: int} */
    public function queue(RestRequest $request): array
    {
        $this->only($request->json(), []);
        return ['scope' => $this->scope($request), 'campaign_id' => $this->routeId($request, 'campaign_id')];
    }

    private function scope(RestRequest $request): EventScope { return new EventScope($this->routeId($request, 'event_id')); }

    private function routeId(RestRequest $request, string $name): int
    {
        $candidate = $request->route($name);
        if ($candidate === null || !ctype_digit($candidate)) throw new RequestInputException('resource_not_found');
        $value = filter_var($candidate, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if ($value === false) throw new RequestInputException('resource_not_found');
        return $value;
    }

    /** @param array<string, mixed> $source @param list<string> $allowed @return array<string, mixed> */
    private function only(array $source, array $allowed): array
    {
        if (array_diff(array_keys($source), $allowed) !== []) throw new RequestInputException('validation_failed');
        return $source;
    }

    private function positiveInt(mixed $value): int
    {
        if (!is_int($value) || $value < 1) throw new RequestInputException('validation_failed');
        return $value;
    }

    private function requiredString(mixed $value): string
    {
        if (!is_string($value)) throw new RequestInputException('validation_failed');
        return trim($value);
    }
}
