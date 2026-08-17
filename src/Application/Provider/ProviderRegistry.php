<?php
namespace EventFlow\Application\Provider;
final readonly class ProviderRegistry{private array $adapters;public function __construct(ProviderAdapter ...$adapters){$map=[];foreach($adapters as $adapter){if(isset($map[$adapter->name()]))throw new ProviderException('provider_duplicate');$map[$adapter->name()]=$adapter;}$this->adapters=$map;}public function require(string $name):ProviderAdapter{return $this->adapters[$name]??throw new ProviderException('provider_not_configured');}}
