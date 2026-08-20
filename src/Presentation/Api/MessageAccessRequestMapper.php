<?php

namespace EventFlow\Presentation\Api;

use EventFlow\Application\Persistence\EventScope;

final readonly class MessageAccessRequestMapper
{
    public function scope(RestRequest $request):EventScope{return new EventScope($this->routeId($request,'event_id'));}
    public function messageId(RestRequest $request):int{return $this->routeId($request,'message_id');}

    /** @return array{int,?int,?int,?string} */
    public function page(RestRequest $request):array
    {
        $status=$request->query('status');
        if($status!==null&&!preg_match('/^[a-z][a-z_]{1,31}$/',$status))throw new RequestInputException('validation_failed');
        return[
            $this->queryInt($request->query('limit'),50,1,100),
            $request->query('after')===null?null:$this->queryInt($request->query('after'),null,1,PHP_INT_MAX),
            $request->query('campaign_id')===null?null:$this->queryInt($request->query('campaign_id'),null,1,PHP_INT_MAX),
            $status,
        ];
    }

    public function requireEmptyBody(RestRequest $request):void{if($request->json()!==[])throw new RequestInputException('validation_failed');}
    private function routeId(RestRequest$request,string$name):int{$candidate=$request->route($name);if($candidate===null||!ctype_digit($candidate))throw new RequestInputException('resource_not_found');$value=filter_var($candidate,FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]);if($value===false)throw new RequestInputException('resource_not_found');return$value;}
    private function queryInt(?string$value,?int$default,int$min,int$max):int{if($value===null)return$default??throw new RequestInputException('validation_failed');if(!preg_match('/^[1-9][0-9]*$/',$value))throw new RequestInputException('validation_failed');$result=filter_var($value,FILTER_VALIDATE_INT,['options'=>['min_range'=>$min,'max_range'=>$max]]);if($result===false)throw new RequestInputException('validation_failed');return$result;}
}
