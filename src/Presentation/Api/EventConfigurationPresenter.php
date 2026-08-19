<?php
namespace EventFlow\Presentation\Api;
use DateTimeImmutable;
use DateTimeZone;
use EventFlow\Application\Error\RequestId;
use EventFlow\Application\EventConfiguration\EventConfigurationRecord;
use EventFlow\Application\Idempotency\IdempotencyOutcome;
final readonly class EventConfigurationPresenter
{
    public function resource(EventConfigurationRecord $record,RequestId $id):JsonApiResponse{return new JsonApiResponse(200,['data'=>$this->data($record),'request_id'=>$id->value],$this->headers($id,$record));}
    public function outcome(IdempotencyOutcome $outcome,RequestId $id):JsonApiResponse{$record=$outcome->response instanceof EventConfigurationRecord?$outcome->response:null;$data=$record?$this->data($record):['type'=>$outcome->reference->entityType,'id'=>$outcome->reference->entityId];return new JsonApiResponse($outcome->reference->responseStatusCode,['data'=>$data,'meta'=>['replayed'=>$outcome->replayed],'request_id'=>$id->value],$this->headers($id,$record));}
    private function data(EventConfigurationRecord $record):array{$values=$record->attributes->all();$utc=new DateTimeZone('UTC');foreach($values as $field=>$value)if($value instanceof DateTimeImmutable)$values[$field]=$value->setTimezone($utc)->format('Y-m-d\TH:i:s\Z');return ['event_id'=>$record->eventScope->eventId,...$values,'revision'=>$record->revision];}
    private function headers(RequestId $id,?EventConfigurationRecord $record):array{$headers=['X-Request-ID'=>$id->value,'Cache-Control'=>'no-store, max-age=0'];if($record)$headers['ETag']='"'.$record->revision.'"';return $headers;}
}
