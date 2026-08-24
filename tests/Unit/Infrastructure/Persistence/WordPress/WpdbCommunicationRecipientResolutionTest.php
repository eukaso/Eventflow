<?php

namespace EventFlow\Tests\Unit\Infrastructure\Persistence\WordPress;

use EventFlow\Application\Communication\{AudienceMode,CampaignPurpose,CampaignRecord,CommunicationChannel};
use EventFlow\Application\Persistence\EventScope;
use EventFlow\Infrastructure\Persistence\WordPress\{WpdbAdapter,WpdbCommunicationRepository,WpdbTableNames};
use PHPUnit\Framework\TestCase;

final class WpdbCommunicationRecipientResolutionTest extends TestCase
{
    public function testSmsCampaignUsesNormalizedPhoneAndExcludesMissingPhones():void
    {
        $wpdb=new CommunicationRecipientWpdb([['invitation_id'=>'7','attendee_id'=>null,'display_name'=>'Test Recipient','address'=>'+15878910335','event_name'=>'Celebration']]);$repository=$this->repository($wpdb);$campaign=$this->campaign(CommunicationChannel::SMS,'active_invitations');
        $recipients=$repository->resolveRecipients(new EventScope(3),$campaign);
        self::assertSame('+15878910335',$recipients[0]->address);self::assertSame('Celebration',$recipients[0]->mergeFields['event_name']);self::assertStringContainsString('i.primary_phone_normalized address',$wpdb->query);self::assertStringContainsString('i.primary_phone_normalized IS NOT NULL',$wpdb->query);self::assertStringNotContainsString('primary_email',$wpdb->query);
    }

    public function testEmailCampaignUsesNormalizedEmailForConfirmedAttendees():void
    {
        $wpdb=new CommunicationRecipientWpdb([['invitation_id'=>'7','attendee_id'=>'8','display_name'=>'Test Recipient','address'=>'guest@example.test','event_name'=>'Celebration']]);$repository=$this->repository($wpdb);$campaign=$this->campaign(CommunicationChannel::EMAIL,'confirmed_attendees');
        $recipients=$repository->resolveRecipients(new EventScope(3),$campaign);
        self::assertSame('guest@example.test',$recipients[0]->address);self::assertStringContainsString('COALESCE(a.email_normalized,i.primary_email_normalized) address',$wpdb->query);self::assertStringContainsString('COALESCE(a.email_normalized,i.primary_email_normalized) IS NOT NULL',$wpdb->query);self::assertStringNotContainsString('phone_normalized',$wpdb->query);
    }

    private function repository(CommunicationRecipientWpdb $wpdb):WpdbCommunicationRepository{return new WpdbCommunicationRepository(new WpdbAdapter($wpdb),new WpdbTableNames('wp_'));}
    private function campaign(CommunicationChannel $channel,string $filter):CampaignRecord{return new CampaignRecord(5,4,'Certification',$channel,CampaignPurpose::OPERATIONAL,AudienceMode::SNAPSHOT,['mode'=>'snapshot','filter'=>$filter,'invitation_ids'=>[7]],'draft');}
}

final class CommunicationRecipientWpdb
{
    public string $prefix='wp_';public string $last_error='';public int $last_errno=0;public string $query='';
    /** @param list<array<string,mixed>> $rows */ public function __construct(private array $rows){}
    public function prepare(string $query,mixed ...$values):string{foreach($values as$value){$replacement=is_int($value)?(string)$value:"'".str_replace("'","''",(string)$value)."'";$query=(string)preg_replace('/%[dfs]/',$replacement,$query,1);}return$query;}
    /** @return list<array<string,mixed>> */ public function get_results(string $query,string $format):array{$this->query=$query;return$this->rows;}
}
