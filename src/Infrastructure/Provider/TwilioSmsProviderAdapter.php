<?php
namespace EventFlow\Infrastructure\Provider;
use DateTimeImmutable;use EventFlow\Application\Persistence\EventScope;use EventFlow\Application\Provider\{NormalizedProviderWebhook,ProviderAdapter,ProviderCapabilities,ProviderDispatchMessage,ProviderException,ProviderOutcome,ProviderSendResult};
final readonly class TwilioSmsProviderAdapter implements ProviderAdapter
{
    public function __construct(private ProviderHttpClient $http,private string $accountSid,private string $authToken,private string $messagingServiceSid,private string $webhookUrl){}
    public function name():string{return'twilio';}public function capabilities():ProviderCapabilities{return new ProviderCapabilities(false,false,true);}
    public function send(ProviderDispatchMessage $m):ProviderSendResult
    {
        if($m->channel!=='sms')throw new ProviderException('provider_channel_invalid');$callback=$this->webhookUrl.'?eventflow_event_id='.$m->eventScope->eventId.'&eventflow_request_id='.$m->requestKey;
        $body=http_build_query(['To'=>$m->address,'MessagingServiceSid'=>$this->messagingServiceSid,'Body'=>$m->content,'StatusCallback'=>$callback],'','&',PHP_QUERY_RFC3986);
        $r=$this->http->post('https://api.twilio.com/2010-04-01/Accounts/'.rawurlencode($this->accountSid).'/Messages.json',['authorization'=>'Basic '.base64_encode($this->accountSid.':'.$this->authToken),'content-type'=>'application/x-www-form-urlencoded'],$body);$p=json_decode($r->body,true);$sid=is_array($p)&&is_string($p['sid']??null)?$p['sid']:null;
        if($r->statusCode>=200&&$r->statusCode<300&&$sid!==null)return new ProviderSendResult(ProviderOutcome::ACCEPTED,$sid,$m->requestKey,(string)$r->statusCode);
        return new ProviderSendResult($r->statusCode>=400&&$r->statusCode<500?ProviderOutcome::DEFINITIVE_FAILURE:ProviderOutcome::AMBIGUOUS,providerRequestId:$m->requestKey,responseCode:(string)$r->statusCode,errorCode:'provider_send_rejected');
    }
    public function authenticateAndNormalize(array $headers,string $rawBody,array $context=[]):NormalizedProviderWebhook
    {
        parse_str($rawBody,$p);if(!is_array($p)||count($p)>64)throw new ProviderException('provider_webhook_invalid');foreach($p as $v)if(!is_string($v))throw new ProviderException('provider_webhook_invalid');
        $eventId=filter_var($context['eventflow_event_id']??null,FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]);$request=$context['eventflow_request_id']??null;if($eventId===false||!is_string($request)||!preg_match('/^[a-f0-9]{64}$/',$request))throw new ProviderException('provider_webhook_invalid');
        $url=$this->webhookUrl.'?'.http_build_query($context,'','&',PHP_QUERY_RFC3986);$data=$url;$sorted=$p;ksort($sorted,SORT_STRING);foreach($sorted as $key=>$value)$data.=$key.$value;$expected=base64_encode(hash_hmac('sha1',$data,$this->authToken,true));$provided=$headers['x-twilio-signature']??'';if(!is_string($provided)||!hash_equals($expected,$provided))throw new ProviderException('provider_webhook_unauthorized');
        $status=strtolower($p['MessageStatus']??$p['SmsStatus']??'unknown');$normalized=match($status){'delivered'=>'delivered','undelivered'=>'bounced','failed'=>'failed',default=>'accepted'};$sid=$p['MessageSid']??$p['SmsSid']??null;$native=$p['EventSid']??null;$occurred=null;try{if(isset($p['Timestamp']))$occurred=new DateTimeImmutable($p['Timestamp']);}catch(\Throwable){}
        return new NormalizedProviderWebhook(new EventScope((int)$eventId),'twilio',$native,$sid,$request,$status,$normalized,$status,isset($p['ErrorCode'])?substr($p['ErrorCode'],0,64):null,null,$occurred,hash('sha256',$rawBody));
    }
}
