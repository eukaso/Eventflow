<?php

namespace EventFlow\Application\Authorization;

use EventFlow\Application\Persistence\EventScope;
use InvalidArgumentException;

final readonly class PrincipalContext
{
    /**
     * @param list<Capability> $committedCapabilities
     */
    private function __construct(
        public PrincipalType $type,
        public string $principalId,
        public ?int $userId = null,
        public ?EventScope $eventScope = null,
        public ?int $invitationId = null,
        public array $committedCapabilities = [],
    ) {
        if ($principalId === '' || strlen($principalId) > 190) {
            throw new InvalidArgumentException('invalid_principal_identifier');
        }
    }

    public static function anonymous(): self
    {
        return new self(PrincipalType::ANONYMOUS, 'anonymous');
    }

    public static function wordpressUser(int $userId): self
    {
        if ($userId < 1) {
            throw new InvalidArgumentException('invalid_wordpress_user_principal');
        }

        return new self(PrincipalType::WORDPRESS_USER, 'wp_user:' . $userId, userId: $userId);
    }

    public static function guest(int $guestSessionId, EventScope $eventScope, int $invitationId): self
    {
        if ($guestSessionId < 1 || $invitationId < 1) {
            throw new InvalidArgumentException('invalid_guest_invitation_scope');
        }

        return new self(
            PrincipalType::GUEST,
            'guest_session:' . $guestSessionId,
            eventScope: $eventScope,
            invitationId: $invitationId,
        );
    }

    /** @param list<Capability> $committedCapabilities */
    public static function backgroundJob(
        int $jobId,
        ?EventScope $eventScope,
        array $committedCapabilities,
    ): self {
        if ($jobId < 1) {
            throw new InvalidArgumentException('invalid_background_job_principal');
        }

        $uniqueCapabilities = [];

        foreach ($committedCapabilities as $capability) {
            if (!$capability instanceof Capability) {
                throw new InvalidArgumentException('invalid_committed_job_capability');
            }

            $uniqueCapabilities[$capability->value] = $capability;
        }

        return new self(
            PrincipalType::BACKGROUND_JOB,
            'job:' . $jobId,
            eventScope: $eventScope,
            committedCapabilities: array_values($uniqueCapabilities),
        );
    }

    public static function providerWebhook(string $providerAccountReference): self
    {
        if ($providerAccountReference === '') {
            throw new InvalidArgumentException('invalid_provider_principal');
        }

        return new self(PrincipalType::PROVIDER_WEBHOOK, 'provider:' . $providerAccountReference);
    }

    public static function migration(string $migrationKey): self
    {
        if ($migrationKey === '') {
            throw new InvalidArgumentException('invalid_migration_principal');
        }

        return new self(PrincipalType::MIGRATION, 'migration:' . $migrationKey);
    }

    public static function system(string $operation): self
    {
        if ($operation === '') {
            throw new InvalidArgumentException('invalid_system_principal');
        }

        return new self(PrincipalType::SYSTEM, 'system:' . $operation);
    }
}
