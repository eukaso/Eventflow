<?php

namespace EventFlow\Tests\Unit\Application\Seating;

use DateTimeImmutable;
use DateTimeZone;
use EventFlow\Application\Audit\{AuditCanonicalizer, AuditPayloadRedactor, AuditRecord, AuditRepository, AuditService};
use EventFlow\Application\Authorization\{AuthorizationService, EventRole, GlobalRecoveryAuthority, MembershipReader, MembershipSnapshot, PrincipalContext, RoleCapabilityPolicy};
use EventFlow\Application\Clock\Clock;
use EventFlow\Application\Idempotency\{CanonicalRequestHasher, IdempotencyClaimResult, IdempotencyClaimState, IdempotencyOutcome, IdempotencyRecord, IdempotencyRepository, IdempotencyRequest, IdempotencyResultReference, IdempotencyService};
use EventFlow\Application\Persistence\EventScope;
use EventFlow\Application\Seating\{RecommendationPlan, RecommendationStatus, RecommendedPlacement, SeatingAssignment, SeatingPlanningCommands, SeatingRecommendationRepository, SeatingRecommendationService, StoredRecommendation};
use EventFlow\Application\Security\SecureRandom;
use EventFlow\Application\Transaction\{TransactionManager, TransactionOptions};
use PHPUnit\Framework\TestCase;

final class SeatingRecommendationServiceTest extends TestCase
{
    public function testGeneratePersistsNormalizedPlanAndReturnsDurableReference(): void
    {
        $fixture = new RecommendationFixture();
        $outcome = $fixture->service->generate($fixture->principal, $fixture->scope, '  seed-1  ', 'recommend-generate-001');
        self::assertSame('seed-1', $fixture->planning->seed);
        self::assertSame(91, $outcome->reference->entityId);
        self::assertSame(201, $outcome->reference->responseStatusCode);
        self::assertInstanceOf(StoredRecommendation::class, $outcome->response);
        self::assertSame(RecommendationStatus::DRAFT, $fixture->repository->stored?->status);
        self::assertSame('group:Family', $fixture->repository->stored?->plan->placements[0]->reason);
    }

    public function testGetIsEventScopedAndApplyUsesExactPersistedPlanThenMarksApplied(): void
    {
        $fixture = new RecommendationFixture();
        $fixture->service->generate($fixture->principal, $fixture->scope, 'seed-2', 'recommend-generate-002');
        self::assertSame(91, $fixture->service->get($fixture->principal, $fixture->scope, 91)->recommendationId);
        $applied = $fixture->service->apply($fixture->principal, $fixture->scope, 91, 'recommend-apply-001');
        self::assertSame($fixture->repository->stored?->plan, $fixture->planning->appliedPlan);
        self::assertSame('recommend-apply-001', $fixture->planning->applyKey);
        self::assertSame(RecommendationStatus::APPLIED, $fixture->repository->stored?->status);
        self::assertSame(RecommendationStatus::APPLIED, $applied->response->status);
        self::assertSame(200, $applied->reference->responseStatusCode);
    }

    public function testUnknownRecommendationFailsBeforePlanningApply(): void
    {
        $fixture = new RecommendationFixture();
        try { $fixture->service->apply($fixture->principal, $fixture->scope, 404, 'recommend-apply-404'); self::fail('Expected not found.'); }
        catch (\EventFlow\Application\Seating\SeatingException $failure) { self::assertSame('resource_not_found', $failure->safeCode); }
        self::assertNull($fixture->planning->appliedPlan);
    }
}

final class RecommendationFixture
{
    public readonly EventScope $scope; public readonly PrincipalContext $principal; public readonly RecommendationPlanningPort $planning; public readonly RecommendationMemoryRepository $repository; public readonly SeatingRecommendationService $service;
    public function __construct()
    {
        $this->scope=new EventScope(44);$this->principal=PrincipalContext::wordpressUser(7);$this->planning=new RecommendationPlanningPort();$this->repository=new RecommendationMemoryRepository();
        $clock=new RecommendationClock();$transactions=new RecommendationTransactions();
        $authorization=new AuthorizationService(new RecommendationMembershipReader(),new RoleCapabilityPolicy(),$clock,new RecommendationNoRecovery());
        $idempotency=new IdempotencyService(new RecommendationIdempotencyRepository(),$transactions,$clock,new RecommendationRandom(),new CanonicalRequestHasher());
        $audit=new AuditService(new RecommendationAuditRepository(),$transactions,$clock,new AuditPayloadRedactor(),new AuditCanonicalizer());
        $this->service=new SeatingRecommendationService($this->planning,$this->repository,$authorization,$idempotency,$audit,$clock,$transactions);
    }
}

final class RecommendationPlanningPort implements SeatingPlanningCommands
{
    public ?string $seed=null; public ?RecommendationPlan $appliedPlan=null; public ?string $applyKey=null;
    public function recommend(PrincipalContext $principal,EventScope $scope,string $seed):RecommendationPlan{$this->seed=$seed;return new RecommendationPlan(str_repeat('a',64),RecommendationPlan::ALGORITHM_VERSION,$seed,[new RecommendedPlacement(7,5,51,'group:Family')],['group_split_for_capacity']);}
    public function assign(PrincipalContext $principal,EventScope $scope,int $attendeeId,int $tableId,?int $seatId,?int $expectedAssignmentId,bool $overrideRequiredGroup,?string $overrideReason,string $idempotencyKey):IdempotencyOutcome{return new IdempotencyOutcome(false,new IdempotencyResultReference('seating_assignment',1,200),new SeatingAssignment(1,$attendeeId,$tableId,$seatId,'manual'));}
    public function applyRecommendation(PrincipalContext $principal,EventScope $scope,RecommendationPlan $plan,string $idempotencyKey):IdempotencyOutcome{$this->appliedPlan=$plan;$this->applyKey=$idempotencyKey;return new IdempotencyOutcome(false,new IdempotencyResultReference('event',$scope->eventId,200),[]);}
}

final class RecommendationMemoryRepository implements SeatingRecommendationRepository
{
    public ?StoredRecommendation $stored=null;
    public function create(EventScope $scope,RecommendationPlan $plan,int $actorUserId,DateTimeImmutable $now):StoredRecommendation{return $this->stored=new StoredRecommendation(91,$scope,RecommendationStatus::DRAFT,$plan,$now);}
    public function find(EventScope $scope,int $recommendationId):?StoredRecommendation{return $this->stored?->eventScope->eventId===$scope->eventId&&$this->stored?->recommendationId===$recommendationId?$this->stored:null;}
    public function lock(EventScope $scope,int $recommendationId):?StoredRecommendation{return $this->find($scope,$recommendationId);}
    public function markApplied(StoredRecommendation $recommendation,int $actorUserId,DateTimeImmutable $now):StoredRecommendation{return $this->stored=new StoredRecommendation($recommendation->recommendationId,$recommendation->eventScope,RecommendationStatus::APPLIED,$recommendation->plan,$recommendation->createdAt,$now);}
}

final readonly class RecommendationMembershipReader implements MembershipReader { public function findCurrent(EventScope $eventScope,int $userId):?MembershipSnapshot{return new MembershipSnapshot(1,$eventScope,$userId,EventRole::OWNER,false,null);} }
final readonly class RecommendationNoRecovery implements GlobalRecoveryAuthority { public function canRecoverPrimaryOwnership(int $userId):bool{return false;} }
final readonly class RecommendationClock implements Clock { public function now():DateTimeImmutable{return new DateTimeImmutable('2026-08-19 18:00:00',new DateTimeZone('UTC'));} }
final readonly class RecommendationRandom implements SecureRandom { public function hex(int $bytes):string{return str_repeat('a',$bytes*2);} }
final class RecommendationTransactions implements TransactionManager { private int $depth=0;public function transactional(callable $operation,?TransactionOptions $options=null):mixed{$this->depth++;try{return $operation();}finally{$this->depth--;}}public function isActive():bool{return $this->depth>0;}public function assertNotActive():void{if($this->depth>0)throw new \RuntimeException('active');} }
final class RecommendationAuditRepository implements AuditRepository { private array $records=[];public function lockChainHead(?EventScope $eventScope):?string{return $this->records===[]?null:$this->records[array_key_last($this->records)]->recordHash;}public function append(AuditRecord $record):int{$this->records[]=$record;return count($this->records);} }
final class RecommendationIdempotencyRepository implements IdempotencyRepository
{
    private array $records=[];
    public function claim(IdempotencyRequest $request,string $leaseToken,DateTimeImmutable $now,DateTimeImmutable $leaseExpiresAt,DateTimeImmutable $recordExpiresAt):IdempotencyClaimResult{$key=$request->operationName.bin2hex($request->keyDigest);if(isset($this->records[$key]))return new IdempotencyClaimResult(IdempotencyClaimState::REPLAY,$this->records[$key]);return new IdempotencyClaimResult(IdempotencyClaimState::ACQUIRED,$this->records[$key]=new IdempotencyRecord(count($this->records)+1,$request->requestFingerprint,'in_progress',$leaseExpiresAt,null,false));}
    public function complete(int $recordId,string $leaseToken,IdempotencyResultReference $result,bool $sensitive,DateTimeImmutable $completedAt):void{foreach($this->records as$key=>$record)if($record->recordId===$recordId)$this->records[$key]=new IdempotencyRecord($recordId,$record->requestFingerprint,'completed',null,$result,$sensitive);}
    public function fail(int $recordId,string $leaseToken,DateTimeImmutable $failedAt):void{foreach($this->records as$key=>$record)if($record->recordId===$recordId)unset($this->records[$key]);}
}
