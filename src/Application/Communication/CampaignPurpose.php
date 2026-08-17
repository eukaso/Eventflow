<?php

namespace EventFlow\Application\Communication;

enum CampaignPurpose: string
{
    case INVITATION = 'invitation';
    case REMINDER = 'reminder';
    case EVENT_UPDATE = 'event_update';
    case OPERATIONAL = 'operational';
}
