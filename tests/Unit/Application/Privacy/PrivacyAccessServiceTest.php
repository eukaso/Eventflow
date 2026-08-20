<?php

namespace EventFlow\Tests\Unit\Application\Privacy;

use DateTimeImmutable;
use DateTimeZone;
use EventFlow\Application\Authorization\AuthorizationException;
use EventFlow\Application\Authorization\AuthorizationService;
use EventFlow\Application\Authorization\EventRole;
use EventFlow\Application\Authorization\GlobalRecoveryAuthority;
use EventFlow\Application\Authorization\MembershipReader;
use EventFlow\Application\Authorization\MembershipSnapshot;
use EventFlow\Application\Authorization\PrincipalContext;
use EventFlow\Application\Authorization\RoleCapabilityPolicy;
use EventFlow\Application\Clock\Clock;
use EventFlow\Application\Persistence\EventScope;
use EventFlow\Application\Privacy\PrivacyAccessRepository;
use EventFlow\Application\Privacy\PrivacyAccessService;
use EventFlow\Application\Privacy\PrivacyActionPage;
use EventFlow\Application\Privacy\PrivacyActionRecord;
use EventFlow\Application\Privacy\PrivacyException;
use EventFlow\Application\Privacy\RetentionHoldPage;
use EventFlow\Application\Privacy\RetentionHoldRecord;
use PHPUnit\Framework\TestCase;

final class PrivacyAccessServiceTest extends TestCase
{
    public function testPrimaryOwnerCanQueryActionsAndHoldsWithStrictFilters(): void
    {
        $repository = new PrivacyAccessMemoryRepository();
        $service = $this->service($repository, true);
        $principal = PrincipalContext::wordpressUser(7);
        $scope = new EventScope(10);

        $actions = $service->listActions($principal,$scope,25,10,'processing','explicit',44);
        self::assertSame([25,10,'processing','explicit',44],$repository->actionQuery);
        self::assertSame(12,$actions->nextAfterActionId);
        self::assertSame(11,$service->readAction($principal,$scope,11)->privacyActionId);

        $holds = $service->listHolds($principal,$scope,20,4,'active',44);
        self::assertSame([20,4,'active',44],$repository->holdQuery);
        self::assertSame(6,$holds->nextAfterHoldId);
        self::assertSame(5,$service->readHold($principal,$scope,5)->retentionHoldId);
    }

    public function testNonPrimaryOwnerCannotReadPrivacyAdministration(): void
    {
        $this->expectException(AuthorizationException::class);
        $this->service(new PrivacyAccessMemoryRepository(),false)->listActions(PrincipalContext::wordpressUser(7),new EventScope(10));
    }

    public function testInvalidLifecycleFilterFailsBeforePersistence(): void
    {
        $repository = new PrivacyAccessMemoryRepository();
        $this->expectException(PrivacyException::class);
        try {
            $this->service($repository,true)->listActions(PrincipalContext::wordpressUser(7),new EventScope(10),status:'secret');
        } finally {
            self::assertSame([],$repository->actionQuery);
        }
    }

    private function service(PrivacyAccessRepository $repository, bool $primary): PrivacyAccessService
    {
        $clock = new PrivacyAccessClock();
        return new PrivacyAccessService($repository,new AuthorizationService(new PrivacyAccessMemberships($primary),new RoleCapabilityPolicy(),$clock,new PrivacyAccessRecovery()));
    }
}

final class PrivacyAccessMemoryRepository implements PrivacyAccessRepository
{
    public array $actionQuery=[];
    public array $holdQuery=[];
    public function listActions(EventScope$s,int$l,?int$a,?string$status,?string$kind,?int$invitationId):PrivacyActionPage{$this->actionQuery=[$l,$a,$status,$kind,$invitationId];return new PrivacyActionPage([$this->action()],12);}
    public function findAction(EventScope$s,int$id):?PrivacyActionRecord{return$id===11?$this->action():null;}
    public function listHolds(EventScope$s,int$l,?int$a,?string$status,?int$invitationId):RetentionHoldPage{$this->holdQuery=[$l,$a,$status,$invitationId];return new RetentionHoldPage([$this->hold()],6);}
    public function findHold(EventScope$s,int$id):?RetentionHoldRecord{return$id===5?$this->hold():null;}
    private function action():PrivacyActionRecord{$now=new DateTimeImmutable('2026-08-19T12:00:00Z');return new PrivacyActionRecord(11,new EventScope(10),44,'explicit','retention-2026.1','Verified request','processing','pii_minimized',requestedAt:$now);}
    private function hold():RetentionHoldRecord{$now=new DateTimeImmutable('2026-08-19T12:00:00Z');return new RetentionHoldRecord(5,new EventScope(10),44,'legal-2026.1','Litigation preservation','active',7,placedAt:$now);}
}

final readonly class PrivacyAccessMemberships implements MembershipReader
{
    public function __construct(private bool$primary){}
    public function findCurrent(EventScope$s,int$u):?MembershipSnapshot{return new MembershipSnapshot(1,$s,$u,EventRole::OWNER,$this->primary,null);}
}
final readonly class PrivacyAccessClock implements Clock{public function now():DateTimeImmutable{return new DateTimeImmutable('2026-08-19 12:00:00',new DateTimeZone('UTC'));}}
final readonly class PrivacyAccessRecovery implements GlobalRecoveryAuthority{public function canRecoverPrimaryOwnership(int$u):bool{return false;}}
