<?php

namespace EventFlow\Presentation\Api;

use DateTimeImmutable;
use DateTimeZone;
use EventFlow\Application\Communication\{MessagePage,MessageRecord,MessageRetryResult,MessageTestResult};
use EventFlow\Application\Error\RequestId;
use EventFlow\Application\Idempotency\IdempotencyOutcome;

final readonly class MessageAccessPresenter
{
    public function page(MessagePage$page,RequestId$requestId):JsonApiResponse{return new JsonApiResponse(200,['data'=>array_map($this->summary(...),$page->messages),'meta'=>['next_after'=>$page->nextAfterMessageId],'request_id'=>$requestId->value],$this->headers($requestId));}
    public function resource(MessageRecord$message,RequestId$requestId):JsonApiResponse{return new JsonApiResponse(200,['data'=>$this->detail($message),'request_id'=>$requestId->value],$this->headers($requestId,$message->revision));}
    public function outcome(IdempotencyOutcome$outcome,int$eventId,RequestId$requestId):JsonApiResponse
    {
        $result=$outcome->response instanceof MessageRetryResult||$outcome->response instanceof MessageTestResult?$outcome->response:null;
        $data=$result===null?['type'=>$outcome->reference->entityType,'id'=>$outcome->reference->entityId]:[...$this->detail($result->message),$result instanceof MessageTestResult?'test_job_id':'retry_job_id'=>$result->jobId];
        $headers=$this->headers($requestId,$result?->message->revision);$headers['Location']='/wp-json/eventflow/v1/events/'.$eventId.'/messages/'.$outcome->reference->entityId;
        return new JsonApiResponse($outcome->reference->responseStatusCode,['data'=>$data,'meta'=>['replayed'=>$outcome->replayed],'request_id'=>$requestId->value],$headers);
    }
    /** @return array<string,mixed> */
    private function summary(MessageRecord$m):array{return['id'=>$m->messageId,'campaign_id'=>$m->campaignId,'invitation_id'=>$m->invitationId,'attendee_id'=>$m->attendeeId,'channel'=>$m->channel->value,'recipient_name'=>$m->recipientName,'recipient_address'=>$m->recipientAddress,'subject'=>$m->subject,'status'=>$m->status,'revision'=>$m->revision,'provider'=>$m->provider,'provider_message_id'=>$m->providerMessageId,'attempt_count'=>$m->attemptCount,'queued_at'=>$this->date($m->queuedAt),'processing_at'=>$this->date($m->processingAt),'provider_accepted_at'=>$this->date($m->providerAcceptedAt),'delivered_at'=>$this->date($m->deliveredAt),'failed_at'=>$this->date($m->failedAt),'bounced_at'=>$this->date($m->bouncedAt),'cancelled_at'=>$this->date($m->cancelledAt),'created_at'=>$this->date($m->createdAt),'updated_at'=>$this->date($m->updatedAt)];}
    /** @return array<string,mixed> */
    private function detail(MessageRecord$m):array{return[...$this->summary($m),'content'=>$m->content,'plain_text'=>$m->plainText];}
    /** @return array<string,string> */private function headers(RequestId$r,?int$revision=null):array{$headers=['X-Request-ID'=>$r->value,'Cache-Control'=>'no-store, max-age=0','Pragma'=>'no-cache'];if($revision!==null)$headers['ETag']='"'.$revision.'"';return$headers;}
    private function date(?DateTimeImmutable$d):?string{return$d?->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d\TH:i:s\Z');}
}
