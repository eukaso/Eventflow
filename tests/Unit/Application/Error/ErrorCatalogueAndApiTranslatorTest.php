<?php

namespace EventFlow\Tests\Unit\Application\Error;

use EventFlow\Application\Authorization\AuthorizationException;
use EventFlow\Application\Attendee\AttendeeException;
use EventFlow\Application\CheckIn\CheckInException;
use EventFlow\Application\Communication\CommunicationException;
use EventFlow\Application\Error\CoreErrorCatalogue;
use EventFlow\Application\Error\ErrorCodeMapper;
use EventFlow\Application\Error\PreconditionDetails;
use EventFlow\Application\Error\PreconditionHeader;
use EventFlow\Application\Error\PublicApiException;
use EventFlow\Application\Error\RequestId;
use EventFlow\Application\Error\RequestIdFactory;
use EventFlow\Application\Error\RetryAfterDetails;
use EventFlow\Application\Error\ValidationErrorDetails;
use EventFlow\Application\Error\VersionConflictDetails;
use EventFlow\Application\Export\ExportException;
use EventFlow\Application\GuestAccess\GuestAccessException;
use EventFlow\Application\Privacy\PrivacyException;
use EventFlow\Application\Idempotency\IdempotencyException;
use EventFlow\Application\Invitation\InvitationException;
use EventFlow\Application\Membership\MembershipException;
use EventFlow\Application\Security\SecureRandom;
use EventFlow\Application\Seating\SeatingException;
use EventFlow\Infrastructure\Persistence\PersistenceException;
use EventFlow\Presentation\Api\ApiErrorTranslator;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class ErrorCatalogueAndApiTranslatorTest extends TestCase
{
    public function testCodeCatalogueExactlyMatchesAuthoritativeCsv(): void
    {
        $path = dirname(__DIR__, 4) . '/catalogues/EventFlow-Error-Catalogue-v1.0.csv';
        $handle = fopen($path, 'rb');
        self::assertIsResource($handle);
        $header = fgetcsv($handle, null, ',', '"', '\\');
        self::assertSame(['code', 'http_status', 'retryability', 'meaning'], $header);
        $csv = [];
        while (($row = fgetcsv($handle, null, ',', '"', '\\')) !== false) {
            $csv[$row[0]] = [(int) $row[1], $row[2], $row[3]];
        }
        fclose($handle);

        $definitions = [];
        foreach (CoreErrorCatalogue::create()->all() as $definition) {
            $definitions[$definition->code] = [
                $definition->httpStatus,
                $definition->retryability->value,
                $definition->publicMessage,
            ];
        }

        self::assertCount(29, $definitions);
        self::assertSame($csv, $definitions);
    }

    public function testKnownAuthorizationAndIdempotencyCodesRemainStable(): void
    {
        $translator = $this->translator();
        $requestId = new RequestId('req_0123456789abcdef0123456789abcdef');

        $forbidden = $translator->translate(
            new AuthorizationException('insufficient_event_permission'),
            $requestId,
        );
        self::assertSame(403, $forbidden->status);
        self::assertSame('insufficient_event_permission', $forbidden->body['code']);

        $returnOnce = $translator->translate(
            new IdempotencyException('idempotency_sensitive_result_not_replayable'),
            $requestId,
        );
        self::assertSame(409, $returnOnce->status);
        self::assertSame('idempotency_sensitive_result_not_replayable', $returnOnce->body['code']);

        $stalePlan = $translator->translate(new SeatingException('seating_recommendation_stale'), $requestId);
        self::assertSame(409, $stalePlan->status);
        self::assertSame('seating_recommendation_stale', $stalePlan->body['code']);

        $invalidSeat = $translator->translate(new SeatingException('accessible_seat_required'), $requestId);
        self::assertSame(422, $invalidSeat->status);
        self::assertSame('validation_failed', $invalidSeat->body['code']);

        $notDownloadable = $translator->translate(new ExportException('export_not_downloadable'), $requestId);
        self::assertSame(422, $notDownloadable->status);
        self::assertSame('validation_failed', $notDownloadable->body['code']);

        $storageFailure = $translator->translate(new ExportException('export_storage_unavailable'), $requestId);
        self::assertSame(503, $storageFailure->status);
        self::assertSame('temporarily_unavailable', $storageFailure->body['code']);

        $held = $translator->translate(new PrivacyException('retention_hold_active'), $requestId);
        self::assertSame(412, $held->status);
        self::assertSame('resource_modified', $held->body['code']);

        $invalidPrivacyRequest = $translator->translate(new PrivacyException('privacy_request_invalid'), $requestId);
        self::assertSame(422, $invalidPrivacyRequest->status);
        self::assertSame('validation_failed', $invalidPrivacyRequest->body['code']);

        $missingMembership = $translator->translate(new MembershipException('membership_not_found'), $requestId);
        self::assertSame(404, $missingMembership->status);
        self::assertSame('resource_not_found', $missingMembership->body['code']);

        $staleOwner = $translator->translate(new MembershipException('primary_owner_version_conflict'), $requestId);
        self::assertSame(412, $staleOwner->status);
        self::assertSame('resource_modified', $staleOwner->body['code']);

        $invalidMembership = $translator->translate(new MembershipException('membership_transition_invalid'), $requestId);
        self::assertSame(422, $invalidMembership->status);
        self::assertSame('validation_failed', $invalidMembership->body['code']);

        $missingInvitation = $translator->translate(new InvitationException('invitation_not_found'), $requestId);
        self::assertSame(404, $missingInvitation->status);
        self::assertSame('resource_not_found', $missingInvitation->body['code']);

        $invalidInvitation = $translator->translate(new InvitationException('invitation_transition_invalid'), $requestId);
        self::assertSame(422, $invalidInvitation->status);
        self::assertSame('validation_failed', $invalidInvitation->body['code']);

        $invalidGuestCredential = $translator->translate(new GuestAccessException('guest_credential_invalid'), $requestId);
        self::assertSame(401, $invalidGuestCredential->status);
        self::assertSame('guest_session_invalid', $invalidGuestCredential->body['code']);

        $staleRsvp = $translator->translate(new AttendeeException('guest_response_modified'), $requestId);
        self::assertSame(409, $staleRsvp->status);
        self::assertSame('guest_response_modified', $staleRsvp->body['code']);

        $invalidRsvp = $translator->translate(new AttendeeException('primary_attendee_continuity_required'), $requestId);
        self::assertSame(422, $invalidRsvp->status);
        self::assertSame('validation_failed', $invalidRsvp->body['code']);

        $foreignAttendee = $translator->translate(new AttendeeException('attendee_scope_invalid'), $requestId);
        self::assertSame(404, $foreignAttendee->status);
        self::assertSame('resource_not_found', $foreignAttendee->body['code']);

        $implicitRoleChange = $translator->translate(new AttendeeException('attendee_role_change_requires_command'), $requestId);
        self::assertSame(422, $implicitRoleChange->status);
        self::assertSame('validation_failed', $implicitRoleChange->body['code']);

        $foreignSeat = $translator->translate(new SeatingException('seating_seat_invalid'), $requestId);
        self::assertSame(404, $foreignSeat->status);
        self::assertSame('resource_not_found', $foreignSeat->body['code']);

        $duplicateCheckIn = $translator->translate(new CheckInException('attendee_already_checked_in'), $requestId);
        self::assertSame(409, $duplicateCheckIn->status);
        self::assertSame('attendee_already_checked_in', $duplicateCheckIn->body['code']);

        $repeatReversal = $translator->translate(new CheckInException('checkin_already_reversed'), $requestId);
        self::assertSame(409, $repeatReversal->status);
        self::assertSame('checkin_already_reversed', $repeatReversal->body['code']);

        $missingTemplate = $translator->translate(new CommunicationException('resource_not_found'), $requestId);
        self::assertSame(404, $missingTemplate->status);
        self::assertSame('resource_not_found', $missingTemplate->body['code']);

        $immutableTemplate = $translator->translate(new CommunicationException('template_immutable'), $requestId);
        self::assertSame(422, $immutableTemplate->status);
        self::assertSame('validation_failed', $immutableTemplate->body['code']);

        $queuedCampaign = $translator->translate(new CommunicationException('campaign_already_queued'), $requestId);
        self::assertSame(409, $queuedCampaign->status);
        self::assertSame('campaign_already_queued', $queuedCampaign->body['code']);
    }

    public function testUnknownFailureNeverLeaksMessageTraceOrSecretContext(): void
    {
        $response = $this->translator()->translate(
            new RuntimeException('SQL failed password=secret-token at /private/path.php:88'),
            new RequestId('req_0123456789abcdef0123456789abcdef'),
        );
        $json = json_encode($response->body, JSON_THROW_ON_ERROR);

        self::assertSame(500, $response->status);
        self::assertSame('internal_error', $response->body['code']);
        self::assertStringNotContainsString('secret-token', $json);
        self::assertStringNotContainsString('/private/path', $json);
        self::assertArrayNotHasKey('trace', $response->body['data']);
    }

    public function testPersistenceInternalsCollapseToSafePublicCodes(): void
    {
        $requestId = new RequestId('req_0123456789abcdef0123456789abcdef');
        $temporary = $this->translator()->translate(
            new PersistenceException('database_deadlock'),
            $requestId,
            new RetryAfterDetails(5),
        );
        self::assertSame('temporarily_unavailable', $temporary->body['code']);
        self::assertSame('5', $temporary->headers['Retry-After']);

        $internal = $this->translator()->translate(
            new PersistenceException('database_query_failed'),
            $requestId,
        );
        self::assertSame('internal_error', $internal->body['code']);

        $corruptIdempotency = $this->translator()->translate(
            new IdempotencyException('idempotency_record_invalid'),
            $requestId,
        );
        self::assertSame('internal_error', $corruptIdempotency->body['code']);
    }

    public function testOnlyMatchingTypedDetailsArePublished(): void
    {
        $requestId = new RequestId('req_0123456789abcdef0123456789abcdef');
        $validation = $this->translator()->translate(
            new PublicApiException('validation_failed'),
            $requestId,
            new ValidationErrorDetails(['email' => ['required', 'invalid_format']]),
        );
        self::assertSame(
            ['fields' => ['email' => ['required', 'invalid_format']]],
            $validation->body['data']['details'],
        );

        $wrongKind = $this->translator()->translate(
            new PublicApiException('validation_failed'),
            $requestId,
            new VersionConflictDetails(2, 3),
        );
        self::assertArrayNotHasKey('details', $wrongKind->body['data']);

        $precondition = $this->translator()->translate(
            new PublicApiException('precondition_required'),
            $requestId,
            new PreconditionDetails(PreconditionHeader::IF_MATCH),
        );
        self::assertSame('If-Match', $precondition->body['data']['details']['required_header']);
    }

    public function testInvalidUntrustedRequestIdIsReplacedInsteadOfReflected(): void
    {
        $factory = new RequestIdFactory(new ErrorTestRandom());
        $requestId = $factory->fromUntrusted("bad\r\nX-Injected: yes");

        self::assertSame('req_' . str_repeat('a', 32), $requestId->value);
        self::assertStringNotContainsString('Injected', $requestId->value);
    }

    public function testPublicDetailTypesEnforceBoundedSchemas(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new ValidationErrorDetails(['email' => ['Raw secret email@example.test']]);
    }

    private function translator(): ApiErrorTranslator
    {
        $catalogue = CoreErrorCatalogue::create();
        return new ApiErrorTranslator($catalogue, new ErrorCodeMapper($catalogue));
    }
}

final class ErrorTestRandom implements SecureRandom
{
    public function hex(int $bytes): string
    {
        return str_repeat('a', $bytes * 2);
    }
}
