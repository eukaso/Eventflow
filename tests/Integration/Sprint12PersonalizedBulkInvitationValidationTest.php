<?php

namespace EventFlow\Tests\Integration;

use PHPUnit\Framework\TestCase;

final class Sprint12PersonalizedBulkInvitationValidationTest extends TestCase
{
    public function testQueueIssuesAndPersistsUniqueGuestLinkBeforeDeliveryJob(): void
    {
        $service = $this->source('src/Application/Communication/CommunicationService.php');
        $create = strpos($service, '->createOrFindMessage(');
        $issue = strpos($service, '$this->guestLinks->issue(');
        $finalize = strpos($service, '->finalizeMessageContent(');
        $enqueue = strpos($service, '$this->jobs->enqueue(');
        self::assertIsInt($create);
        self::assertIsInt($issue);
        self::assertIsInt($finalize);
        self::assertIsInt($enqueue);
        self::assertLessThan($issue, $create);
        self::assertLessThan($finalize, $issue);
        self::assertLessThan($enqueue, $finalize);
        self::assertStringContainsString('guest_page_url_not_configured', $service);
    }

    public function testCredentialsRemainInUrlFragmentAndConfigurationRequiresHttps(): void
    {
        $issuer = $this->source('src/Application/Communication/GuestAccessMessageLinkIssuer.php');
        self::assertStringContainsString("'/#i='", $issuer);
        self::assertStringContainsString("strtr(base64_encode(\$binaryCredential), '+/', '-_')", $issuer);
        self::assertStringContainsString("preg_match('/^https", $issuer);
        self::assertStringNotContainsString('?eventflow-invitation=', $issuer);
        self::assertStringNotContainsString('?i=', $issuer);
        $composition = $this->source('src/Bootstrap/DatabaseFoundation.php');
        self::assertStringContainsString("defined('EVENTFLOW_GUEST_PAGE_URL')", $composition);
        self::assertStringContainsString("str_starts_with(strtolower(\$guestPageUrl), 'https://')", $composition);
    }

    public function testGuestClientSupportsCompactAndAlreadyIssuedCredentials(): void
    {
        $client = $this->source('assets/guest/eventflow-guest.js');
        self::assertStringContainsString("parameters.get('i')", $client);
        self::assertStringContainsString("parameters.get('eventflow-invitation')", $client);
        self::assertStringContainsString("window.atob", $client);
        self::assertStringContainsString("value.length % 4", $client);
        self::assertStringContainsString("binary.length !== 16 && binary.length !== 32", $client);
    }

    public function testRecipientMergeFieldsIncludeEventAndPersonalization(): void
    {
        $repository = $this->source('src/Infrastructure/Persistence/WordPress/WpdbCommunicationRepository.php');
        foreach (["'recipient_name'", "'event_name'", "'guest_link'"] as $field) {
            self::assertStringContainsString($field, $repository);
        }
        self::assertStringContainsString('JOIN {$e} e ON e.event_id=i.event_id', $repository);
    }

    private function source(string $path): string
    {
        $source = file_get_contents(dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $path));
        self::assertIsString($source, $path);
        return $source;
    }
}
