<?php
namespace EventFlow\Presentation\Api;
use EventFlow\Application\EventConfiguration\EventConfigurationOperations;
final readonly class EventConfigurationController
{
    public function __construct(private EventConfigurationOperations $configurations,private AuthenticatedRequestContextFactory $contexts,private EventConfigurationRequestMapper $requests,private EventConfigurationPresenter $presenter){}
    public function read(RestRequest $request):ApiResponse{$context=$this->contexts->create($request,MutationPreconditionPolicy::NONE);return $this->presenter->resource($this->configurations->read($context->principal,$this->requests->scope($request)),$context->requestId);}
    public function update(RestRequest $request):ApiResponse{$context=$this->contexts->create($request,MutationPreconditionPolicy::IF_MATCH_AND_IDEMPOTENCY_KEY);return $this->presenter->outcome($this->configurations->update($context->principal,$this->requests->scope($request),$this->requests->patch($request,$context->requiredExpectedVersion()),$context->requiredIdempotencyKey()),$context->requestId);}
}
