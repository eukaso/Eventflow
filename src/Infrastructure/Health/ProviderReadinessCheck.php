<?php
namespace EventFlow\Infrastructure\Health;
use EventFlow\Application\Health\{CheckImpact,HealthCode,ReadinessCheck,ReadinessCheckResult};use EventFlow\Infrastructure\Provider\ProviderRuntimeConfiguration;
final readonly class ProviderReadinessCheck implements ReadinessCheck
{
    public function __construct(private ProviderRuntimeConfiguration $configuration){}
    public function identifier():string{return'provider_delivery';}public function impact():CheckImpact{return CheckImpact::OPTIONAL_CAPABILITY;}
    public function check():ReadinessCheckResult{return $this->configuration->isReady()?ReadinessCheckResult::up($this->identifier(),$this->impact()):ReadinessCheckResult::degraded($this->identifier(),$this->impact(),HealthCode::PROVIDER_UNAVAILABLE);}
}
