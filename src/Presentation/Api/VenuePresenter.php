<?php
namespace EventFlow\Presentation\Api;
use EventFlow\Application\Error\RequestId;
use EventFlow\Application\Idempotency\IdempotencyOutcome;
use EventFlow\Application\Venue\{VenuePage,VenueRecord};
final readonly class VenuePresenter
{
    public function page(VenuePage $page,RequestId $id):JsonApiResponse{return new JsonApiResponse(200,['data'=>array_map($this->data(...),$page->venues),'meta'=>['next_after_venue_id'=>$page->nextAfterVenueId],'request_id'=>$id->value],$this->headers($id));}
    public function resource(VenueRecord $venue,RequestId $id):JsonApiResponse{return new JsonApiResponse(200,['data'=>$this->data($venue),'request_id'=>$id->value],$this->headers($id,$venue));}
    public function outcome(IdempotencyOutcome $outcome,RequestId $id):JsonApiResponse{$venue=$outcome->response instanceof VenueRecord?$outcome->response:null;$data=$venue?$this->data($venue):['type'=>$outcome->reference->entityType,'id'=>$outcome->reference->entityId];$headers=$this->headers($id,$venue);$headers['Location']='/wp-json/eventflow/v1/venues/'.$outcome->reference->entityId;return new JsonApiResponse($outcome->reference->responseStatusCode,['data'=>$data,'meta'=>['replayed'=>$outcome->replayed],'request_id'=>$id->value],$headers);}
    private function data(VenueRecord $venue):array{return ['id'=>$venue->venueId,...$venue->attributes->all(),'revision'=>$venue->revision];}
    private function headers(RequestId $id,?VenueRecord $venue=null):array{$headers=['X-Request-ID'=>$id->value,'Cache-Control'=>'no-store, max-age=0'];if($venue)$headers['ETag']='"'.$venue->revision.'"';return $headers;}
}
