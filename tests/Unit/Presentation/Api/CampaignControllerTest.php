<?php

namespace EventFlow\Tests\Unit\Presentation\Api;

use EventFlow\Application\Authorization\PrincipalContext;
use EventFlow\Application\Communication\{AudienceMode, CampaignCommands, CampaignPurpose, CampaignQueueResult, CampaignRecord, CommunicationChannel, MessageRecord};
use EventFlow\Application\Error\RequestIdFactory;
use EventFlow\Application\Idempotency\{IdempotencyOutcome, IdempotencyResultReference};
use EventFlow\Application\Persistence\EventScope;
use EventFlow\Application\Security\SecureRandom;
use EventFlow\Presentation\Api\{AuthenticatedPrincipalResolver, AuthenticatedRequestContextFactory, CampaignController, CampaignPresenter, CampaignRequestMapper, CampaignRouteRegistrar, RequestInputException, RestRequest, RestRouteRegistry};
use PHPUnit\Framework\TestCase;

final class CampaignControllerTest extends TestCase
{
    public function testRegistrarExposesOnlyAuthoritativeCampaignCommands(): void
    {
        $routes = new CampaignMemoryRoutes();
        (new CampaignRouteRegistrar($this->controller(new CampaignPort())))->register($routes);
        self::assertSame([
            'POST eventflow/v1/events/(?P<event_id>\d+)/campaigns',
            'POST eventflow/v1/events/(?P<event_id>\d+)/campaigns/(?P<campaign_id>\d+)/queue',
        ], $routes->registered);
    }

    public function testCreateMapsExplicitAudienceAndReturnsDraft(): void
    {
        $port = new CampaignPort();
        $response = $this->controller($port)->create(new RestRequest(
            ['Idempotency-Key' => 'campaign-create-001'],
            [
                'template_id' => 41, 'name' => 'Final reminder', 'channel' => 'email',
                'purpose' => 'reminder', 'audience_mode' => 'snapshot',
                'audience' => ['filter' => 'confirmed_attendees', 'invitation_ids' => [12, 14]],
            ],
            ['event_id' => '9'],
        ));
        self::assertSame(41, $port->creation[0]);
        self::assertSame(CommunicationChannel::EMAIL, $port->creation[2]);
        self::assertSame(CampaignPurpose::REMINDER, $port->creation[3]);
        self::assertSame(AudienceMode::SNAPSHOT, $port->creation[4]);
        self::assertSame(['filter'=>'confirmed_attendees','invitation_ids'=>[12,14]], $port->creation[5]);
        self::assertSame('campaign-create-001', $port->creation[6]);
        self::assertSame(201, $response->status());
        self::assertSame('snapshot', $response->body()['data']['audience_mode']);
        self::assertSame('/wp-json/eventflow/v1/events/9/campaigns/51', $response->headers()['Location']);
    }

    public function testQueueReturnsOnlyFrozenOperationalIdentifiers(): void
    {
        $port = new CampaignPort();
        $response = $this->controller($port)->queue(new RestRequest(
            ['Idempotency-Key' => 'campaign-queue-001'], [], ['event_id' => '9', 'campaign_id' => '51'],
        ));
        self::assertSame([51, 'campaign-queue-001'], $port->queueInput);
        self::assertSame(202, $response->status());
        self::assertSame(2, $response->body()['data']['recipient_count']);
        self::assertSame([61, 62], $response->body()['data']['message_ids']);
        $encoded = json_encode($response->body(), JSON_THROW_ON_ERROR);
        self::assertStringNotContainsString('guest@example.test', $encoded);
        self::assertStringNotContainsString('Rendered secret', $encoded);
    }

    public function testQueueReplayUsesStableCampaignReference(): void
    {
        $port = new CampaignPort(); $port->replay = true;
        $response = $this->controller($port)->queue(new RestRequest(
            ['Idempotency-Key' => 'campaign-queue-002'], [], ['event_id' => '9', 'campaign_id' => '51'],
        ));
        self::assertTrue($response->body()['meta']['replayed']);
        self::assertSame(['type'=>'campaign','id'=>51], $response->body()['data']);
    }

    public function testWeakTypesUnknownFieldsEnumsAndRoutesFailBeforePort(): void
    {
        $port = new CampaignPort();
        $base = ['template_id'=>41,'name'=>'Reminder','channel'=>'email','purpose'=>'reminder','audience_mode'=>'dynamic','audience'=>[]];
        foreach ([
            fn () => $this->controller($port)->create(new RestRequest(['Idempotency-Key'=>'campaign-bad-001'], [...$base,'template_id'=>'41'], ['event_id'=>'9'])),
            fn () => $this->controller($port)->create(new RestRequest(['Idempotency-Key'=>'campaign-bad-002'], [...$base,'purpose'=>'marketing'], ['event_id'=>'9'])),
            fn () => $this->controller($port)->create(new RestRequest(['Idempotency-Key'=>'campaign-bad-003'], [...$base,'audience'=>['invitation_ids'=>'12']], ['event_id'=>'9'])),
            fn () => $this->controller($port)->create(new RestRequest(['Idempotency-Key'=>'campaign-bad-004'], [...$base,'audience'=>['admin'=>true]], ['event_id'=>'9'])),
            fn () => $this->controller($port)->create(new RestRequest(['Idempotency-Key'=>'campaign-bad-005'], [...$base,'force'=>true], ['event_id'=>'9'])),
            fn () => $this->controller($port)->queue(new RestRequest(['Idempotency-Key'=>'campaign-bad-006'], ['force'=>true], ['event_id'=>'9','campaign_id'=>'51'])),
            fn () => $this->controller($port)->queue(new RestRequest(['Idempotency-Key'=>'campaign-bad-007'], [], ['event_id'=>'9','campaign_id'=>'../51'])),
        ] as $operation) {
            try { $operation(); self::fail('Expected controlled input failure.'); }
            catch (RequestInputException $failure) { self::assertContains($failure->safeCode, ['validation_failed', 'resource_not_found']); }
        }
        self::assertSame(0, $port->calls);
    }

    private function controller(CampaignPort $port): CampaignController
    {
        return new CampaignController(
            $port,
            new AuthenticatedRequestContextFactory(new CampaignPrincipalResolver(), new RequestIdFactory(new CampaignRandom())),
            new CampaignRequestMapper(),
            new CampaignPresenter(),
        );
    }
}

final class CampaignMemoryRoutes implements RestRouteRegistry
{
    public array $registered=[];
    public function registerPublicGet(string $namespace,string $route,callable $handler):void{}
    public function registerPublicPost(string $namespace,string $route,callable $handler):void{}
    public function registerPublicPut(string $namespace,string $route,callable $handler):void{}
    public function registerAuthenticatedGet(string $namespace,string $route,callable $handler):void{}
    public function registerAuthenticatedPost(string $namespace,string $route,callable $handler):void{$this->registered[]='POST '.$namespace.$route;}
    public function registerAuthenticatedPatch(string $namespace,string $route,callable $handler):void{}
}

final readonly class CampaignPrincipalResolver implements AuthenticatedPrincipalResolver
{
    public function resolve(RestRequest $request):PrincipalContext{return PrincipalContext::wordpressUser(7);}
}

final readonly class CampaignRandom implements SecureRandom
{
    public function hex(int $bytes):string{return str_repeat('9',$bytes*2);}
}

final class CampaignPort implements CampaignCommands
{
    public int $calls=0;
    public array $creation=[];
    public array $queueInput=[];
    public bool $replay=false;

    public function createCampaign(PrincipalContext $principal,EventScope $scope,int $templateId,string $name,CommunicationChannel $channel,CampaignPurpose $purpose,AudienceMode $mode,array $audience,string $idempotencyKey):IdempotencyOutcome
    {
        $this->calls++;$this->creation=[$templateId,$name,$channel,$purpose,$mode,$audience,$idempotencyKey];
        $record=new CampaignRecord(51,$templateId,$name,$channel,$purpose,$mode,['mode'=>$mode->value,...$audience],'draft');
        return new IdempotencyOutcome(false,new IdempotencyResultReference('campaign',51,201),$record);
    }

    public function queue(PrincipalContext $principal,EventScope $scope,int $campaignId,string $idempotencyKey):IdempotencyOutcome
    {
        $this->calls++;$this->queueInput=[$campaignId,$idempotencyKey];
        $messages=[
            new MessageRecord(61,$campaignId,str_repeat('a',64),'guest@example.test','Secret','Rendered secret'),
            new MessageRecord(62,$campaignId,str_repeat('b',64),'other@example.test','Secret','Rendered secret'),
        ];
        return new IdempotencyOutcome($this->replay,new IdempotencyResultReference('campaign',$campaignId,202),$this->replay?null:new CampaignQueueResult($campaignId,2,$messages));
    }
}
