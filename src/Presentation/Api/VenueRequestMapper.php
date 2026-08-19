<?php
namespace EventFlow\Presentation\Api;
use EventFlow\Application\Venue\{VenueAttributes,VenuePatch};
use InvalidArgumentException;
final readonly class VenueRequestMapper
{
    private const FIELDS=['name','status','address_line_1','address_line_2','city','region','postal_code','country_code','latitude','longitude','phone','email','website_url','default_capacity','notes'];
    public function create(RestRequest $request):VenueAttributes{return $this->attributes($request->json(),true);}
    public function patch(RestRequest $request,int $revision):VenuePatch{$json=$request->json();if($json===[]||array_diff(array_keys($json),self::FIELDS)!==[])throw new RequestInputException('validation_failed');try{return new VenuePatch($json,$revision);}catch(InvalidArgumentException){throw new RequestInputException('validation_failed');}}
    public function venueId(RestRequest $request):int{return $this->positive($request->route('venue_id'),'resource_not_found');}
    /** @return array{int,?int} */public function page(RestRequest $request):array{return [$this->query($request->query('limit'),50,1,100),$request->query('after')===null?null:$this->query($request->query('after'),null,1,PHP_INT_MAX)];}
    private function attributes(array $json,bool $requireName):VenueAttributes{if(($requireName&&!array_key_exists('name',$json))||array_diff(array_keys($json),self::FIELDS)!==[])throw new RequestInputException('validation_failed');try{return new VenueAttributes($json);}catch(InvalidArgumentException){throw new RequestInputException('validation_failed');}}
    private function positive(?string $value,string $code):int{if($value===null||!ctype_digit($value))throw new RequestInputException($code);$id=filter_var($value,FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]);if($id===false)throw new RequestInputException($code);return $id;}
    private function query(?string $value,?int $default,int $min,int $max):int{if($value===null)return $default??throw new RequestInputException('validation_failed');if(!preg_match('/^[1-9][0-9]*$/',$value))throw new RequestInputException('validation_failed');$number=filter_var($value,FILTER_VALIDATE_INT,['options'=>['min_range'=>$min,'max_range'=>$max]]);if($number===false)throw new RequestInputException('validation_failed');return $number;}
}
