<?php

namespace EventFlow\Application\Privacy;

use DateTimeImmutable;
use EventFlow\Application\Persistence\EventScope;

interface PrivacyRepository
{
    public function createAction(EventScope $scope, int $invitationId, string $kind, string $policyVersion, string $purpose, ?int $actorUserId, DateTimeImmutable $now): PrivacyActionRecord;
    public function resume(EventScope $scope, int $actionId, DateTimeImmutable $now): PrivacyActionRecord;
    public function advance(PrivacyActionRecord $action, string $checkpoint, DateTimeImmutable $now): PrivacyActionRecord;
    public function fail(PrivacyActionRecord $action, string $failureCode, DateTimeImmutable $now): void;
    public function revokeCredentials(PrivacyActionRecord $action, DateTimeImmutable $now): void;
    public function minimizePii(PrivacyActionRecord $action, DateTimeImmutable $now): void;
    /** @return list<string> */ public function invalidatePiiExports(PrivacyActionRecord $action, DateTimeImmutable $now): array;
    /** @return list<string> */ public function invalidatedArtifactLocators(PrivacyActionRecord $action): array;
    public function recordTombstone(PrivacyActionRecord $action, DateTimeImmutable $now): void;
    public function complete(PrivacyActionRecord $action, DateTimeImmutable $now): PrivacyActionRecord;
    public function placeHold(EventScope $scope, ?int $invitationId, string $policyVersion, string $reason, int $actorUserId, DateTimeImmutable $now): RetentionHoldRecord;
    public function releaseHold(EventScope $scope, int $holdId, int $actorUserId, DateTimeImmutable $now): RetentionHoldRecord;
    public function requireReconciliation(DateTimeImmutable $now): int;
    /** @return list<PrivacyActionRecord> */ public function pendingReconciliation(): array;
    public function isReconciled(): bool;
}
