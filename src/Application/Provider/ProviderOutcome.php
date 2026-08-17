<?php
namespace EventFlow\Application\Provider;
enum ProviderOutcome:string{case ACCEPTED='accepted';case DEFINITIVE_FAILURE='definitive_failure';case AMBIGUOUS='ambiguous';}
