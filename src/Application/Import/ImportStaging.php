<?php
namespace EventFlow\Application\Import;
use EventFlow\Application\Authorization\PrincipalContext;use EventFlow\Application\Idempotency\IdempotencyOutcome;use EventFlow\Application\Persistence\EventScope;
interface ImportStaging{public function stage(PrincipalContext$principal,EventScope$scope,string$path,string$idempotencyKey,?string$sourceFilename=null):IdempotencyOutcome;}
