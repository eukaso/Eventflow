<?php

namespace EventFlow\Presentation\Api;

use DateTimeImmutable;
use DateTimeZone;
use EventFlow\Application\Error\RequestId;
use EventFlow\Application\Idempotency\IdempotencyOutcome;
use EventFlow\Application\Privacy\PrivacyActionPage;
use EventFlow\Application\Privacy\PrivacyActionRecord;
use EventFlow\Application\Privacy\RetentionHoldPage;
use EventFlow\Application\Privacy\RetentionHoldRecord;

final readonly class PrivacyPresenter
{
    public function actionPage(PrivacyActionPage$page,RequestId$requestId):JsonApiResponse{return new JsonApiResponse(200,['data'=>array_map($this->action(...),$page->actions),'meta'=>['next_after'=>$page->nextAfterActionId],'request_id'=>$requestId->value],$this->headers($requestId));}
    public function actionResource(PrivacyActionRecord$action,RequestId$requestId):JsonApiResponse{return new JsonApiResponse(200,['data'=>$this->action($action),'request_id'=>$requestId->value],$this->headers($requestId));}
    public function holdPage(RetentionHoldPage$page,RequestId$requestId):JsonApiResponse{return new JsonApiResponse(200,['data'=>array_map($this->hold(...),$page->holds),'meta'=>['next_after'=>$page->nextAfterHoldId],'request_id'=>$requestId->value],$this->headers($requestId));}
    public function holdResource(RetentionHoldRecord$hold,RequestId$requestId):JsonApiResponse{return new JsonApiResponse(200,['data'=>$this->hold($hold),'request_id'=>$requestId->value],$this->headers($requestId));}

    public function actionOutcome(IdempotencyOutcome$outcome,int$eventId,RequestId$requestId):JsonApiResponse
    {
        $record=$outcome->response instanceof PrivacyActionRecord?$outcome->response:null;
        return$this->outcome($outcome,$record===null?['type'=>$outcome->reference->entityType,'id'=>$outcome->reference->entityId]:$this->action($record),'/wp-json/eventflow/v1/events/'.$eventId.'/privacy-actions/'.$outcome->reference->entityId,$requestId);
    }

    public function holdOutcome(IdempotencyOutcome$outcome,int$eventId,RequestId$requestId):JsonApiResponse
    {
        $record=$outcome->response instanceof RetentionHoldRecord?$outcome->response:null;
        return$this->outcome($outcome,$record===null?['type'=>$outcome->reference->entityType,'id'=>$outcome->reference->entityId]:$this->hold($record),'/wp-json/eventflow/v1/events/'.$eventId.'/retention-holds/'.$outcome->reference->entityId,$requestId);
    }

    /** @param array<string,mixed> $data */
    private function outcome(IdempotencyOutcome$outcome,array$data,string$location,RequestId$requestId):JsonApiResponse{$headers=$this->headers($requestId);$headers['Location']=$location;return new JsonApiResponse($outcome->reference->responseStatusCode,['data'=>$data,'meta'=>['replayed'=>$outcome->replayed],'request_id'=>$requestId->value],$headers);}
    /** @return array<string,mixed> */
    private function action(PrivacyActionRecord$x):array{return['id'=>$x->privacyActionId,'invitation_id'=>$x->invitationId,'request_kind'=>$x->requestKind,'policy_version'=>$x->policyVersion,'purpose'=>$x->purpose,'status'=>$x->status,'checkpoint'=>$x->checkpoint,'failure_code'=>$x->failureCode,'requested_at'=>$this->date($x->requestedAt),'completed_at'=>$this->date($x->completedAt)];}
    /** @return array<string,mixed> */
    private function hold(RetentionHoldRecord$x):array{return['id'=>$x->retentionHoldId,'invitation_id'=>$x->invitationId,'policy_version'=>$x->policyVersion,'reason'=>$x->reason,'status'=>$x->status,'placed_by_user_id'=>$x->placedByUserId,'released_by_user_id'=>$x->releasedByUserId,'placed_at'=>$this->date($x->placedAt),'released_at'=>$this->date($x->releasedAt)];}
    /** @return array<string,string> */private function headers(RequestId$r):array{return['X-Request-ID'=>$r->value,'Cache-Control'=>'private, no-store, max-age=0','Pragma'=>'no-cache'];}
    private function date(?DateTimeImmutable$d):?string{return$d?->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d\TH:i:s\Z');}
}
