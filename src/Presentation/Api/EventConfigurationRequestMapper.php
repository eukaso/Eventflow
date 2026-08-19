<?php
namespace EventFlow\Presentation\Api;
use DateTimeImmutable;
use EventFlow\Application\EventConfiguration\EventConfigurationPatch;
use EventFlow\Application\Persistence\EventScope;
use Exception;
use InvalidArgumentException;
final readonly class EventConfigurationRequestMapper
{
    private const FIELDS=['logo_media_id','invitation_media_id','primary_theme','secondary_theme','welcome_message','confirmation_message','surprise_notice','dress_code','confirmation_opens_at','confirmation_closes_at','allow_guest_edits','seating_mode','automatic_seating_enabled','default_from_name','reply_to_email','default_sms_sender'];
    public function scope(RestRequest $request):EventScope{$value=$request->route('event_id');if($value===null||!ctype_digit($value))throw new RequestInputException('resource_not_found');$id=filter_var($value,FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]);if($id===false)throw new RequestInputException('resource_not_found');return new EventScope($id);}
    public function patch(RestRequest $request,int $revision):EventConfigurationPatch{$json=$request->json();if($json===[]||array_diff(array_keys($json),self::FIELDS)!==[])throw new RequestInputException('validation_failed');foreach(['confirmation_opens_at','confirmation_closes_at'] as $field)if(array_key_exists($field,$json))$json[$field]=$this->date($json[$field]);try{return new EventConfigurationPatch($json,$revision);}catch(InvalidArgumentException){throw new RequestInputException('validation_failed');}}
    private function date(mixed $value):?DateTimeImmutable{if($value===null)return null;if(!is_string($value)||!preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}(?:Z|[+-]\d{2}:\d{2})$/',$value))throw new RequestInputException('validation_failed');try{$date=new DateTimeImmutable($value);}catch(Exception){throw new RequestInputException('validation_failed');}$canonical=str_ends_with($value,'Z')?substr($value,0,-1).'+00:00':$value;if($date->format('Y-m-d\TH:i:sP')!==$canonical)throw new RequestInputException('validation_failed');return $date;}
}
