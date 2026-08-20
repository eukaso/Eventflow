<?php

namespace EventFlow\Presentation\Api;

use EventFlow\Application\Privacy\PrivacyAccess;
use EventFlow\Application\Privacy\PrivacyCommands;

final readonly class PrivacyController
{
    public function __construct(private PrivacyCommands$commands,private PrivacyAccess$access,private AuthenticatedRequestContextFactory$contexts,private PrivacyRequestMapper$requests,private PrivacyPresenter$presenter){}
    public function listActions(RestRequest$r):ApiResponse{$c=$this->contexts->create($r,MutationPreconditionPolicy::NONE);[$l,$a,$status,$kind,$invitation]=$this->requests->actionPage($r);return$this->presenter->actionPage($this->access->listActions($c->principal,$this->requests->scope($r),$l,$a,$status,$kind,$invitation),$c->requestId);}
    public function readAction(RestRequest$r):ApiResponse{$c=$this->contexts->create($r,MutationPreconditionPolicy::NONE);return$this->presenter->actionResource($this->access->readAction($c->principal,$this->requests->scope($r),$this->requests->actionId($r)),$c->requestId);}
    public function createAction(RestRequest$r):ApiResponse{$c=$this->contexts->create($r,MutationPreconditionPolicy::IDEMPOTENCY_KEY);[$invitation,$policy,$purpose]=$this->requests->actionCreation($r);$scope=$this->requests->scope($r);return$this->presenter->actionOutcome($this->commands->request($c->principal,$scope,$invitation,$policy,$purpose,$c->requiredIdempotencyKey()),$scope->eventId,$c->requestId);}
    public function listHolds(RestRequest$r):ApiResponse{$c=$this->contexts->create($r,MutationPreconditionPolicy::NONE);[$l,$a,$status,$invitation]=$this->requests->holdPage($r);return$this->presenter->holdPage($this->access->listHolds($c->principal,$this->requests->scope($r),$l,$a,$status,$invitation),$c->requestId);}
    public function readHold(RestRequest$r):ApiResponse{$c=$this->contexts->create($r,MutationPreconditionPolicy::NONE);return$this->presenter->holdResource($this->access->readHold($c->principal,$this->requests->scope($r),$this->requests->holdId($r)),$c->requestId);}
    public function createHold(RestRequest$r):ApiResponse{$c=$this->contexts->create($r,MutationPreconditionPolicy::IDEMPOTENCY_KEY);[$invitation,$policy,$reason]=$this->requests->holdCreation($r);$scope=$this->requests->scope($r);return$this->presenter->holdOutcome($this->commands->placeHold($c->principal,$scope,$invitation,$policy,$reason,$c->requiredIdempotencyKey()),$scope->eventId,$c->requestId);}
    public function releaseHold(RestRequest$r):ApiResponse{$c=$this->contexts->create($r,MutationPreconditionPolicy::IDEMPOTENCY_KEY);$this->requests->requireEmptyBody($r);$scope=$this->requests->scope($r);return$this->presenter->holdOutcome($this->commands->releaseHold($c->principal,$scope,$this->requests->holdId($r),$c->requiredIdempotencyKey()),$scope->eventId,$c->requestId);}
}
