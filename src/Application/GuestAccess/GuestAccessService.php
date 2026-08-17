<?php

namespace EventFlow\Application\GuestAccess;

use DateInterval;
use DateTimeImmutable;
use EventFlow\Application\Audit\AuditAction;
use EventFlow\Application\Audit\AuditEntityType;
use EventFlow\Application\Audit\AuditEvent;
use EventFlow\Application\Audit\AuditService;
use EventFlow\Application\Authorization\AuthorizationService;
use EventFlow\Application\Authorization\Capability;
use EventFlow\Application\Authorization\PrincipalContext;
use EventFlow\Application\Clock\Clock;
use EventFlow\Application\Idempotency\IdempotencyOutcome;
use EventFlow\Application\Idempotency\IdempotencyResultReference;
use EventFlow\Application\Idempotency\IdempotencyService;
use EventFlow\Application\Idempotency\IdempotentOperationResult;
use EventFlow\Application\Invitation\InvitationRepository;
use EventFlow\Application\Invitation\InvitationStatus;
use EventFlow\Application\Persistence\EventScope;
use EventFlow\Application\Security\CredentialDigester;
use EventFlow\Application\Security\SecureRandom;
use EventFlow\Application\Transaction\TransactionManager;

final readonly class GuestAccessService implements GuestSessionAuthenticator, GuestSessionBootstrap
{
    public function __construct(
        private GuestAccessRepository $guestAccess,
        private InvitationRepository $invitations,
        private AuthorizationService $authorization,
        private IdempotencyService $idempotency,
        private AuditService $audit,
        private Clock $clock,
        private SecureRandom $random,
        private CredentialDigester $digester,
        private TransactionManager $transactions,
        private int $sessionLifetimeSeconds = 28800,
    ) {
        if ($sessionLifetimeSeconds < 300 || $sessionLifetimeSeconds > 86400) {
            throw new \InvalidArgumentException('invalid_guest_session_lifetime');
        }
    }

    public function bootstrap(string $rawCredential, GuestCredentialType $type): GuestSessionCredentials
    {
        return $this->transactions->transactional(function () use ($rawCredential, $type): GuestSessionCredentials {
            $now = $this->clock->now();
            $digest = $this->digestOrFail($rawCredential, 'guest_credential_invalid');
            $invitation = $this->guestAccess->resolveBootstrapCredential($type, $digest, $now);
            if ($invitation === null || $invitation->status !== InvitationStatus::ACTIVE) {
                throw new GuestAccessException('guest_credential_invalid');
            }
            if ($invitation->tokenExpiresAt !== null && $invitation->tokenExpiresAt <= $now) {
                throw new GuestAccessException('guest_credential_invalid');
            }
            $rawSession = $this->random->hex(32);
            $rawCsrf = $this->random->hex(32);
            $session = $this->guestAccess->createSession(
                $invitation,
                $this->digester->digest($rawSession),
                $this->digester->digest($rawCsrf),
                $now->add(new DateInterval('PT' . $this->sessionLifetimeSeconds . 'S')),
                $now,
            );
            $this->guestAccess->markCredentialUsed($type, $digest, $invitation, $now);

            return new GuestSessionCredentials($session, $rawSession, $rawCsrf);
        });
    }

    public function authenticate(
        string $rawSessionToken,
        ?string $rawCsrfToken = null,
        bool $stateChanging = false,
        bool $sameOrigin = true,
    ): PrincipalContext {
        return $this->transactions->transactional(function () use ($rawSessionToken, $rawCsrfToken, $stateChanging, $sameOrigin): PrincipalContext {
            $session = $this->guestAccess->findCurrentSession($this->digestOrFail($rawSessionToken, 'guest_session_invalid'), $this->clock->now());
            if ($session === null || $session->expiresAt <= $this->clock->now()) {
                throw new GuestAccessException('guest_session_invalid');
            }
            if ($stateChanging && (!$sameOrigin || $rawCsrfToken === null || !$this->csrfMatches($rawCsrfToken, $session->csrfSecretDigest))) {
                throw new GuestAccessException('guest_csrf_invalid');
            }
            $this->guestAccess->touchSession($session, $this->clock->now());

            return PrincipalContext::guest($session->sessionId, $session->eventScope, $session->invitationId);
        });
    }

    public function issueMessageLink(
        PrincipalContext $principal,
        EventScope $scope,
        int $invitationId,
        int $messageId,
        string $purpose,
        DateTimeImmutable $expiresAt,
        string $idempotencyKey,
    ): IdempotencyOutcome {
        if ($messageId < 1 || !preg_match('/^[a-z][a-z0-9_.-]{0,63}$/', $purpose)) {
            throw new GuestAccessException('guest_link_request_invalid');
        }
        return $this->idempotency->execute(
            $principal,
            $scope,
            'guest_link.issue',
            $idempotencyKey,
            ['event_id' => $scope->eventId, 'invitation_id' => $invitationId, 'message_id' => $messageId, 'purpose' => $purpose, 'expires_at' => $expiresAt->format(DATE_ATOM)],
            function () use ($principal, $scope, $invitationId, $messageId, $purpose, $expiresAt): IdempotentOperationResult {
                $this->authorization->requireEventCapability($principal, $scope, Capability::QUEUE_CAMPAIGN);
                if ($expiresAt <= $this->clock->now()) {
                    throw new GuestAccessException('guest_link_expiry_invalid');
                }
                $invitation = $this->invitations->lock($scope, $invitationId);
                if ($invitation === null || $invitation->status !== InvitationStatus::ACTIVE) {
                    throw new GuestAccessException('invitation_not_found');
                }
                $raw = $this->random->hex(32);
                $credentialId = $this->guestAccess->issueMessageLink(
                    $scope, $invitationId, $messageId, $purpose, $this->digester->digest($raw),
                    $invitation->tokenVersion, $expiresAt, $this->clock->now(),
                );
                $this->audit->recordRequired(new AuditEvent(
                    principal: $principal,
                    eventScope: $scope,
                    action: AuditAction::GUEST_LINK_CREDENTIAL_ISSUED,
                    entityType: AuditEntityType::INVITATION,
                    entityId: $invitationId,
                    after: ['message_id' => $messageId, 'purpose' => $purpose, 'token_version' => $invitation->tokenVersion],
                ));

                return new IdempotentOperationResult(
                    new IdempotencyResultReference('guest_link_credential', $credentialId, 201),
                    new IssuedGuestLink($credentialId, $raw),
                    sensitiveReturnOnce: true,
                );
            },
        );
    }

    private function digestOrFail(string $rawCredential, string $safeCode): string
    {
        try {
            return $this->digester->digest($rawCredential);
        } catch (\InvalidArgumentException) {
            throw new GuestAccessException($safeCode);
        }
    }

    private function csrfMatches(string $rawCsrfToken, string $digest): bool
    {
        try {
            return $this->digester->matches($rawCsrfToken, $digest);
        } catch (\InvalidArgumentException) {
            return false;
        }
    }
}
