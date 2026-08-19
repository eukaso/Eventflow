<?php

namespace EventFlow\Application\Venue;

use EventFlow\Application\Audit\{AuditAction, AuditEntityType, AuditEvent, AuditService};
use EventFlow\Application\Authorization\{PrincipalContext, PrincipalType};
use EventFlow\Application\Clock\Clock;
use EventFlow\Application\Idempotency\{IdempotencyOutcome, IdempotencyResultReference, IdempotencyService, IdempotentOperationResult};
use InvalidArgumentException;

final readonly class VenueService implements VenueOperations
{
    public function __construct(
        private VenueRepository $venues,
        private VenueAuthority $authority,
        private IdempotencyService $idempotency,
        private AuditService $audit,
        private Clock $clock,
    ) {}

    public function list(PrincipalContext $principal, int $limit = 50, ?int $afterVenueId = null): VenuePage
    {
        $this->requireManager($principal);
        if ($limit < 1 || $limit > 100 || ($afterVenueId !== null && $afterVenueId < 1)) throw new VenueException('validation_failed');
        return $this->venues->list($limit, $afterVenueId);
    }

    public function read(PrincipalContext $principal, int $venueId): VenueRecord
    {
        $this->requireManager($principal);
        if ($venueId < 1) throw new VenueException('resource_not_found');
        return $this->venues->find($venueId) ?? throw new VenueException('resource_not_found');
    }

    public function create(PrincipalContext $principal, VenueAttributes $attributes, string $idempotencyKey): IdempotencyOutcome
    {
        $actor = $this->requireManager($principal);
        return $this->idempotency->execute($principal, null, 'venue.create', $idempotencyKey, $attributes->all(), function () use ($principal,$attributes,$actor): IdempotentOperationResult {
            $venue = $this->venues->create($attributes, $actor, $this->clock->now());
            $this->audit->recordRequired(new AuditEvent($principal,null,AuditAction::VENUE_CREATED,AuditEntityType::VENUE,$venue->venueId,after:$this->snapshot($venue)));
            return new IdempotentOperationResult(new IdempotencyResultReference('venue',$venue->venueId,201),$venue);
        });
    }

    public function update(PrincipalContext $principal, int $venueId, VenuePatch $patch, string $idempotencyKey): IdempotencyOutcome
    {
        $actor = $this->requireManager($principal);
        return $this->idempotency->execute($principal, null, 'venue.update', $idempotencyKey, ['venue_id'=>$venueId,...$patch->canonicalRequest()], function () use ($principal,$venueId,$patch,$actor): IdempotentOperationResult {
            $current = $this->venues->lock($venueId) ?? throw new VenueException('resource_not_found');
            if ($current->revision !== $patch->expectedRevision) throw new VenueException('resource_modified');
            try { $replacement = $patch->applyTo($current); } catch (InvalidArgumentException) { throw new VenueException('validation_failed'); }
            $updated = $this->venues->update($current,$replacement,$actor,$this->clock->now());
            $this->audit->recordRequired(new AuditEvent($principal,null,AuditAction::VENUE_UPDATED,AuditEntityType::VENUE,$venueId,before:$this->snapshot($current),after:$this->snapshot($updated)));
            return new IdempotentOperationResult(new IdempotencyResultReference('venue',$venueId,200),$updated);
        });
    }

    private function requireManager(PrincipalContext $principal): int
    {
        if ($principal->type !== PrincipalType::WORDPRESS_USER || $principal->userId === null) throw new VenueException('authentication_required');
        if (!$this->authority->canManageVenues($principal->userId)) throw new VenueException('insufficient_event_permission');
        return $principal->userId;
    }

    /** @return array<string, mixed> */ private function snapshot(VenueRecord $venue): array { return ['venue_id'=>$venue->venueId,...$venue->attributes->all(),'revision'=>$venue->revision]; }
}
