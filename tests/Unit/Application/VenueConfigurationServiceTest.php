<?php

namespace EventFlow\Tests\Unit\Application;

use DateTimeImmutable;
use EventFlow\Application\Audit\{AuditAction, AuditCanonicalizer, AuditPayloadRedactor, AuditRecord, AuditRepository, AuditService};
use EventFlow\Application\Authorization\{AuthorizationService, EventRole, GlobalRecoveryAuthority, MembershipReader, MembershipSnapshot, PrincipalContext, RoleCapabilityPolicy};
use EventFlow\Application\Clock\Clock;
use EventFlow\Application\EventConfiguration\{EventConfigurationAttributes, EventConfigurationPatch, EventConfigurationRecord, EventConfigurationRepository, EventConfigurationService};
use EventFlow\Application\Idempotency\{CanonicalRequestHasher, IdempotencyClaimResult, IdempotencyClaimState, IdempotencyRecord, IdempotencyRepository, IdempotencyRequest, IdempotencyResultReference, IdempotencyService};
use EventFlow\Application\Persistence\EventScope;
use EventFlow\Application\Security\SecureRandom;
use EventFlow\Application\Transaction\{TransactionManager, TransactionOptions};
use EventFlow\Application\Venue\{VenueAttributes, VenueAuthority, VenuePage, VenuePatch, VenueRecord, VenueRepository, VenueService};
use PHPUnit\Framework\TestCase;

final class VenueConfigurationServiceTest extends TestCase
{
    public function testVenueOperationsRequireDedicatedGlobalAuthorityAndAreBounded(): void
    {
        [$service,$repository] = $this->venue(false);
        $this->expectException(\EventFlow\Application\Venue\VenueException::class);
        try { $service->list(PrincipalContext::wordpressUser(7)); }
        finally { self::assertSame(0,$repository->lists); }
    }

    public function testVenueCreateAndRevisionUpdateAreIdempotentAndAudited(): void
    {
        [$service,$repository,$audit] = $this->venue(true);
        $principal=PrincipalContext::wordpressUser(7);
        $attributes=new VenueAttributes(['name'=>'Hall','country_code'=>'CA','default_capacity'=>200]);
        $created=$service->create($principal,$attributes,'venue-create-001');
        $updated=$service->update($principal,81,new VenuePatch(['city'=>'Calgary'],1),'venue-update-001');
        $replay=$service->update($principal,81,new VenuePatch(['city'=>'Calgary'],1),'venue-update-001');

        self::assertSame(81,$created->response->venueId);
        self::assertSame(2,$updated->response->revision);
        self::assertTrue($replay->replayed);
        self::assertSame(1,$repository->updates);
        self::assertSame([AuditAction::VENUE_CREATED,AuditAction::VENUE_UPDATED],$audit->actions);
    }

    public function testVenueStaleRevisionFailsBeforeWrite(): void
    {
        [$service,$repository]=$this->venue(true);$repository->record=new VenueRecord(81,new VenueAttributes(['name'=>'Hall']),4);
        try{$service->update(PrincipalContext::wordpressUser(7),81,new VenuePatch(['city'=>'Calgary'],3),'venue-update-002');self::fail('Expected stale revision.');}
        catch(\EventFlow\Application\Venue\VenueException $failure){self::assertSame('resource_modified',$failure->safeCode);}
        self::assertSame(0,$repository->updates);
    }

    public function testConfigurationReadAndUpdateUseEventCapabilitiesRevisionAndAudit(): void
    {
        [$service,$repository,$audit]=$this->configuration();$scope=new EventScope(51);$principal=PrincipalContext::wordpressUser(7);
        self::assertSame(3,$service->read($principal,$scope)->revision);
        $outcome=$service->update($principal,$scope,new EventConfigurationPatch(['allow_guest_edits'=>true,'confirmation_closes_at'=>new DateTimeImmutable('2026-09-01T18:00:00Z')],3),'config-update-001');
        self::assertSame(4,$outcome->response->revision);
        self::assertTrue($outcome->response->attributes->get('allow_guest_edits'));
        self::assertSame(1,$repository->updates);
        self::assertSame([AuditAction::EVENT_CONFIGURATION_UPDATED],$audit->actions);
    }

    public function testConfigurationMergedWindowInvariantFailsBeforeWrite(): void
    {
        [$service,$repository]=$this->configuration();
        $repository->record=new EventConfigurationRecord(new EventScope(51),new EventConfigurationAttributes(['confirmation_opens_at'=>new DateTimeImmutable('2026-09-02T18:00:00Z')]),3);
        try{$service->update(PrincipalContext::wordpressUser(7),new EventScope(51),new EventConfigurationPatch(['confirmation_closes_at'=>new DateTimeImmutable('2026-09-01T18:00:00Z')],3),'config-update-002');self::fail('Expected merged validation failure.');}
        catch(\EventFlow\Application\EventConfiguration\EventConfigurationException $failure){self::assertSame('validation_failed',$failure->safeCode);}
        self::assertSame(0,$repository->updates);
    }

    private function venue(bool $allowed): array
    {
        $clock=new VCClock();$transactions=new VCTransactions();$repository=new VCVenueRepository();$auditRepository=new VCAuditRepository();
        $service=new VenueService($repository,new VCVenueAuthority($allowed),new IdempotencyService(new VCIdempotencyRepository(),$transactions,$clock,new VCRandom(),new CanonicalRequestHasher()),new AuditService($auditRepository,$transactions,$clock,new AuditPayloadRedactor(),new AuditCanonicalizer()),$clock);
        return [$service,$repository,$auditRepository];
    }

    private function configuration(): array
    {
        $clock=new VCClock();$transactions=new VCTransactions();$repository=new VCConfigurationRepository();$auditRepository=new VCAuditRepository();
        $authorization=new AuthorizationService(new VCMembershipReader(),new RoleCapabilityPolicy(),$clock,new VCNoRecovery());
        $service=new EventConfigurationService($repository,$authorization,new IdempotencyService(new VCIdempotencyRepository(),$transactions,$clock,new VCRandom(),new CanonicalRequestHasher()),new AuditService($auditRepository,$transactions,$clock,new AuditPayloadRedactor(),new AuditCanonicalizer()),$clock);
        return [$service,$repository,$auditRepository];
    }
}

final class VCVenueAuthority implements VenueAuthority { public function __construct(private bool $allowed){} public function canManageVenues(int $userId):bool{return $this->allowed&&$userId===7;} }
final class VCVenueRepository implements VenueRepository
{
    public VenueRecord $record;public int $updates=0;public int $lists=0;
    public function __construct(){ $this->record=new VenueRecord(81,new VenueAttributes(['name'=>'Hall']),1); }
    public function list(int $limit,?int $afterVenueId):VenuePage{$this->lists++;return new VenuePage([$this->record],null);}
    public function find(int $venueId):?VenueRecord{return $venueId===81?$this->record:null;}
    public function lock(int $venueId):?VenueRecord{return $this->find($venueId);}
    public function create(VenueAttributes $attributes,int $actorUserId,DateTimeImmutable $now):VenueRecord{return $this->record=new VenueRecord(81,$attributes,1);}
    public function update(VenueRecord $current,VenueAttributes $replacement,int $actorUserId,DateTimeImmutable $now):VenueRecord{$this->updates++;return $this->record=new VenueRecord($current->venueId,$replacement,$current->revision+1);}
}
final class VCConfigurationRepository implements EventConfigurationRepository
{
    public EventConfigurationRecord $record;public int $updates=0;
    public function __construct(){ $this->record=new EventConfigurationRecord(new EventScope(51),new EventConfigurationAttributes(),3); }
    public function find(EventScope $scope):?EventConfigurationRecord{return $scope->eventId===51?$this->record:null;}
    public function lock(EventScope $scope):?EventConfigurationRecord{return $this->find($scope);}
    public function update(EventConfigurationRecord $current,EventConfigurationAttributes $replacement,int $actorUserId,DateTimeImmutable $now):EventConfigurationRecord{$this->updates++;return $this->record=new EventConfigurationRecord($current->eventScope,$replacement,$current->revision+1);}
}
final class VCClock implements Clock{public function now():DateTimeImmutable{return new DateTimeImmutable('2026-08-18T18:00:00Z');}}
final class VCRandom implements SecureRandom{public function hex(int $bytes):string{return str_repeat('ab',$bytes);}}
final class VCTransactions implements TransactionManager{private int $depth=0;public function transactional(callable $operation,?TransactionOptions $options=null):mixed{$this->depth++;try{return $operation();}finally{$this->depth--;}}public function isActive():bool{return $this->depth>0;}public function assertNotActive():void{if($this->isActive())throw new \RuntimeException('transaction_active');}}
final class VCIdempotencyRepository implements IdempotencyRepository
{
    /** @var array<string,IdempotencyResultReference> */private array $completed=[];
    private ?string $pendingFingerprint=null;
    public function claim(IdempotencyRequest $request,string $leaseToken,DateTimeImmutable $now,DateTimeImmutable $leaseExpiresAt,DateTimeImmutable $recordExpiresAt):IdempotencyClaimResult{$reference=$this->completed[$request->requestFingerprint]??null;$this->pendingFingerprint=$request->requestFingerprint;return $reference===null?new IdempotencyClaimResult(IdempotencyClaimState::ACQUIRED,new IdempotencyRecord(1,$request->requestFingerprint,'in_progress',$leaseExpiresAt,null,false)):new IdempotencyClaimResult(IdempotencyClaimState::REPLAY,new IdempotencyRecord(1,$request->requestFingerprint,'completed',null,$reference,false));}
    public function complete(int $recordId,string $leaseToken,IdempotencyResultReference $reference,bool $sensitiveResult,DateTimeImmutable $completedAt):void{if($this->pendingFingerprint!==null)$this->completed[$this->pendingFingerprint]=$reference;}
    public function fail(int $recordId,string $leaseToken,DateTimeImmutable $failedAt):void{}
}
final class VCAuditRepository implements AuditRepository{/** @var list<AuditAction> */public array $actions=[];public function lockChainHead(?EventScope $eventScope):?string{return null;}public function append(AuditRecord $record):int{$this->actions[]=$record->action;return count($this->actions);}}
final class VCMembershipReader implements MembershipReader{public function findCurrent(EventScope $eventScope,int $userId):?MembershipSnapshot{return $userId===7?new MembershipSnapshot(1,$eventScope,7,EventRole::OWNER,true,null):null;}}
final class VCNoRecovery implements GlobalRecoveryAuthority{public function canRecoverPrimaryOwnership(int $userId):bool{return false;}}
