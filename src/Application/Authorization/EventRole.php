<?php

namespace EventFlow\Application\Authorization;

enum EventRole: string
{
    case OWNER = 'owner';
    case ORGANIZER = 'organizer';
    case COORDINATOR = 'coordinator';
    case RECEPTION = 'reception';
    case REPORTING = 'reporting';
}
