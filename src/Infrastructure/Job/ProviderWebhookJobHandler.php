<?php
namespace EventFlow\Infrastructure\Job;
use EventFlow\Application\Job\{JobExecutionContext,JobExecutionException,JobHandler};use EventFlow\Application\Provider\{ProviderException,ProviderService};
final readonly class ProviderWebhookJobHandler implements JobHandler
{
    public function __construct(private ProviderService $providers){}
    public function jobType():string{return'provider.webhook.process';}public function payloadVersion():int{return 1;}
    public function validatePayload(array $payload):void{$keys=['event_id','provider','native_event_key','provider_message_id','provider_request_id','provider_event_type','normalized_type','provider_status','reason_code','reason_message','occurred_at','payload_hash','dedupe_version'];if(array_keys($payload)!==$keys||!is_int($payload['event_id'])||$payload['event_id']<1||!is_string($payload['provider'])||!is_string($payload['provider_event_type'])||!is_string($payload['normalized_type'])||!is_string($payload['payload_hash'])||!preg_match('/^[a-f0-9]{64}$/',$payload['payload_hash'])||$payload['dedupe_version']!=='provider-dedupe-v1')throw new JobExecutionException('provider_webhook_job_invalid',false);}
    public function handle(JobExecutionContext $context):void{$this->validatePayload($context->payload);try{$this->providers->process(new \EventFlow\Application\Job\JobRecord($context->jobId,$context->eventScope,$this->jobType(),1,$context->payload,[],\EventFlow\Application\Job\JobStatus::RUNNING,20,$context->attemptNumber,20));}catch(ProviderException $e){throw new JobExecutionException($e->safeCode,$e->safeCode==='provider_event_unmatched',$e);}}
}
