<?php

namespace EventFlow\Presentation\Api;

use EventFlow\Application\Communication\{TemplateRecord, TemplateReplacement};
use EventFlow\Application\Persistence\EventScope;

final readonly class TemplateAccessRequestMapper
{
    public function scope(RestRequest $request):EventScope{return new EventScope($this->routeId($request,'event_id'));}
    public function templateId(RestRequest $request):int{return $this->routeId($request,'template_id');}
    /** @return array{int,?int} */
    public function page(RestRequest $request):array{return [$this->queryInt($request->query('limit'),50,1,100),$request->query('after')===null?null:$this->queryInt($request->query('after'),null,1,PHP_INT_MAX)];}

    public function replacement(RestRequest $request,TemplateRecord $current,int $expectedRevision):TemplateReplacement
    {
        $json=$this->only($request,['name','type','subject','body','plain_text','allowed_fields']);if($json===[])throw new RequestInputException('validation_failed');
        $fields=$current->allowedFields;if(array_key_exists('allowed_fields',$json)){if(!is_array($json['allowed_fields'])||!array_is_list($json['allowed_fields']))throw new RequestInputException('validation_failed');$fields=array_map(fn(mixed $field):string=>$this->string($field),$json['allowed_fields']);}
        return new TemplateReplacement(
            array_key_exists('name',$json)?$this->string($json['name']):$current->name,
            array_key_exists('type',$json)?$this->string($json['type']):$current->type,
            array_key_exists('subject',$json)?$this->nullableString($json['subject'],false):$current->subject,
            array_key_exists('body',$json)?$this->string($json['body'],false):$current->body,
            array_key_exists('plain_text',$json)?$this->nullableString($json['plain_text'],false):$current->plainText,
            $fields,$expectedRevision,
        );
    }

    /** @return array<string,string> */
    public function previewValues(RestRequest $request):array
    {
        $json=$this->only($request,['values']);if(array_keys($json)!==['values']||!is_array($json['values'])||array_is_list($json['values']))throw new RequestInputException('validation_failed');$values=[];foreach($json['values']as$field=>$value){if(!is_string($field)||!is_string($value))throw new RequestInputException('validation_failed');$values[$field]=$value;}ksort($values);return $values;
    }

    public function requireEmptyBody(RestRequest $request):void{if($request->json()!==[])throw new RequestInputException('validation_failed');}
    /** @param list<string> $allowed @return array<string,mixed> */ private function only(RestRequest $request,array $allowed):array{$json=$request->json();if(array_diff(array_keys($json),$allowed)!==[])throw new RequestInputException('validation_failed');return $json;}
    private function routeId(RestRequest $request,string $name):int{$candidate=$request->route($name);if($candidate===null||!ctype_digit($candidate))throw new RequestInputException('resource_not_found');$value=filter_var($candidate,FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]);if($value===false)throw new RequestInputException('resource_not_found');return $value;}
    private function queryInt(?string $value,?int $default,int $min,int $max):int{if($value===null)return $default??throw new RequestInputException('validation_failed');if(!preg_match('/^[1-9][0-9]*$/',$value))throw new RequestInputException('validation_failed');$result=filter_var($value,FILTER_VALIDATE_INT,['options'=>['min_range'=>$min,'max_range'=>$max]]);if($result===false)throw new RequestInputException('validation_failed');return $result;}
    private function string(mixed $value,bool $trim=true):string{if(!is_string($value))throw new RequestInputException('validation_failed');return $trim?trim($value):$value;}
    private function nullableString(mixed $value,bool $trim=true):?string{return $value===null?null:$this->string($value,$trim);}
}
