<?php
namespace EventFlow\Infrastructure\Job;
use EventFlow\Application\Job\{JobException,JobExecutionContext,JobExecutionException,JobHandler};use EventFlow\Application\Provider\{ProviderException,ProviderService};
final readonly class MessageDeliveryJobHandler implements JobHandler
{
    public function __construct(private ProviderService $providers,private string $type='message.delivery.send'){}
    public function jobType():string{return$this->type;}public function payloadVersion():int{return 1;}
    public function validatePayload(array $payload):void{$keys=array_keys($payload);sort($keys,SORT_STRING);if($keys!==['message_id','provider']||!is_int($payload['message_id'])||$payload['message_id']<1||!in_array($payload['provider'],['brevo','twilio'],true))throw new JobException('message_delivery_payload_invalid');}
    public function handle(JobExecutionContext $context):void{$this->validatePayload($context->payload);if($context->eventScope===null)throw new JobExecutionException('message_delivery_scope_required',false);try{$this->providers->dispatch($context->eventScope,$context->payload['message_id'],$context->payload['provider']);}catch(ProviderException $e){throw new JobExecutionException($e->safeCode,in_array($e->safeCode,['provider_circuit_open','provider_transport_unknown'],true),$e);}}
}
