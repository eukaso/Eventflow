<?php

namespace EventFlow\Application\Communication;

use EventFlow\Application\Audit\{AuditAction, AuditEntityType, AuditEvent, AuditService};
use EventFlow\Application\Authorization\{AuthorizationService, Capability, PrincipalContext, PrincipalType};
use EventFlow\Application\Clock\Clock;
use EventFlow\Application\Idempotency\{IdempotencyOutcome, IdempotencyResultReference, IdempotencyService, IdempotentOperationResult};
use EventFlow\Application\Persistence\EventScope;

final readonly class TemplateAccessService implements TemplateAccess
{
    public function __construct(
        private TemplateAccessRepository $templates,
        private AuthorizationService $authorization,
        private IdempotencyService $idempotency,
        private AuditService $audit,
        private Clock $clock,
        private TemplateRenderer $renderer,
    ) {}

    public function list(PrincipalContext $principal, EventScope $scope, int $limit = 50, ?int $afterTemplateId = null): TemplatePage
    {
        $this->authorization->requireEventCapability($principal, $scope, Capability::MANAGE_TEMPLATES);
        if ($limit < 1 || $limit > 100 || ($afterTemplateId !== null && $afterTemplateId < 1)) throw new CommunicationException('validation_failed');
        return $this->templates->listTemplates($scope, $limit, $afterTemplateId);
    }

    public function read(PrincipalContext $principal, EventScope $scope, int $templateId): TemplateRecord
    {
        $this->authorization->requireEventCapability($principal, $scope, Capability::MANAGE_TEMPLATES);
        if ($templateId < 1) throw new CommunicationException('resource_not_found');
        return $this->templates->findTemplate($scope, $templateId) ?? throw new CommunicationException('resource_not_found');
    }

    public function update(PrincipalContext $principal, EventScope $scope, int $templateId, TemplateReplacement $replacement, string $idempotencyKey): IdempotencyOutcome
    {
        if ($templateId < 1) throw new CommunicationException('resource_not_found');
        $normalized = $this->replacement($replacement);
        return $this->idempotency->execute($principal, $scope, 'template.update', $idempotencyKey, ['template_id'=>$templateId,...$this->canonical($normalized)], function () use ($principal,$scope,$templateId,$normalized) {
            $this->authorization->requireEventCapability($principal,$scope,Capability::MANAGE_TEMPLATES);
            $current=$this->templates->lockTemplate($scope,$templateId)??throw new CommunicationException('resource_not_found');
            if($current->revision!==$normalized->expectedRevision)throw new CommunicationException('resource_modified');
            if($current->status!=='draft')throw new CommunicationException('template_immutable');
            $updated=$this->templates->updateTemplate($scope,$current,$normalized,$this->actor($principal),$this->clock->now());
            $this->audit($principal,$scope,$updated,AuditAction::TEMPLATE_UPDATED,$current);
            return $this->result($updated,200);
        });
    }

    public function newVersion(PrincipalContext $principal, EventScope $scope, int $templateId, int $expectedRevision, string $idempotencyKey): IdempotencyOutcome
    {
        if ($templateId < 1) throw new CommunicationException('resource_not_found');
        if ($expectedRevision < 1) throw new CommunicationException('communication_template_invalid');
        return $this->idempotency->execute($principal,$scope,'template.new_version',$idempotencyKey,['template_id'=>$templateId,'expected_revision'=>$expectedRevision],function()use($principal,$scope,$templateId,$expectedRevision){
            $this->authorization->requireEventCapability($principal,$scope,Capability::MANAGE_TEMPLATES);
            $current=$this->templates->lockTemplate($scope,$templateId)??throw new CommunicationException('resource_not_found');
            if($current->revision!==$expectedRevision)throw new CommunicationException('resource_modified');
            if($current->status!=='published')throw new CommunicationException('template_transition_invalid');
            $created=$this->templates->createTemplateVersion($scope,$current,$this->actor($principal),$this->clock->now());
            $this->audit($principal,$scope,$created,AuditAction::TEMPLATE_VERSION_CREATED,$current);
            return $this->result($created,201);
        });
    }

    public function archive(PrincipalContext $principal, EventScope $scope, int $templateId, int $expectedRevision, string $idempotencyKey): IdempotencyOutcome
    {
        if ($templateId < 1) throw new CommunicationException('resource_not_found');
        if ($expectedRevision < 1) throw new CommunicationException('communication_template_invalid');
        return $this->idempotency->execute($principal,$scope,'template.archive',$idempotencyKey,['template_id'=>$templateId,'expected_revision'=>$expectedRevision],function()use($principal,$scope,$templateId,$expectedRevision){
            $this->authorization->requireEventCapability($principal,$scope,Capability::MANAGE_TEMPLATES);
            $current=$this->templates->lockTemplate($scope,$templateId)??throw new CommunicationException('resource_not_found');
            if($current->revision!==$expectedRevision)throw new CommunicationException('resource_modified');
            if($current->status==='archived')throw new CommunicationException('template_transition_invalid');
            if($this->templates->templateHasMutableCampaigns($scope,$templateId))throw new CommunicationException('template_in_use');
            $archived=$this->templates->archiveTemplate($scope,$current,$this->actor($principal),$this->clock->now());
            $this->audit($principal,$scope,$archived,AuditAction::TEMPLATE_ARCHIVED,$current);
            return $this->result($archived,200);
        });
    }

    public function preview(PrincipalContext $principal, EventScope $scope, int $templateId, array $values): array
    {
        $this->authorization->requireEventCapability($principal,$scope,Capability::MANAGE_TEMPLATES);
        if($templateId<1)throw new CommunicationException('resource_not_found');
        $template=$this->templates->findTemplate($scope,$templateId)??throw new CommunicationException('resource_not_found');
        if($template->status==='archived')throw new CommunicationException('template_transition_invalid');
        foreach($values as $field=>$value)if(!is_string($field)||!is_string($value)||!in_array($field,$template->allowedFields,true))throw new CommunicationException('template_merge_field_invalid');
        return ['template_id'=>$templateId,'revision'=>$template->revision,'subject'=>$template->subject===null?null:$this->renderer->render($template->subject,$template->allowedFields,$values,true),'body'=>$this->renderer->render($template->body,$template->allowedFields,$values,true),'plain_text'=>$template->plainText===null?null:$this->renderer->render($template->plainText,$template->allowedFields,$values,true)];
    }

    private function replacement(TemplateReplacement $r): TemplateReplacement
    {
        $fields=array_values(array_unique($r->allowedFields));sort($fields);
        if(trim($r->name)===''||strlen(trim($r->name))>190||!preg_match('/^[a-z][a-z0-9_-]{0,63}$/',trim($r->type))||trim($r->body)==='')throw new CommunicationException('communication_template_invalid');
        foreach($fields as $field)if(!is_string($field)||!preg_match('/^[a-z][a-z0-9_]*$/',$field))throw new CommunicationException('template_merge_field_invalid');
        $this->renderer->render(($r->subject??'').$r->body.($r->plainText??''),$fields,[],true);
        return new TemplateReplacement(trim($r->name),trim($r->type),$r->subject,$r->body,$r->plainText,$fields,$r->expectedRevision);
    }

    private function canonical(TemplateReplacement $r):array{return ['name'=>$r->name,'type'=>$r->type,'subject'=>$r->subject,'body'=>$r->body,'plain_text'=>$r->plainText,'allowed_fields'=>$r->allowedFields,'expected_revision'=>$r->expectedRevision];}
    private function actor(PrincipalContext $p):int{if($p->type!==PrincipalType::WORDPRESS_USER||$p->userId===null)throw new CommunicationException('template_actor_invalid');return $p->userId;}
    private function result(TemplateRecord $t,int $status):IdempotentOperationResult{return new IdempotentOperationResult(new IdempotencyResultReference('communication_template',$t->templateId,$status),$t);}
    private function audit(PrincipalContext $p,EventScope $s,TemplateRecord $after,AuditAction $action,TemplateRecord $before):void{$this->audit->recordRequired(new AuditEvent($p,$s,$action,AuditEntityType::COMMUNICATION_TEMPLATE,$after->templateId,before:['version'=>$before->version,'status'=>$before->status,'revision'=>$before->revision],after:['version'=>$after->version,'status'=>$after->status,'revision'=>$after->revision]));}
}
