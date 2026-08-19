<?php

namespace EventFlow\Application\EventConfiguration;

use EventFlow\Application\Audit\{AuditAction, AuditEntityType, AuditEvent, AuditService};
use EventFlow\Application\Authorization\{AuthorizationService, Capability, PrincipalContext, PrincipalType};
use EventFlow\Application\Clock\Clock;
use EventFlow\Application\Idempotency\{IdempotencyOutcome, IdempotencyResultReference, IdempotencyService, IdempotentOperationResult};
use EventFlow\Application\Persistence\EventScope;
use InvalidArgumentException;

final readonly class EventConfigurationService implements EventConfigurationOperations
{
    public function __construct(
        private EventConfigurationRepository $configurations,
        private AuthorizationService $authorization,
        private IdempotencyService $idempotency,
        private AuditService $audit,
        private Clock $clock,
    ) {}

    public function read(PrincipalContext $principal, EventScope $scope): EventConfigurationRecord
    {
        $this->authorization->requireEventCapability($principal,$scope,Capability::VIEW_EVENT);
        return $this->configurations->find($scope) ?? throw new EventConfigurationException('resource_not_found');
    }

    public function update(PrincipalContext $principal, EventScope $scope, EventConfigurationPatch $patch, string $idempotencyKey): IdempotencyOutcome
    {
        return $this->idempotency->execute($principal,$scope,'event_configuration.update',$idempotencyKey,$patch->canonicalRequest(),function () use ($principal,$scope,$patch): IdempotentOperationResult {
            $current = $this->configurations->lock($scope) ?? throw new EventConfigurationException('resource_not_found');
            $this->authorization->requireEventCapability($principal,$scope,Capability::EDIT_EVENT);
            if ($current->revision !== $patch->expectedRevision) throw new EventConfigurationException('resource_modified');
            try { $replacement = $patch->applyTo($current); } catch (InvalidArgumentException) { throw new EventConfigurationException('validation_failed'); }
            $updated = $this->configurations->update($current,$replacement,$this->actor($principal),$this->clock->now());
            $this->audit->recordRequired(new AuditEvent($principal,$scope,AuditAction::EVENT_CONFIGURATION_UPDATED,AuditEntityType::EVENT_CONFIGURATION,$scope->eventId,before:$this->snapshot($current),after:$this->snapshot($updated)));
            return new IdempotentOperationResult(new IdempotencyResultReference('event_configuration',$scope->eventId,200),$updated);
        });
    }

    private function actor(PrincipalContext $principal): int
    {
        if ($principal->type !== PrincipalType::WORDPRESS_USER || $principal->userId === null) throw new EventConfigurationException('event_actor_invalid');
        return $principal->userId;
    }

    /** @return array<string, mixed> */
    private function snapshot(EventConfigurationRecord $configuration): array
    {
        $attributes = $configuration->attributes->all();
        foreach ($attributes as $field=>$value) if ($value instanceof \DateTimeImmutable) $attributes[$field]=$value->format(DATE_ATOM);
        return [...$attributes,'revision'=>$configuration->revision];
    }
}
