<?php
namespace EventFlow\Presentation\Api;
use EventFlow\Application\Import\{ImportStaging,ImportUploadGuard};
final readonly class ImportUploadController
{public function __construct(private ImportStaging$imports,private ImportUploadGuard$uploads,private AuthenticatedRequestContextFactory$contexts,private ImportAdministrationRequestMapper$requests,private ImportAdministrationPresenter$presenter){}public function create(RestRequest$r):ApiResponse{$c=$this->contexts->create($r,MutationPreconditionPolicy::IDEMPOTENCY_KEY);$file=$r->file('source')??throw new RequestInputException('validation_failed');$scope=$this->requests->scope($r);$outcome=$this->uploads->withTrustedUpload($file,fn(string$path,string$name)=>$this->imports->stage($c->principal,$scope,$path,$c->requiredIdempotencyKey(),$name));return$this->presenter->creation($outcome,$scope->eventId,$c->requestId);}}
