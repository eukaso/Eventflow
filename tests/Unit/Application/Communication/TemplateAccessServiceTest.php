<?php

namespace EventFlow\Tests\Unit\Application\Communication;

use DateTimeImmutable;
use DateTimeZone;
use EventFlow\Application\Audit\{AuditCanonicalizer, AuditPayloadRedactor, AuditRecord, AuditRepository, AuditService};
use EventFlow\Application\Authorization\{AuthorizationService, EventRole, GlobalRecoveryAuthority, MembershipReader, MembershipSnapshot, PrincipalContext, RoleCapabilityPolicy};
use EventFlow\Application\Clock\Clock;
use EventFlow\Application\Communication\{CommunicationChannel, CommunicationException, TemplateAccessRepository, TemplateAccessService, TemplatePage, TemplateRecord, TemplateRenderer, TemplateReplacement};
use EventFlow\Application\Idempotency\{CanonicalRequestHasher, IdempotencyClaimResult, IdempotencyClaimState, IdempotencyRecord, IdempotencyRepository, IdempotencyRequest, IdempotencyResultReference, IdempotencyService};
use EventFlow\Application\Persistence\EventScope;
use EventFlow\Application\Security\SecureRandom;
use EventFlow\Application\Transaction\{TransactionManager, TransactionOptions};
use PHPUnit\Framework\TestCase;

final class TemplateAccessServiceTest extends TestCase
{
    public function testReadsAreScopedAndCursorBounded(): void
    {
        $f=new TemplateAccessFixture();
        self::assertSame([11,12],array_map(static fn(TemplateRecord $t):int=>$t->templateId,$f->service->list($f->principal,$f->scope,2)->templates));
        self::assertSame('Reminder',$f->service->read($f->principal,$f->scope,11)->name);
        try{$f->service->list($f->principal,$f->scope,101);self::fail('Expected cursor validation.');}catch(CommunicationException $e){self::assertSame('validation_failed',$e->safeCode);}
    }

    public function testDraftUpdateIsRevisionGuardedNormalizedAndReplaySafe(): void
    {
        $f=new TemplateAccessFixture();$replacement=new TemplateReplacement(' Updated ','reminder','Hi {{recipient_name}}','Body {{guest_link}}',null,['guest_link','recipient_name','guest_link'],1);
        $first=$f->service->update($f->principal,$f->scope,11,$replacement,'update-key');
        self::assertSame(2,$first->response->revision);self::assertSame(['guest_link','recipient_name'],$first->response->allowedFields);self::assertSame('Updated',$first->response->name);
        self::assertTrue($f->service->update($f->principal,$f->scope,11,$replacement,'update-key')->replayed);
        foreach([[12,new TemplateReplacement('X','general',null,'Body',null,[],1),'template_immutable'],[11,new TemplateReplacement('X','general',null,'Body',null,[],1),'resource_modified']]as[$id,$change,$code]){try{$f->service->update($f->principal,$f->scope,$id,$change,'invalid-update-'.$id);self::fail('Expected update failure.');}catch(CommunicationException $e){self::assertSame($code,$e->safeCode);}}
    }

    public function testPublishedTemplateCreatesNewDraftVersion(): void
    {
        $f=new TemplateAccessFixture();$created=$f->service->newVersion($f->principal,$f->scope,12,1,'version-key')->response;
        self::assertSame(13,$created->templateId);self::assertSame(3,$created->version);self::assertSame('draft',$created->status);self::assertSame(1,$created->revision);
        try{$f->service->newVersion($f->principal,$f->scope,11,1,'version-bad');self::fail('Expected lifecycle failure.');}catch(CommunicationException $e){self::assertSame('template_transition_invalid',$e->safeCode);}
    }

    public function testArchiveProtectsMutableCampaignsAndPreviewUsesStoredTemplate(): void
    {
        $f=new TemplateAccessFixture();$f->repository->inUse=true;
        try{$f->service->archive($f->principal,$f->scope,12,1,'archive-used');self::fail('Expected in-use failure.');}catch(CommunicationException $e){self::assertSame('template_in_use',$e->safeCode);}
        $f->repository->inUse=false;$archived=$f->service->archive($f->principal,$f->scope,12,1,'archive-ok')->response;self::assertSame('archived',$archived->status);self::assertSame(2,$archived->revision);
        $preview=$f->service->preview($f->principal,$f->scope,11,['recipient_name'=>'A & B']);self::assertSame('Hi A &amp; B',$preview['subject']);self::assertStringContainsString(TemplateRenderer::PREVIEW_GUEST_LINK,$preview['body']);
        try{$f->service->preview($f->principal,$f->scope,11,['admin'=>'yes']);self::fail('Expected field rejection.');}catch(CommunicationException $e){self::assertSame('template_merge_field_invalid',$e->safeCode);}
    }
}

final class TemplateAccessFixture
{
    public EventScope $scope;public PrincipalContext $principal;public TemplateMemoryRepository $repository;public TemplateAccessService $service;
    public function __construct(){
        $this->scope=new EventScope(9);$this->principal=PrincipalContext::wordpressUser(7);$this->repository=new TemplateMemoryRepository($this->scope);
        $clock=new TemplateClock();$tx=new TemplateTransactions();$auth=new AuthorizationService(new TemplateMemberships(),new RoleCapabilityPolicy(),$clock,new TemplateRecovery());$idempotency=new IdempotencyService(new TemplateIdempotency(),$tx,$clock,new TemplateRandom(),new CanonicalRequestHasher());$audit=new AuditService(new TemplateAudit(),$tx,$clock,new AuditPayloadRedactor(),new AuditCanonicalizer());
        $this->service=new TemplateAccessService($this->repository,$auth,$idempotency,$audit,$clock,new TemplateRenderer());
    }
}

final class TemplateMemoryRepository implements TemplateAccessRepository
{
    public array $records;public bool $inUse=false;public function __construct(private EventScope $scope){$this->records=[11=>$this->record(11,1,'draft','Reminder'),12=>$this->record(12,2,'published','Published')];}
    public function listTemplates(EventScope $scope,int $limit,?int $afterTemplateId):TemplatePage{$items=array_values(array_filter($this->records,fn(TemplateRecord $t):bool=>$afterTemplateId===null||$t->templateId>$afterTemplateId));$more=count($items)>$limit;$items=array_slice($items,0,$limit);return new TemplatePage($items,$more?$items[array_key_last($items)]->templateId:null);}
    public function findTemplate(EventScope $scope,int $templateId):?TemplateRecord{return $this->records[$templateId]??null;}
    public function lockTemplate(EventScope $scope,int $templateId):?TemplateRecord{return $this->findTemplate($scope,$templateId);}
    public function updateTemplate(EventScope $scope,TemplateRecord $current,TemplateReplacement $r,int $actorUserId,DateTimeImmutable $now):TemplateRecord{return $this->records[$current->templateId]=new TemplateRecord($current->templateId,$current->templateKey,$r->name,$current->channel,$current->version,'draft',$r->subject,$r->body,$r->plainText,$r->allowedFields,$r->type,$current->revision+1);}
    public function createTemplateVersion(EventScope $scope,TemplateRecord $source,int $actorUserId,DateTimeImmutable $now):TemplateRecord{return $this->records[13]=new TemplateRecord(13,$source->templateKey,$source->name,$source->channel,3,'draft',$source->subject,$source->body,$source->plainText,$source->allowedFields,$source->type,1);}
    public function archiveTemplate(EventScope $scope,TemplateRecord $current,int $actorUserId,DateTimeImmutable $now):TemplateRecord{return $this->records[$current->templateId]=new TemplateRecord($current->templateId,$current->templateKey,$current->name,$current->channel,$current->version,'archived',$current->subject,$current->body,$current->plainText,$current->allowedFields,$current->type,$current->revision+1,archivedAt:$now);}
    public function templateHasMutableCampaigns(EventScope $scope,int $templateId):bool{return $this->inUse;}
    private function record(int $id,int $version,string $status,string $name):TemplateRecord{return new TemplateRecord($id,'reminder',$name,CommunicationChannel::EMAIL,$version,$status,'Hi {{recipient_name}}','Visit {{guest_link}}',null,['recipient_name','guest_link'],'reminder',1);}
}

final readonly class TemplateMemberships implements MembershipReader{public function findCurrent(EventScope $eventScope,int $userId):?MembershipSnapshot{return new MembershipSnapshot(1,$eventScope,$userId,EventRole::OWNER,false,null);}}
final readonly class TemplateRecovery implements GlobalRecoveryAuthority{public function canRecoverPrimaryOwnership(int $userId):bool{return false;}}
final readonly class TemplateClock implements Clock{public function now():DateTimeImmutable{return new DateTimeImmutable('2026-08-20 01:00:00',new DateTimeZone('UTC'));}}
final readonly class TemplateRandom implements SecureRandom{public function hex(int $bytes):string{return str_repeat('e',$bytes*2);}}
final class TemplateTransactions implements TransactionManager{private int $depth=0;public function transactional(callable $operation,?TransactionOptions $options=null):mixed{$this->depth++;try{return $operation();}finally{$this->depth--;}}public function isActive():bool{return $this->depth>0;}public function assertNotActive():void{if($this->depth>0)throw new \RuntimeException('active');}}
final class TemplateAudit implements AuditRepository{public function lockChainHead(?EventScope $eventScope):?string{return null;}public function append(AuditRecord $record):int{return 1;}}
final class TemplateIdempotency implements IdempotencyRepository
{
    private array $records=[];
    public function claim(IdempotencyRequest $request,string $leaseToken,DateTimeImmutable $now,DateTimeImmutable $leaseExpiresAt,DateTimeImmutable $recordExpiresAt):IdempotencyClaimResult{$key=$request->operationName.bin2hex($request->keyDigest);if(isset($this->records[$key]))return new IdempotencyClaimResult(IdempotencyClaimState::REPLAY,$this->records[$key]);return new IdempotencyClaimResult(IdempotencyClaimState::ACQUIRED,$this->records[$key]=new IdempotencyRecord(count($this->records)+1,$request->requestFingerprint,'in_progress',$leaseExpiresAt,null,false));}
    public function complete(int $recordId,string $leaseToken,IdempotencyResultReference $result,bool $sensitive,DateTimeImmutable $completedAt):void{foreach($this->records as$key=>$record)if($record->recordId===$recordId)$this->records[$key]=new IdempotencyRecord($recordId,$record->requestFingerprint,'completed',null,$result,$sensitive);}
    public function fail(int $recordId,string $leaseToken,DateTimeImmutable $failedAt):void{foreach($this->records as$key=>$record)if($record->recordId===$recordId)unset($this->records[$key]);}
}
