<?php
namespace EventFlow\Infrastructure\Provider;
final readonly class ProviderAdapterFactory
{
    public function __construct(private ProviderHttpClient $http){}
    public function fromConstants():ProviderRuntimeConfiguration
    {
        $enabled=defined('EVENTFLOW_PROVIDER_BULK_ENABLED')&&EVENTFLOW_PROVIDER_BULK_ENABLED===true;$adapters=[];$issues=[];
        $brevo=$this->values(['EVENTFLOW_BREVO_API_KEY','EVENTFLOW_BREVO_SENDER_EMAIL','EVENTFLOW_BREVO_SENDER_NAME','EVENTFLOW_BREVO_WEBHOOK_TOKEN']);
        if($brevo!==null&&filter_var($brevo[1],FILTER_VALIDATE_EMAIL)!==false&&strlen($brevo[0])>=16&&strlen($brevo[3])>=16)$adapters[]=new BrevoEmailProviderAdapter($this->http,...$brevo);else $issues[]=$brevo===null?'brevo_not_configured':'brevo_configuration_invalid';
        $twilio=$this->values(['EVENTFLOW_TWILIO_ACCOUNT_SID','EVENTFLOW_TWILIO_AUTH_TOKEN','EVENTFLOW_TWILIO_MESSAGING_SERVICE_SID','EVENTFLOW_TWILIO_WEBHOOK_URL']);
        if($twilio!==null&&preg_match('/^AC[a-fA-F0-9]{32}$/',$twilio[0])&&preg_match('/^MG[a-fA-F0-9]{32}$/',$twilio[2])&&strlen($twilio[1])>=16&&filter_var($twilio[3],FILTER_VALIDATE_URL)!==false&&str_starts_with($twilio[3],'https://'))$adapters[]=new TwilioSmsProviderAdapter($this->http,...$twilio);else $issues[]=$twilio===null?'twilio_not_configured':'twilio_configuration_invalid';
        if(!$enabled)$issues[]='provider_dispatch_disabled';
        return new ProviderRuntimeConfiguration($enabled,$adapters,array_values(array_unique($issues)));
    }
    /** @param list<string> $names @return list<string>|null */
    private function values(array $names):?array{$out=[];foreach($names as $name){if(!defined($name)||!is_string(constant($name))||trim((string)constant($name))==='')return null;$out[]=trim((string)constant($name));}return$out;}
}
