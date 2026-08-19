<?php

namespace EventFlow\Infrastructure\Persistence\WordPress;

use DateTimeImmutable;
use DateTimeZone;
use EventFlow\Application\Persistence\EventScope;
use EventFlow\Application\Seating\{RecommendationPlan, RecommendationStatus, RecommendedPlacement, SeatingException, SeatingRecommendationRepository, StoredRecommendation};
use EventFlow\Infrastructure\Persistence\{PersistenceException, TableName};

final class WpdbSeatingRecommendationRepository extends AbstractWpdbRepository implements SeatingRecommendationRepository
{
    public function create(EventScope $scope, RecommendationPlan $plan, int $actorUserId, DateTimeImmutable $now): StoredRecommendation
    {
        $recommendations = $this->table(TableName::SEATING_RECOMMENDATIONS); $timestamp = $this->timestamp($now);
        if ($this->database->execute("INSERT INTO {$recommendations} (event_id,recommendation_status,input_fingerprint,algorithm_version,recommendation_seed,created_by_user_id,created_at) VALUES (%d,%s,%s,%s,%s,%d,%s)", [$scope->eventId, 'draft', $plan->inputFingerprint, $plan->algorithmVersion, $plan->seed, $actorUserId, $timestamp]) !== 1) throw new PersistenceException('seating_recommendation_create_failed');
        $id = $this->database->lastInsertId();
        $placements = $this->table(TableName::SEATING_RECOMMENDATION_PLACEMENTS);
        foreach ($plan->placements as $index => $placement) {
            $sql = "INSERT INTO {$placements} (event_id,seating_recommendation_id,attendee_id,table_id,seat_id,placement_reason,sort_order) VALUES (%d,%d,%d,%d," . ($placement->seatId === null ? 'NULL' : '%d') . ',%s,%d)';
            $parameters = [$scope->eventId, $id, $placement->attendeeId, $placement->tableId]; if ($placement->seatId !== null) $parameters[] = $placement->seatId; $parameters[] = $placement->reason; $parameters[] = $index + 1;
            if ($this->database->execute($sql, $parameters) !== 1) throw new PersistenceException('seating_recommendation_placement_create_failed');
        }
        $warnings = $this->table(TableName::SEATING_RECOMMENDATION_WARNINGS);
        foreach ($plan->warnings as $index => $warning) if ($this->database->execute("INSERT INTO {$warnings} (event_id,seating_recommendation_id,warning_code,sort_order) VALUES (%d,%d,%s,%d)", [$scope->eventId, $id, $warning, $index + 1]) !== 1) throw new PersistenceException('seating_recommendation_warning_create_failed');
        return new StoredRecommendation($id, $scope, RecommendationStatus::DRAFT, $plan, $now);
    }

    public function find(EventScope $scope, int $recommendationId): ?StoredRecommendation { return $this->load($scope, $recommendationId, false); }
    public function lock(EventScope $scope, int $recommendationId): ?StoredRecommendation { return $this->load($scope, $recommendationId, true); }

    public function markApplied(StoredRecommendation $recommendation, int $actorUserId, DateTimeImmutable $now): StoredRecommendation
    {
        if ($recommendation->status === RecommendationStatus::APPLIED) return $recommendation;
        $table = $this->table(TableName::SEATING_RECOMMENDATIONS); $timestamp = $this->timestamp($now);
        $affected = $this->database->execute("UPDATE {$table} SET recommendation_status=%s,applied_by_user_id=%d,applied_at=%s WHERE event_id=%d AND seating_recommendation_id=%d AND recommendation_status=%s", ['applied', $actorUserId, $timestamp, $recommendation->eventScope->eventId, $recommendation->recommendationId, 'draft']);
        if ($affected !== 1) throw new SeatingException('resource_modified');
        return new StoredRecommendation($recommendation->recommendationId, $recommendation->eventScope, RecommendationStatus::APPLIED, $recommendation->plan, $recommendation->createdAt, $now);
    }

    private function load(EventScope $scope, int $id, bool $lock): ?StoredRecommendation
    {
        $suffix = $lock ? ' FOR UPDATE' : '';
        $table = $this->table(TableName::SEATING_RECOMMENDATIONS);
        $row = $this->database->fetchRow("SELECT seating_recommendation_id,recommendation_status,input_fingerprint,algorithm_version,recommendation_seed,created_at,applied_at FROM {$table} WHERE event_id=%d AND seating_recommendation_id=%d LIMIT 1{$suffix}", [$scope->eventId, $id]);
        if ($row === null) return null;
        $placementsTable = $this->table(TableName::SEATING_RECOMMENDATION_PLACEMENTS);
        $placementRows = $this->database->fetchAll("SELECT attendee_id,table_id,seat_id,placement_reason FROM {$placementsTable} WHERE event_id=%d AND seating_recommendation_id=%d ORDER BY sort_order ASC{$suffix}", [$scope->eventId, $id]);
        $warningsTable = $this->table(TableName::SEATING_RECOMMENDATION_WARNINGS);
        $warningRows = $this->database->fetchAll("SELECT warning_code FROM {$warningsTable} WHERE event_id=%d AND seating_recommendation_id=%d ORDER BY sort_order ASC{$suffix}", [$scope->eventId, $id]);
        $status = RecommendationStatus::tryFrom((string) $row['recommendation_status']) ?? throw new PersistenceException('seating_recommendation_status_invalid');
        $plan = new RecommendationPlan((string) $row['input_fingerprint'], (string) $row['algorithm_version'], (string) $row['recommendation_seed'], array_map(static fn (array $placement): RecommendedPlacement => new RecommendedPlacement((int) $placement['attendee_id'], (int) $placement['table_id'], $placement['seat_id'] === null ? null : (int) $placement['seat_id'], (string) $placement['placement_reason']), $placementRows), array_map(static fn (array $warning): string => (string) $warning['warning_code'], $warningRows));
        return new StoredRecommendation($id, $scope, $status, $plan, $this->date($row['created_at']), $row['applied_at'] === null ? null : $this->date($row['applied_at']));
    }

    private function timestamp(DateTimeImmutable $date): string { return $date->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s'); }
    private function date(mixed $value): DateTimeImmutable { if (!is_string($value) || $value === '') throw new PersistenceException('seating_recommendation_date_invalid'); return new DateTimeImmutable($value, new DateTimeZone('UTC')); }
}
