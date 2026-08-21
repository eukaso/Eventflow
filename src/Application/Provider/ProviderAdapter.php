<?php
namespace EventFlow\Application\Provider;
interface ProviderAdapter{public function name():string;public function capabilities():ProviderCapabilities;public function send(ProviderDispatchMessage $message):ProviderSendResult;/** @param array<string,string> $headers @param array<string,string> $context Authenticates before returning normalized, bounded data. */public function authenticateAndNormalize(array $headers,string $rawBody,array $context=[]):NormalizedProviderWebhook;}
