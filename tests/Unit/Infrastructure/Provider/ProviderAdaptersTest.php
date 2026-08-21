<?php
namespace EventFlow\Tests\Unit\Infrastructure\Provider;
use EventFlow\Application\Persistence\EventScope;use EventFlow\Application\Provider\{ProviderDispatchMessage,ProviderException,ProviderOutcome};use EventFlow\Infrastructure\Provider\{BrevoEmailProviderAdapter,ProviderHttpClient,ProviderHttpResponse,TwilioSmsProviderAdapter};use PHPUnit\Framework\TestCase;
final class ProviderAdaptersTest extends TestCase
{
    public function testBrevoSendUsesExternalSecretAndStableCorrelationHeaders():void
    {
        $http=new AdapterMemoryHttp(new ProviderHttpResponse(201,'{"messageId":"brevo-1"}'));$adapter=new BrevoEmailProviderAdapter($http,'secret','sender@example.test','EventFlow','hook-secret');$key=str_repeat('a',64);
        $result=$adapter->send(new ProviderDispatchMessage(new EventScope(8),41,'email','guest@example.test','Subject','<p>Hello</p>',1,$key));
        self::assertSame(ProviderOutcome::ACCEPTED,$result->outcome);self::assertSame('brevo-1',$result->providerMessageId);self::assertSame('secret',$http->headers['api-key']);self::assertStringContainsString('eventflow_event_id:8|eventflow_request_id:'.$key,$http->body);
    }
    public function testBrevoWebhookFailsClosedThenNormalizesAuthenticatedDelivery():void
    {
        $adapter=new BrevoEmailProviderAdapter(new AdapterMemoryHttp(new ProviderHttpResponse(500,'')),'api','sender@example.test','EventFlow','hook-secret');$body='{"id":"evt-1","message-id":"brevo-1","event":"delivered","X-Mailin-custom":"eventflow_event_id:8|eventflow_request_id:'.str_repeat('b',64).'"}';
        try{$adapter->authenticateAndNormalize([], $body);self::fail('Expected authentication failure.');}catch(ProviderException $e){self::assertSame('provider_webhook_unauthorized',$e->safeCode);}
        $event=$adapter->authenticateAndNormalize(['x-eventflow-webhook-token'=>'hook-secret'],$body);self::assertSame(8,$event->eventScope->eventId);self::assertSame('delivered',$event->normalizedType);self::assertSame('brevo-1',$event->providerMessageId);
    }
    public function testTwilioValidatesExactCallbackSignatureAndNormalizesDelivery():void
    {
        $token='auth-token';$url='https://example.test/wp-json/eventflow/v1/webhooks/twilio';$adapter=new TwilioSmsProviderAdapter(new AdapterMemoryHttp(new ProviderHttpResponse(201,'{"sid":"SM1"}')),'AC1',$token,'MG1',$url);$context=['eventflow_event_id'=>'9','eventflow_request_id'=>str_repeat('c',64)];$body='MessageSid=SM1&MessageStatus=delivered';$params=['MessageSid'=>'SM1','MessageStatus'=>'delivered'];ksort($params);$signed=$url.'?'.http_build_query($context,'','&',PHP_QUERY_RFC3986);foreach($params as $k=>$v)$signed.=$k.$v;$signature=base64_encode(hash_hmac('sha1',$signed,$token,true));
        $event=$adapter->authenticateAndNormalize(['x-twilio-signature'=>$signature],$body,$context);self::assertSame(9,$event->eventScope->eventId);self::assertSame('delivered',$event->normalizedType);self::assertSame('SM1',$event->providerMessageId);
        $this->expectException(ProviderException::class);$adapter->authenticateAndNormalize(['x-twilio-signature'=>'invalid'],$body,$context);
    }
}
final class AdapterMemoryHttp implements ProviderHttpClient
{
    public string $url='';public array $headers=[];public string $body='';public function __construct(private ProviderHttpResponse $response){}
    public function post(string $url,array $headers,string $body):ProviderHttpResponse{$this->url=$url;$this->headers=$headers;$this->body=$body;return$this->response;}
}
