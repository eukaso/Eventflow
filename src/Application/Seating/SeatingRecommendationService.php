<?php

namespace EventFlow\Application\Seating;

use EventFlow\Application\Audit\{AuditAction, AuditEntityType, AuditEvent, AuditService};
use EventFlow\Application\Authorization\{AuthorizationService, Capability, PrincipalContext, PrincipalType};
use EventFlow\Application\Clock\Clock;
use EventFlow\Application\Idempotency\{IdempotencyOutcome, IdempotencyResultReference, IdempotencyService, IdempotentOperationResult};
use EventFlow\Application\Persistence\EventScope;
use EventFlow\Application\Transaction\TransactionManager;

final readonly class SeatingRecommendationService implements SeatingRecommendationOperations
{
    public function __construct(
        private SeatingPlanningCommands $planning,
        private SeatingRecommendationRepository $recommendations,
        private AuthorizationService $authorization,
        private IdempotencyService $idempotency,
        private AuditService $audit,
        private Clock $clock,
        private TransactionManager $transactions,
    ) {
    }

    public function generate(PrincipalContext $principal, EventScope $scope, string $seed, string $idempotencyKey): IdempotencyOutcome
    {
        $normalizedSeed = trim($seed);
        if ($normalizedSeed === '' || strlen($normalizedSeed) > 190) throw new SeatingException('recommendation_seed_invalid');
        return $this->idempotency->execute($principal, $scope, 'seating.recommendation.generate', $idempotencyKey, ['seed' => $normalizedSeed],
            function () use ($principal, $scope, $normalizedSeed): IdempotentOperationResult {
                $plan = $this->planning->recommend($principal, $scope, $normalizedSeed);
                $stored = $this->recommendations->create($scope, $plan, $this->actor($principal), $this->clock->now());
                $this->audit->recordRequired(new AuditEvent($principal, $scope, AuditAction::SEATING_RECOMMENDATION_GENERATED, AuditEntityType::SEATING_RECOMMENDATION, $stored->recommendationId, after: ['input_fingerprint' => $plan->inputFingerprint, 'algorithm_version' => $plan->algorithmVersion, 'placement_count' => count($plan->placements), 'warning_count' => count($plan->warnings)]));
                return new IdempotentOperationResult(new IdempotencyResultReference('seating_recommendation', $stored->recommendationId, 201), $stored);
            });
    }

    public function get(PrincipalContext $principal, EventScope $scope, int $recommendationId): StoredRecommendation
    {
        $this->authorization->requireEventCapability($principal, $scope, Capability::VIEW_EVENT);
        return $this->recommendations->find($scope, $recommendationId) ?? throw new SeatingException('resource_not_found');
    }

    public function apply(PrincipalContext $principal, EventScope $scope, int $recommendationId, string $idempotencyKey): IdempotencyOutcome
    {
        $this->authorization->requireEventCapability($principal, $scope, Capability::MANAGE_SEATING);
        return $this->transactions->transactional(function () use ($principal, $scope, $recommendationId, $idempotencyKey): IdempotencyOutcome {
            $stored = $this->recommendations->lock($scope, $recommendationId) ?? throw new SeatingException('resource_not_found');
            $outcome = $this->planning->applyRecommendation($principal, $scope, $stored->plan, $idempotencyKey);
            $applied = $this->recommendations->markApplied($stored, $this->actor($principal), $this->clock->now());
            if ($stored->status !== RecommendationStatus::APPLIED) {
                $this->audit->recordRequired(new AuditEvent($principal, $scope, AuditAction::SEATING_RECOMMENDATION_APPLIED, AuditEntityType::SEATING_RECOMMENDATION, $recommendationId, before: ['status' => $stored->status->value], after: ['status' => $applied->status->value, 'placement_count' => count($applied->plan->placements)]));
            }
            return new IdempotencyOutcome($outcome->replayed, new IdempotencyResultReference('seating_recommendation', $recommendationId, 200), $applied);
        });
    }

    private function actor(PrincipalContext $principal): int
    {
        return $principal->type === PrincipalType::WORDPRESS_USER && $principal->userId !== null
            ? $principal->userId
            : throw new SeatingException('authentication_required');
    }
}
