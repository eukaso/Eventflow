<?php
namespace EventFlow\Application\Communication;
final readonly class CampaignPage{/** @param list<CampaignRecord> $campaigns */public function __construct(public array $campaigns,public ?int $nextAfterCampaignId){}}
