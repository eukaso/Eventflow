<?php
namespace EventFlow\Infrastructure\Provider;
use DateTimeImmutable;use EventFlow\Application\Persistence\EventScope;use EventFlow\Application\Provider\{NormalizedProviderWebhook,ProviderAdapter,ProviderCapabilities,ProviderDispatchMessage,ProviderException,ProviderOutcome,ProviderSendResult};
final readonly class BrevoEmailProviderAdapter implements ProviderAdapter
{
    public function __construct(private ProviderHttpClient $http,private string $apiKey,private string $senderEmail,private string $senderName,private string $webhookToken){}
    public function name():string{return'brevo';}
    public function capabilities():ProviderCapabilities{return new ProviderCapabilities(true,true,true);}
    public function send(ProviderDispatchMessage $m):ProviderSendResult
    {
        if($m->channel!=='email')throw new ProviderException('provider_channel_invalid');
        $custom='eventflow_event_id:'.$m->eventScope->eventId.'|eventflow_request_id:'.$m->requestKey;
        $payload=['sender'=>['email'=>$this->senderEmail,'name'=>$this->senderName],'to'=>[['email'=>$m->address]],'subject'=>$m->subject??'','htmlContent'=>$m->content,'headers'=>['X-Mailin-custom'=>$custom,'Idempotency-Key'=>$m->requestKey]];
        $body=json_encode($payload,JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR);$r=$this->http->post('https://api.brevo.com/v3/smtp/email',['accept'=>'application/json','api-key'=>$this->apiKey,'content-type'=>'application/json'],$body);
        $decoded=json_decode($r->body,true);$messageId=is_array($decoded)&&is_string($decoded['messageId']??null)?$decoded['messageId']:null;
        if($r->statusCode>=200&&$r->statusCode<300&&$messageId!==null)return new ProviderSendResult(ProviderOutcome::ACCEPTED,$messageId,$m->requestKey,(string)$r->statusCode);
        return new ProviderSendResult($r->statusCode>=400&&$r->statusCode<500?ProviderOutcome::DEFINITIVE_FAILURE:ProviderOutcome::AMBIGUOUS,providerRequestId:$m->requestKey,responseCode:(string)$r->statusCode,errorCode:'provider_send_rejected');
    }
    public function authenticateAndNormalize(array $headers,string $rawBody,array $context=[]):NormalizedProviderWebhook
    {
        $token=$headers['x-eventflow-webhook-token']??'';if(!is_string($token)||!hash_equals($this->webhookToken,$token))throw new ProviderException('provider_webhook_unauthorized');
        try{$p=json_decode($rawBody,true,32,JSON_THROW_ON_ERROR);}catch(\Throwable){throw new ProviderException('provider_webhook_invalid');}if(!is_array($p))throw new ProviderException('provider_webhook_invalid');
        $custom=$p['X-Mailin-custom']??$p['x-mailin-custom']??'';if(is_array($custom))$custom=implode('|',array_map('strval',$custom));preg_match('/(?:^|\|)eventflow_event_id:(\d+)(?:\||$)/',(string)$custom,$em);preg_match('/(?:^|\|)eventflow_request_id:([a-f0-9]{64})(?:\||$)/',(string)$custom,$rm);
        $eventId=(int)($em[1]??0);if($eventId<1)throw new ProviderException('provider_webhook_invalid');$native=$this->text($p['id']??null);$messageId=$this->text($p['message-id']??$p['messageId']??null);$type=strtolower($this->text($p['event']??null)??'unknown');$normalized=match($type){'delivered'=>'delivered','hard_bounce','soft_bounce','blocked','invalid_email'=>'bounced','error','deferred'=>'failed',default=>'accepted'};
        $occurred=null;$stamp=$p['date']??$p['ts_event']??null;try{if(is_string($stamp)&&$stamp!=='')$occurred=new DateTimeImmutable($stamp);elseif(is_int($stamp)||is_float($stamp))$occurred=new DateTimeImmutable('@'.(int)$stamp);}catch(\Throwable){}
        return new NormalizedProviderWebhook(new EventScope($eventId),'brevo',$native,$messageId,$rm[1]??null,$type,$normalized,$type,null,null,$occurred,hash('sha256',$rawBody));
    }
    private function text(mixed $v):?string{return is_string($v)&&$v!==''&&strlen($v)<=255?$v:null;}
}
