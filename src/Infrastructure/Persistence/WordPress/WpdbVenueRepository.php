<?php

namespace EventFlow\Infrastructure\Persistence\WordPress;

use DateTimeImmutable;
use DateTimeZone;
use EventFlow\Application\Venue\{VenueAttributes, VenuePage, VenueRecord, VenueRepository};
use EventFlow\Infrastructure\Persistence\{PersistenceException, TableName};

final class WpdbVenueRepository extends AbstractWpdbRepository implements VenueRepository
{
    private const FIELDS = ['address_line_1','address_line_2','city','region','postal_code','country_code','latitude','longitude','phone','email','website_url','default_capacity','notes'];

    public function list(int $limit, ?int $afterVenueId): VenuePage
    {
        if ($limit < 1 || $limit > 100 || ($afterVenueId !== null && $afterVenueId < 1)) throw new PersistenceException('venue_query_invalid');
        $table=$this->table(TableName::VENUES); $after=$afterVenueId===null?'':' AND venue_id > %d';
        $parameters=[]; if($afterVenueId!==null)$parameters[]=$afterVenueId; $parameters[]=$limit+1;
        $rows=$this->database->fetchAll('SELECT '.$this->columns()." FROM {$table} WHERE deleted_at IS NULL{$after} ORDER BY venue_id ASC LIMIT %d",$parameters);
        $more=count($rows)>$limit; if($more)array_pop($rows); $venues=array_map(fn(array $row):VenueRecord=>$this->hydrate($row),$rows);
        return new VenuePage($venues,$more&&$venues!==[]?$venues[array_key_last($venues)]->venueId:null);
    }

    public function find(int $venueId): ?VenueRecord { return $this->findRecord($venueId,false); }
    public function lock(int $venueId): ?VenueRecord { return $this->findRecord($venueId,true); }

    public function create(VenueAttributes $attributes, int $actorUserId, DateTimeImmutable $now): VenueRecord
    {
        if($actorUserId<1)throw new PersistenceException('venue_actor_invalid');
        $table=$this->table(TableName::VENUES); $a=$attributes->all(); $parameters=[$a['name'],$a['status']]; $values=['%s','%s'];
        foreach(self::FIELDS as $field)$values[]=$this->valueSql($a[$field],$this->placeholder($field),$parameters);
        $timestamp=$this->timestamp($now); array_push($parameters,$actorUserId,$actorUserId,$timestamp,$timestamp);
        $columns='venue_name,venue_status,'.implode(',',self::FIELDS).',created_by_user_id,updated_by_user_id,created_at,updated_at';
        if($this->database->execute("INSERT INTO {$table} ({$columns}) VALUES (".implode(',',$values).',%d,%d,%s,%s)',$parameters)!==1)throw new PersistenceException('venue_create_failed');
        return new VenueRecord($this->database->lastInsertId(),$attributes,1);
    }

    public function update(VenueRecord $current, VenueAttributes $replacement, int $actorUserId, DateTimeImmutable $now): VenueRecord
    {
        if($actorUserId<1)throw new PersistenceException('venue_actor_invalid');
        $table=$this->table(TableName::VENUES); $a=$replacement->all(); $parameters=[$a['name'],$a['status']]; $sets=['venue_name=%s','venue_status=%s'];
        foreach(self::FIELDS as $field){$sets[]="{$field}=".$this->valueSql($a[$field],$this->placeholder($field),$parameters);}
        array_push($parameters,$actorUserId,$this->timestamp($now),$current->venueId,$current->revision);
        $affected=$this->database->execute("UPDATE {$table} SET ".implode(',',$sets).',venue_revision=venue_revision+1,updated_by_user_id=%d,updated_at=%s WHERE venue_id=%d AND venue_revision=%d AND deleted_at IS NULL',$parameters);
        if($affected!==1)throw new PersistenceException('resource_modified');
        return new VenueRecord($current->venueId,$replacement,$current->revision+1);
    }

    private function findRecord(int $venueId,bool $lock): ?VenueRecord
    {
        if($venueId<1)throw new PersistenceException('venue_id_invalid');
        $table=$this->table(TableName::VENUES); $row=$this->database->fetchRow('SELECT '.$this->columns()." FROM {$table} WHERE venue_id=%d AND deleted_at IS NULL".($lock?' FOR UPDATE':''),[$venueId]);
        return $row===null?null:$this->hydrate($row);
    }

    /** @param array<string,mixed> $row */
    private function hydrate(array $row): VenueRecord
    {
        $values=['name'=>(string)($row['venue_name']??''),'status'=>(string)($row['venue_status']??'')];
        foreach(self::FIELDS as $field)$values[$field]=$row[$field]??null;
        foreach(['latitude','longitude'] as $field)if($values[$field]!==null)$values[$field]=(float)$values[$field];
        if($values['default_capacity']!==null)$values['default_capacity']=(int)$values['default_capacity'];
        return new VenueRecord((int)($row['venue_id']??0),new VenueAttributes($values),(int)($row['venue_revision']??0));
    }

    private function columns(): string { return 'venue_id,venue_name,venue_status,venue_revision,'.implode(',',self::FIELDS); }
    private function placeholder(string $field): string { return in_array($field,['latitude','longitude'],true)?'%f':($field==='default_capacity'?'%d':'%s'); }
    /** @param list<mixed> $parameters */ private function valueSql(mixed $value,string $placeholder,array &$parameters): string { if($value===null)return 'NULL';$parameters[]=$value;return $placeholder; }
    private function timestamp(DateTimeImmutable $date): string { return $date->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s'); }
}
