<?php
namespace EventFlow\Application\CheckIn;
final readonly class BulkCheckInResult { /** @param list<CheckInAction> $actions */ public function __construct(public string $operationId, public array $actions) {} }
