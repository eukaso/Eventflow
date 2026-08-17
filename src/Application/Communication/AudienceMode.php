<?php

namespace EventFlow\Application\Communication;

enum AudienceMode: string
{
    case DYNAMIC = 'dynamic';
    case SNAPSHOT = 'snapshot';
}
