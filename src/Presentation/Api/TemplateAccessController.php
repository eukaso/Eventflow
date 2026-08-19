<?php

namespace EventFlow\Presentation\Api;

use EventFlow\Application\Communication\TemplateAccess;

final readonly class TemplateAccessController
{
    public function __construct(private TemplateAccess $templates,private AuthenticatedRequestContextFactory $contexts,private TemplateAccessRequestMapper $requests,private TemplateAccessPresenter $presenter){}
    public function list(RestRequest $request):ApiResponse{$context=$this->contexts->create($request,MutationPreconditionPolicy::NONE);[$limit,$after]=$this->requests->page($request);return $this->presenter->page($this->templates->list($context->principal,$this->requests->scope($request),$limit,$after),$context->requestId);}
    public function read(RestRequest $request):ApiResponse{$context=$this->contexts->create($request,MutationPreconditionPolicy::NONE);return $this->presenter->resource($this->templates->read($context->principal,$this->requests->scope($request),$this->requests->templateId($request)),$context->requestId);}
    public function update(RestRequest $request):ApiResponse{$context=$this->contexts->create($request,MutationPreconditionPolicy::IF_MATCH_AND_IDEMPOTENCY_KEY);$scope=$this->requests->scope($request);$id=$this->requests->templateId($request);$current=$this->templates->read($context->principal,$scope,$id);$outcome=$this->templates->update($context->principal,$scope,$id,$this->requests->replacement($request,$current,$context->requiredExpectedVersion()),$context->requiredIdempotencyKey());return $this->presenter->outcome($outcome,$scope,$context->requestId);}
    public function newVersion(RestRequest $request):ApiResponse{return $this->transition($request,true);}
    public function archive(RestRequest $request):ApiResponse{return $this->transition($request,false);}
    public function preview(RestRequest $request):ApiResponse{$context=$this->contexts->create($request,MutationPreconditionPolicy::NONE);return $this->presenter->preview($this->templates->preview($context->principal,$this->requests->scope($request),$this->requests->templateId($request),$this->requests->previewValues($request)),$context->requestId);}
    private function transition(RestRequest $request,bool $version):ApiResponse{$context=$this->contexts->create($request,MutationPreconditionPolicy::IF_MATCH_AND_IDEMPOTENCY_KEY);$this->requests->requireEmptyBody($request);$scope=$this->requests->scope($request);$id=$this->requests->templateId($request);$outcome=$version?$this->templates->newVersion($context->principal,$scope,$id,$context->requiredExpectedVersion(),$context->requiredIdempotencyKey()):$this->templates->archive($context->principal,$scope,$id,$context->requiredExpectedVersion(),$context->requiredIdempotencyKey());return $this->presenter->outcome($outcome,$scope,$context->requestId);}
}
