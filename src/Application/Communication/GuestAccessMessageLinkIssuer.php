<?php

namespace EventFlow\Application\Communication;

use DateInterval;
use EventFlow\Application\Authorization\PrincipalContext;
use EventFlow\Application\Clock\Clock;
use EventFlow\Application\GuestAccess\GuestAccessService;
use EventFlow\Application\GuestAccess\IssuedGuestLink;
use EventFlow\Application\Persistence\EventScope;

final readonly class GuestAccessMessageLinkIssuer implements MessageGuestLinkIssuer
{
    public function __construct(
        private GuestAccessService $guestAccess,
        private Clock $clock,
        private string $guestPageUrl,
        private int $lifetimeDays = 30,
    ) {
        if ($lifetimeDays < 1 || $lifetimeDays > 90 || filter_var($guestPageUrl, FILTER_VALIDATE_URL) === false || !preg_match('/^https:\/\//i', $guestPageUrl)) {
            throw new CommunicationException('guest_page_url_invalid');
        }
    }

    public function issue(PrincipalContext $principal, EventScope $scope, int $invitationId, int $messageId, CampaignPurpose $purpose): string
    {
        $outcome = $this->guestAccess->issueMessageLink(
            $principal,
            $scope,
            $invitationId,
            $messageId,
            $purpose->value,
            $this->clock->now()->add(new DateInterval('P' . $this->lifetimeDays . 'D')),
            'message-guest-link-' . $messageId . '-' . $purpose->value,
        );
        if (!$outcome->response instanceof IssuedGuestLink) {
            throw new CommunicationException('guest_link_issue_failed');
        }

        $binaryCredential = hex2bin($outcome->response->rawCredential);
        if ($binaryCredential === false) {
            throw new CommunicationException('guest_link_issue_failed');
        }
        $compactCredential = rtrim(strtr(base64_encode($binaryCredential), '+/', '-_'), '=');

        return preg_replace('/#.*$/', '', rtrim($this->guestPageUrl, '/'))
            . '/#i=' . $compactCredential;
    }
}
