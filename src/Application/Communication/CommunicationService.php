<?php

namespace EventFlow\Application\Communication;

use EventFlow\Application\Audit\AuditAction;
use EventFlow\Application\Audit\AuditEntityType;
use EventFlow\Application\Audit\AuditEvent;
use EventFlow\Application\Audit\AuditService;
use EventFlow\Application\Authorization\AuthorizationService;
use EventFlow\Application\Authorization\Capability;
use EventFlow\Application\Authorization\PrincipalContext;
use EventFlow\Application\Authorization\PrincipalType;
use EventFlow\Application\Clock\Clock;
use EventFlow\Application\Idempotency\IdempotencyOutcome;
use EventFlow\Application\Idempotency\IdempotencyResultReference;
use EventFlow\Application\Idempotency\IdempotencyService;
use EventFlow\Application\Idempotency\IdempotentOperationResult;
use EventFlow\Application\Persistence\EventScope;

final readonly class CommunicationService implements TemplateCommands
{
    public function __construct(private CommunicationRepository $repository, private AuthorizationService $authorization, private IdempotencyService $idempotency, private AuditService $audit, private Clock $clock, private TemplateRenderer $renderer) {}

    /** @param list<string> $allowedFields */
    public function createDraft(PrincipalContext $principal, EventScope $scope, string $key, string $name, CommunicationChannel $channel, string $type, ?string $subject, string $body, ?string $plainText, array $allowedFields, string $idempotencyKey): IdempotencyOutcome
    {
        $key = trim($key); $name = trim($name); $type = trim($type); $allowedFields = array_values(array_unique($allowedFields)); sort($allowedFields);
        if (!preg_match('/^[a-z][a-z0-9_.-]{0,99}$/', $key) || $name === '' || strlen($name) > 190 || !preg_match('/^[a-z][a-z0-9_-]{0,63}$/', $type) || trim($body) === '') throw new CommunicationException('communication_template_invalid');
        foreach ($allowedFields as $field) if (!is_string($field) || !preg_match('/^[a-z][a-z0-9_]*$/', $field)) throw new CommunicationException('template_merge_field_invalid');
        $this->renderer->render(($subject ?? '') . $body . ($plainText ?? ''), $allowedFields, [], true);
        return $this->idempotency->execute($principal, $scope, 'template.create_draft', $idempotencyKey, ['key'=>$key,'name'=>$name,'channel'=>$channel->value,'type'=>$type,'subject'=>$subject,'body'=>$body,'plain_text'=>$plainText,'allowed_fields'=>$allowedFields], function () use ($principal,$scope,$key,$name,$channel,$type,$subject,$body,$plainText,$allowedFields) {
            $this->authorization->requireEventCapability($principal,$scope,Capability::MANAGE_TEMPLATES);
            $template = $this->repository->createDraft($scope,$key,$name,$channel,$type,$subject,$body,$plainText,$allowedFields,$this->actor($principal),$this->clock->now());
            $this->audit->recordRequired(new AuditEvent($principal,$scope,AuditAction::TEMPLATE_CREATED,AuditEntityType::COMMUNICATION_TEMPLATE,$template->templateId,after:['key'=>$template->templateKey,'version'=>$template->version,'status'=>'draft']));
            return new IdempotentOperationResult(new IdempotencyResultReference('communication_template',$template->templateId,201),$template);
        });
    }

    public function publish(PrincipalContext $principal, EventScope $scope, int $templateId, string $idempotencyKey): IdempotencyOutcome
    {
        return $this->idempotency->execute($principal,$scope,'template.publish',$idempotencyKey,['template_id'=>$templateId],function()use($principal,$scope,$templateId){$this->authorization->requireEventCapability($principal,$scope,Capability::MANAGE_TEMPLATES);$template=$this->repository->publish($scope,$templateId,$this->actor($principal),$this->clock->now());$this->audit->recordRequired(new AuditEvent($principal,$scope,AuditAction::TEMPLATE_PUBLISHED,AuditEntityType::COMMUNICATION_TEMPLATE,$template->templateId,after:['version'=>$template->version,'status'=>'published']));return new IdempotentOperationResult(new IdempotencyResultReference('communication_template',$template->templateId,200),$template);});
    }

    /** @param array<string,mixed> $audience */
    public function createCampaign(PrincipalContext $principal, EventScope $scope, int $templateId, string $name, CommunicationChannel $channel, CampaignPurpose $purpose, AudienceMode $mode, array $audience, string $idempotencyKey): IdempotencyOutcome
    {
        $name=trim($name); $audience=$this->validateAudience($audience,$mode);
        return $this->idempotency->execute($principal,$scope,'campaign.create',$idempotencyKey,['template_id'=>$templateId,'name'=>$name,'channel'=>$channel->value,'purpose'=>$purpose->value,'mode'=>$mode->value,'audience'=>$audience],function()use($principal,$scope,$templateId,$name,$channel,$purpose,$mode,$audience){$this->authorization->requireEventCapability($principal,$scope,Capability::QUEUE_CAMPAIGN);$campaign=$this->repository->createCampaign($scope,$templateId,$name,$channel,$purpose,$mode,$audience,$this->actor($principal),$this->clock->now());$this->audit->recordRequired(new AuditEvent($principal,$scope,AuditAction::CAMPAIGN_CREATED,AuditEntityType::CAMPAIGN,$campaign->campaignId,after:['purpose'=>$purpose->value,'audience_mode'=>$mode->value]));return new IdempotentOperationResult(new IdempotencyResultReference('campaign',$campaign->campaignId,201),$campaign);});
    }

    /** @param array<string,string> $values @return array{subject:?string,body:string,plain_text:?string} */
    public function preview(PrincipalContext $principal, EventScope $scope, TemplateRecord $template, array $values): array { $this->authorization->requireEventCapability($principal,$scope,Capability::MANAGE_TEMPLATES); return ['subject'=>$template->subject===null?null:$this->renderer->render($template->subject,$template->allowedFields,$values,true),'body'=>$this->renderer->render($template->body,$template->allowedFields,$values,true),'plain_text'=>$template->plainText===null?null:$this->renderer->render($template->plainText,$template->allowedFields,$values,true)]; }

    public function queue(PrincipalContext $principal, EventScope $scope, int $campaignId, string $idempotencyKey): IdempotencyOutcome
    {
        return $this->idempotency->execute($principal,$scope,'campaign.queue',$idempotencyKey,['campaign_id'=>$campaignId],function()use($principal,$scope,$campaignId){$this->authorization->requireEventCapability($principal,$scope,Capability::QUEUE_CAMPAIGN);$campaign=$this->repository->lockCampaign($scope,$campaignId)??throw new CommunicationException('resource_not_found');if(!in_array($campaign->status,['draft','scheduled'],true))throw new CommunicationException('campaign_already_queued');$template=$this->repository->lockPublishedTemplate($scope,$campaign->templateId)??throw new CommunicationException('campaign_template_invalid');if($template->channel!==$campaign->channel)throw new CommunicationException('campaign_channel_invalid');$recipients=$this->repository->resolveRecipients($scope,$campaign);$messages=[];foreach($recipients as $recipient){$logical=hash('sha256','campaign:'.$campaign->campaignId.':recipient:'.$recipient->identity());$subject=$template->subject===null?null:$this->renderer->render($template->subject,$template->allowedFields,$recipient->mergeFields);$body=$this->renderer->render($template->body,$template->allowedFields,$recipient->mergeFields);$plain=$template->plainText===null?null:$this->renderer->render($template->plainText,$template->allowedFields,$recipient->mergeFields);$messages[]=$this->repository->createOrFindMessage($scope,$campaign,$recipient,$logical,$subject,$body,$plain,$this->clock->now());}$this->repository->freezeQueued($scope,$campaign,count($messages),$this->clock->now());$this->audit->recordRequired(new AuditEvent($principal,$scope,AuditAction::CAMPAIGN_QUEUED,AuditEntityType::CAMPAIGN,$campaign->campaignId,after:['purpose'=>$campaign->purpose->value,'audience_mode'=>$campaign->audienceMode->value,'recipient_count'=>count($messages)]));$result=new CampaignQueueResult($campaign->campaignId,count($messages),$messages);return new IdempotentOperationResult(new IdempotencyResultReference('campaign',$campaign->campaignId,202),$result);});
    }

    private function validateAudience(array $audience, AudienceMode $mode): array { $filter=$audience['filter']??'active_invitations';if(!in_array($filter,['active_invitations','confirmed_attendees'],true))throw new CommunicationException('campaign_audience_invalid');$ids=$audience['invitation_ids']??[];if(!is_array($ids))throw new CommunicationException('campaign_audience_invalid');$ids=array_values(array_unique($ids));sort($ids,SORT_NUMERIC);foreach($ids as $id)if(!is_int($id)||$id<1)throw new CommunicationException('campaign_audience_invalid');if($mode===AudienceMode::SNAPSHOT&&$ids===[])throw new CommunicationException('campaign_snapshot_audience_required');return ['mode'=>$mode->value,'filter'=>$filter,'invitation_ids'=>$ids]; }
    private function actor(PrincipalContext $principal): ?int { return $principal->type===PrincipalType::WORDPRESS_USER?$principal->userId:null; }
}
