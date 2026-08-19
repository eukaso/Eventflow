<?php

namespace EventFlow\Presentation\Api;

use DateTimeImmutable;
use DateTimeZone;
use EventFlow\Application\Communication\{CampaignAudiencePreview, CampaignPage, CampaignRecord};
use EventFlow\Application\Error\RequestId;
use EventFlow\Application\Idempotency\IdempotencyOutcome;

final readonly class CampaignAccessPresenter
{
    public function page(CampaignPage $page, RequestId $requestId): JsonApiResponse
    {
        return new JsonApiResponse(200, ['data'=>array_map($this->campaign(...),$page->campaigns),'meta'=>['next_after'=>$page->nextAfterCampaignId],'request_id'=>$requestId->value], $this->headers($requestId));
    }

    public function resource(CampaignRecord $campaign, RequestId $requestId): JsonApiResponse
    {
        return new JsonApiResponse(200, ['data'=>$this->campaign($campaign),'request_id'=>$requestId->value], $this->headers($requestId,$campaign->revision));
    }

    public function outcome(IdempotencyOutcome $outcome, int $eventId, RequestId $requestId): JsonApiResponse
    {
        $campaign = $outcome->response instanceof CampaignRecord ? $outcome->response : null;
        $data = $campaign === null ? ['type'=>$outcome->reference->entityType,'id'=>$outcome->reference->entityId] : $this->campaign($campaign);
        $headers = $this->headers($requestId, $campaign?->revision);
        $headers['Location'] = '/wp-json/eventflow/v1/events/'.$eventId.'/campaigns/'.$outcome->reference->entityId;
        return new JsonApiResponse($outcome->reference->responseStatusCode, ['data'=>$data,'meta'=>['replayed'=>$outcome->replayed],'request_id'=>$requestId->value], $headers);
    }

    public function preview(CampaignAudiencePreview $preview, RequestId $requestId): JsonApiResponse
    {
        $data = ['campaign_id'=>$preview->campaignId,'recipient_count'=>$preview->recipientCount,'audience_fingerprint'=>$preview->audienceFingerprint];
        $headers = $this->headers($requestId);
        $headers['ETag'] = '"'.$preview->audienceFingerprint.'"';
        return new JsonApiResponse(200, ['data'=>$data,'request_id'=>$requestId->value], $headers);
    }

    /** @return array<string,mixed> */
    private function campaign(CampaignRecord $campaign): array
    {
        return ['id'=>$campaign->campaignId,'template_id'=>$campaign->templateId,'name'=>$campaign->name,'channel'=>$campaign->channel->value,'purpose'=>$campaign->purpose->value,'audience_mode'=>$campaign->audienceMode->value,'audience'=>$campaign->audienceDefinition,'status'=>$campaign->status,'revision'=>$campaign->revision,'scheduled_at'=>$this->date($campaign->scheduledAt),'started_at'=>$this->date($campaign->startedAt),'completed_at'=>$this->date($campaign->completedAt),'cancelled_at'=>$this->date($campaign->cancelledAt),'recipient_count'=>$campaign->recipientCount];
    }

    /** @return array<string,string> */
    private function headers(RequestId $requestId, ?int $revision=null): array
    {
        $headers=['X-Request-ID'=>$requestId->value,'Cache-Control'=>'no-store, max-age=0','Pragma'=>'no-cache'];
        if($revision!==null)$headers['ETag']='"'.$revision.'"';
        return $headers;
    }

    private function date(?DateTimeImmutable $date): ?string { return $date?->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d\TH:i:s\Z'); }
}
