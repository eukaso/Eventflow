<?php

namespace EventFlow\Tests\Unit\Infrastructure\Persistence\WordPress;

use DateTimeImmutable;
use EventFlow\Application\EventConfiguration\{EventConfigurationAttributes, EventConfigurationRecord};
use EventFlow\Application\Persistence\EventScope;
use EventFlow\Application\Venue\{VenueAttributes, VenueRecord};
use EventFlow\Infrastructure\Persistence\WordPress\{WpdbAdapter, WpdbEventConfigurationRepository, WpdbTableNames, WpdbVenueRepository};
use PHPUnit\Framework\TestCase;

final class WpdbVenueConfigurationRepositoryTest extends TestCase
{
    public function testVenueListHydratesBoundedCursorPage(): void
    {
        $wpdb=new VCWpdb();$wpdb->rows=[$this->venueRow(4),$this->venueRow(5)];
        $page=$this->venues($wpdb)->list(1,3);
        self::assertCount(1,$page->venues);self::assertSame(4,$page->nextAfterVenueId);self::assertSame(2,$page->venues[0]->revision);
        self::assertStringContainsString('venue_id > 3',$wpdb->queries[0]);self::assertStringContainsString('LIMIT 2',$wpdb->queries[0]);
    }

    public function testVenueCreateAndUpdateWriteExplicitRevisionGuard(): void
    {
        $wpdb=new VCWpdb();$attributes=new VenueAttributes(['name'=>'Hall','country_code'=>'CA','latitude'=>51.05,'default_capacity'=>200]);
        $created=$this->venues($wpdb)->create($attributes,7,$this->now());
        $updated=$this->venues($wpdb)->update(new VenueRecord(81,$attributes,3),new VenueAttributes(['name'=>'Hall 2','city'=>'Calgary']),7,$this->now());
        self::assertSame(81,$created->venueId);self::assertSame(4,$updated->revision);
        self::assertStringContainsString('INSERT INTO wp_eventflow_venues',$wpdb->queries[0]);
        self::assertStringContainsString('venue_revision=venue_revision+1',$wpdb->queries[1]);
        self::assertStringContainsString('venue_revision=3',$wpdb->queries[1]);
    }

    public function testConfigurationHydrationAndUpdatePreserveTypedValuesAndRevision(): void
    {
        $wpdb=new VCWpdb();$wpdb->row=$this->configurationRow();$repository=$this->configurations($wpdb);$scope=new EventScope(51);
        $current=$repository->find($scope);
        self::assertSame(6,$current?->revision);self::assertTrue($current?->attributes->get('allow_guest_edits'));
        self::assertInstanceOf(DateTimeImmutable::class,$current?->attributes->get('confirmation_opens_at'));
        $replacement=new EventConfigurationAttributes(['seating_mode'=>'seat','automatic_seating_enabled'=>true,'reply_to_email'=>'host@example.com']);
        $updated=$repository->update(new EventConfigurationRecord($scope,$replacement,6),$replacement,7,$this->now());
        self::assertSame(7,$updated->revision);
        self::assertStringContainsString('configuration_revision=configuration_revision+1',$wpdb->queries[1]);
        self::assertStringContainsString('configuration_revision=6',$wpdb->queries[1]);
    }

    private function venues(VCWpdb $wpdb):WpdbVenueRepository{return new WpdbVenueRepository(new WpdbAdapter($wpdb),new WpdbTableNames('wp_'));}
    private function configurations(VCWpdb $wpdb):WpdbEventConfigurationRepository{return new WpdbEventConfigurationRepository(new WpdbAdapter($wpdb),new WpdbTableNames('wp_'));}
    private function now():DateTimeImmutable{return new DateTimeImmutable('2026-08-18T18:00:00Z');}
    private function venueRow(int $id):array{return ['venue_id'=>(string)$id,'venue_name'=>'Hall','venue_status'=>'active','venue_revision'=>'2','address_line_1'=>null,'address_line_2'=>null,'city'=>'Calgary','region'=>'AB','postal_code'=>null,'country_code'=>'CA','latitude'=>'51.05','longitude'=>'-114.07','phone'=>null,'email'=>null,'website_url'=>null,'default_capacity'=>'200','notes'=>null];}
    private function configurationRow():array{return ['event_id'=>'51','configuration_revision'=>'6','logo_media_id'=>null,'invitation_media_id'=>null,'primary_theme'=>null,'secondary_theme'=>null,'welcome_message'=>null,'confirmation_message'=>null,'surprise_notice'=>null,'dress_code'=>null,'confirmation_opens_at'=>'2026-09-01 18:00:00','confirmation_closes_at'=>'2026-09-02 18:00:00','allow_guest_edits'=>'1','seating_mode'=>'table','automatic_seating_enabled'=>'0','default_from_name'=>null,'reply_to_email'=>null,'default_sms_sender'=>null];}
}

final class VCWpdb
{
    public string $prefix='wp_';public string $last_error='';public int $last_errno=0;public int $insert_id=81;
    /** @var list<string> */public array $queries=[];/** @var list<array<string,mixed>> */public array $rows=[];/** @var array<string,mixed>|null */public ?array $row=null;
    public function prepare(string $query,mixed ...$values):string{foreach($values as $value){$replacement=is_int($value)||is_float($value)?(string)$value:"'".str_replace("'","''",(string)$value)."'";$query=(string)preg_replace('/%[dfs]/',$replacement,$query,1);}return $query;}
    public function query(string $query):int{$this->queries[]=$query;return 1;}
    public function get_results(string $query,string $format):array{$this->queries[]=$query;return $this->rows;}
    public function get_row(string $query,string $format):?array{$this->queries[]=$query;return $this->row;}
}
