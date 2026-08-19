<?php

namespace EventFlow\Presentation\Api;

use DateTimeImmutable;
use DateTimeZone;
use EventFlow\Application\Communication\{TemplatePage, TemplateRecord};
use EventFlow\Application\Error\RequestId;
use EventFlow\Application\Idempotency\IdempotencyOutcome;
use EventFlow\Application\Persistence\EventScope;

final readonly class TemplateAccessPresenter
{
    public function page(TemplatePage $page,RequestId $requestId):JsonApiResponse{return new JsonApiResponse(200,['data'=>array_map($this->template(...),$page->templates),'meta'=>['next_after'=>$page->nextAfterTemplateId],'request_id'=>$requestId->value],$this->headers($requestId));}
    public function resource(TemplateRecord $template,RequestId $requestId):JsonApiResponse{return new JsonApiResponse(200,['data'=>$this->template($template),'request_id'=>$requestId->value],$this->headers($requestId,$template->revision));}
    public function outcome(IdempotencyOutcome $outcome,EventScope $scope,RequestId $requestId):JsonApiResponse{$template=$outcome->response instanceof TemplateRecord?$outcome->response:null;$data=$template===null?['type'=>$outcome->reference->entityType,'id'=>$outcome->reference->entityId]:$this->template($template);$headers=$this->headers($requestId,$template?->revision);$headers['Location']='/wp-json/eventflow/v1/events/'.$scope->eventId.'/communication-templates/'.$outcome->reference->entityId;return new JsonApiResponse($outcome->reference->responseStatusCode,['data'=>$data,'meta'=>['replayed'=>$outcome->replayed],'request_id'=>$requestId->value],$headers);}
    /** @param array{template_id:int,revision:int,subject:?string,body:string,plain_text:?string} $preview */
    public function preview(array $preview,RequestId $requestId):JsonApiResponse{$headers=$this->headers($requestId);$headers['ETag']='"'.hash('sha256',(string)json_encode($preview,JSON_THROW_ON_ERROR)).'"';return new JsonApiResponse(200,['data'=>$preview,'request_id'=>$requestId->value],$headers);}
    /** @return array<string,mixed> */
    private function template(TemplateRecord $t):array{return ['id'=>$t->templateId,'key'=>$t->templateKey,'name'=>$t->name,'channel'=>$t->channel->value,'type'=>$t->type,'version'=>$t->version,'status'=>$t->status,'revision'=>$t->revision,'subject'=>$t->subject,'body'=>$t->body,'plain_text'=>$t->plainText,'allowed_fields'=>$t->allowedFields,'created_at'=>$this->date($t->createdAt),'updated_at'=>$this->date($t->updatedAt),'published_at'=>$this->date($t->publishedAt),'archived_at'=>$this->date($t->archivedAt)];}
    /** @return array<string,string> */ private function headers(RequestId $requestId,?int $revision=null):array{$headers=['X-Request-ID'=>$requestId->value,'Cache-Control'=>'no-store, max-age=0','Pragma'=>'no-cache'];if($revision!==null)$headers['ETag']='"'.$revision.'"';return $headers;}
    private function date(?DateTimeImmutable $date):?string{return $date?->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d\TH:i:s\Z');}
}
