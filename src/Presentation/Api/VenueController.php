<?php
namespace EventFlow\Presentation\Api;
use EventFlow\Application\Venue\VenueOperations;
final readonly class VenueController
{
    public function __construct(private VenueOperations $venues,private AuthenticatedRequestContextFactory $contexts,private VenueRequestMapper $requests,private VenuePresenter $presenter){}
    public function list(RestRequest $request):ApiResponse{$context=$this->contexts->create($request,MutationPreconditionPolicy::NONE);[$limit,$after]=$this->requests->page($request);return $this->presenter->page($this->venues->list($context->principal,$limit,$after),$context->requestId);}
    public function read(RestRequest $request):ApiResponse{$context=$this->contexts->create($request,MutationPreconditionPolicy::NONE);return $this->presenter->resource($this->venues->read($context->principal,$this->requests->venueId($request)),$context->requestId);}
    public function create(RestRequest $request):ApiResponse{$context=$this->contexts->create($request,MutationPreconditionPolicy::IDEMPOTENCY_KEY);return $this->presenter->outcome($this->venues->create($context->principal,$this->requests->create($request),$context->requiredIdempotencyKey()),$context->requestId);}
    public function update(RestRequest $request):ApiResponse{$context=$this->contexts->create($request,MutationPreconditionPolicy::IF_MATCH_AND_IDEMPOTENCY_KEY);return $this->presenter->outcome($this->venues->update($context->principal,$this->requests->venueId($request),$this->requests->patch($request,$context->requiredExpectedVersion()),$context->requiredIdempotencyKey()),$context->requestId);}
}
