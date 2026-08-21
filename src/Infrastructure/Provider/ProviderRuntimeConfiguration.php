<?php
namespace EventFlow\Infrastructure\Provider;
use EventFlow\Application\Provider\{ProviderAdapter,ProviderDispatchGate,ProviderException,ProviderRegistry};
final readonly class ProviderRuntimeConfiguration implements ProviderDispatchGate
{
    /** @param list<ProviderAdapter> $adapters @param list<string> $issues */
    public function __construct(public bool $bulkEnabled,public array $adapters,public array $issues){}
    public function registry():ProviderRegistry{return new ProviderRegistry(...$this->adapters);}
    public function assertEnabled(string $provider):void{if(!$this->bulkEnabled)throw new ProviderException('provider_dispatch_disabled');$this->registry()->require($provider);}
    public function isReady():bool{return $this->bulkEnabled&&$this->issues===[]&&$this->adapters!==[];}
}
