<?php

namespace EventFlow\Infrastructure\Persistence\WordPress;

use DateTimeImmutable;
use DateTimeZone;
use EventFlow\Application\EventConfiguration\{EventConfigurationAttributes, EventConfigurationRecord, EventConfigurationRepository};
use EventFlow\Application\Persistence\EventScope;
use EventFlow\Infrastructure\Persistence\{PersistenceException, TableName};

final class WpdbEventConfigurationRepository extends AbstractWpdbRepository implements EventConfigurationRepository
{
    private const FIELDS=['logo_media_id','invitation_media_id','primary_theme','secondary_theme','welcome_message','confirmation_message','surprise_notice','dress_code','confirmation_opens_at','confirmation_closes_at','allow_guest_edits','seating_mode','automatic_seating_enabled','default_from_name','reply_to_email','default_sms_sender'];

    public function find(EventScope $scope): ?EventConfigurationRecord { return $this->findRecord($scope,false); }
    public function lock(EventScope $scope): ?EventConfigurationRecord { return $this->findRecord($scope,true); }

    public function update(EventConfigurationRecord $current, EventConfigurationAttributes $replacement, int $actorUserId, DateTimeImmutable $now): EventConfigurationRecord
    {
        if($actorUserId<1)throw new PersistenceException('event_configuration_actor_invalid');
        $table=$this->table(TableName::EVENT_CONFIGURATIONS);$values=$replacement->all();$parameters=[];$sets=[];
        foreach(self::FIELDS as $field){$value=$this->databaseValue($field,$values[$field]);$sets[]="{$field}=".$this->valueSql($value,$this->placeholder($field),$parameters);}
        array_push($parameters,$actorUserId,$this->timestamp($now),$current->eventScope->eventId,$current->revision);
        $affected=$this->database->execute("UPDATE {$table} SET ".implode(',',$sets).',configuration_revision=configuration_revision+1,updated_by_user_id=%d,updated_at=%s WHERE event_id=%d AND configuration_revision=%d',$parameters);
        if($affected!==1)throw new PersistenceException('resource_modified');
        return new EventConfigurationRecord($current->eventScope,$replacement,$current->revision+1);
    }

    private function findRecord(EventScope $scope,bool $lock): ?EventConfigurationRecord
    {
        $table=$this->table(TableName::EVENT_CONFIGURATIONS);$row=$this->database->fetchRow('SELECT event_id,configuration_revision,'.implode(',',self::FIELDS)." FROM {$table} WHERE event_id=%d".($lock?' FOR UPDATE':''),[$scope->eventId]);
        if($row===null)return null;
        if((int)($row['event_id']??0)!==$scope->eventId)throw new PersistenceException('event_configuration_record_invalid');
        $values=[];foreach(self::FIELDS as $field)$values[$field]=$row[$field]??null;
        foreach(['logo_media_id','invitation_media_id'] as $field)if($values[$field]!==null)$values[$field]=(int)$values[$field];
        foreach(['allow_guest_edits','automatic_seating_enabled'] as $field)$values[$field]=(bool)(int)$values[$field];
        foreach(['confirmation_opens_at','confirmation_closes_at'] as $field)if($values[$field]!==null)$values[$field]=new DateTimeImmutable((string)$values[$field],new DateTimeZone('UTC'));
        return new EventConfigurationRecord($scope,new EventConfigurationAttributes($values),(int)($row['configuration_revision']??0));
    }

    private function placeholder(string $field): string { return in_array($field,['logo_media_id','invitation_media_id','allow_guest_edits','automatic_seating_enabled'],true)?'%d':'%s'; }
    private function databaseValue(string $field,mixed $value): mixed
    {
        if($value instanceof DateTimeImmutable)return $this->timestamp($value);
        if(in_array($field,['allow_guest_edits','automatic_seating_enabled'],true))return $value?1:0;
        return $value;
    }
    /** @param list<mixed> $parameters */ private function valueSql(mixed $value,string $placeholder,array &$parameters): string { if($value===null)return 'NULL';$parameters[]=$value;return $placeholder; }
    private function timestamp(DateTimeImmutable $date): string { return $date->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s'); }
}
