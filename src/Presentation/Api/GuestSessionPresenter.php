<?php

namespace EventFlow\Presentation\Api;

use DateTimeZone;
use EventFlow\Application\Error\RequestId;
use EventFlow\Application\GuestAccess\GuestSessionCredentials;

final readonly class GuestSessionPresenter
{
    public function bootstrap(GuestSessionCredentials $credentials, RequestId $requestId): JsonApiResponse
    {
        $utc = new DateTimeZone('UTC');
        $expiresAt = $credentials->session->expiresAt->setTimezone($utc);
        return new JsonApiResponse(
            201,
            [
                'data' => [
                    'event_id' => $credentials->session->eventScope->eventId,
                    'invitation_id' => $credentials->session->invitationId,
                    'expires_at' => $expiresAt->format('Y-m-d\TH:i:s\Z'),
                    'csrf_token' => $credentials->rawCsrfToken,
                ],
                'request_id' => $requestId->value,
            ],
            [
                'X-Request-ID' => $requestId->value,
                'Cache-Control' => 'no-store, max-age=0',
                'Pragma' => 'no-cache',
                'Set-Cookie' => GuestSessionCookie::NAME . '=' . $credentials->rawSessionToken
                    . '; Expires=' . $expiresAt->format('D, d M Y H:i:s') . ' GMT'
                    . '; Path=' . GuestSessionCookie::PATH . '; Secure; HttpOnly; SameSite=Lax',
            ],
        );
    }
}
