<?php

namespace EventFlow\Presentation\Api;

use EventFlow\Application\Persistence\EventScope;

final readonly class PrivacyRequestMapper
{
    public function scope(RestRequest$request):EventScope{return new EventScope($this->routeId($request,'event_id'));}
    public function actionId(RestRequest$request):int{return$this->routeId($request,'privacy_action_id');}
    public function holdId(RestRequest$request):int{return$this->routeId($request,'retention_hold_id');}

    /** @return array{int,?int,?string,?string,?int} */
    public function actionPage(RestRequest$request):array{$this->queryOnly($request,['limit','after','status','kind','invitation_id']);return[$this->queryInt($request->query('limit'),50,100),$this->optionalQueryInt($request->query('after')),$request->query('status'),$request->query('kind'),$this->optionalQueryInt($request->query('invitation_id'))];}
    /** @return array{int,?int,?string,?int} */
    public function holdPage(RestRequest$request):array{$this->queryOnly($request,['limit','after','status','invitation_id']);return[$this->queryInt($request->query('limit'),50,100),$this->optionalQueryInt($request->query('after')),$request->query('status'),$this->optionalQueryInt($request->query('invitation_id'))];}

    /** @return array{int,string,string} */
    public function actionCreation(RestRequest$request):array
    {
        $json=$this->only($request->json(),['invitation_id','policy_version','purpose']);
        if(count($json)!==3||!array_key_exists('invitation_id',$json)||!array_key_exists('policy_version',$json)||!array_key_exists('purpose',$json))throw new RequestInputException('validation_failed');
        return[$this->positiveInt($json['invitation_id']),$this->policy($json['policy_version']),$this->text($json['purpose'])];
    }

    /** @return array{?int,string,string} */
    public function holdCreation(RestRequest$request):array
    {
        $json=$this->only($request->json(),['invitation_id','policy_version','reason']);
        if(!array_key_exists('policy_version',$json)||!array_key_exists('reason',$json))throw new RequestInputException('validation_failed');
        $invitation=array_key_exists('invitation_id',$json)&&$json['invitation_id']!==null?$this->positiveInt($json['invitation_id']):null;
        return[$invitation,$this->policy($json['policy_version']),$this->text($json['reason'])];
    }

    public function requireEmptyBody(RestRequest$request):void{if($request->json()!==[])throw new RequestInputException('validation_failed');}

    /** @param array<string,mixed> $source @param list<string> $allowed @return array<string,mixed> */
    private function only(array$source,array$allowed):array{if(array_diff(array_keys($source),$allowed)!==[])throw new RequestInputException('validation_failed');return$source;}
    private function policy(mixed$value):string{if(!is_string($value)||!preg_match('/^[A-Za-z0-9][A-Za-z0-9._-]{0,63}$/',$value))throw new RequestInputException('validation_failed');return$value;}
    private function text(mixed$value):string{if(!is_string($value)||trim($value)===''||strlen(trim($value))>500)throw new RequestInputException('validation_failed');return trim($value);}
    private function positiveInt(mixed$value):int{if(!is_int($value)||$value<1)throw new RequestInputException('validation_failed');return$value;}
    private function optionalQueryInt(?string$value):?int{return$value===null?null:$this->queryInt($value,null,PHP_INT_MAX);}
    /** @param list<string> $allowed */private function queryOnly(RestRequest$request,array$allowed):void{if(array_diff(array_keys($request->queries()),$allowed)!==[])throw new RequestInputException('validation_failed');}
    private function queryInt(?string$value,?int$default,int$max):int{if($value===null)return$default??throw new RequestInputException('validation_failed');if(!preg_match('/^[1-9][0-9]*$/',$value))throw new RequestInputException('validation_failed');$result=filter_var($value,FILTER_VALIDATE_INT,['options'=>['min_range'=>1,'max_range'=>$max]]);if($result===false)throw new RequestInputException('validation_failed');return$result;}
    private function routeId(RestRequest$request,string$name):int{$candidate=$request->route($name);if($candidate===null||!ctype_digit($candidate))throw new RequestInputException('resource_not_found');$value=filter_var($candidate,FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]);if($value===false)throw new RequestInputException('resource_not_found');return$value;}
}
