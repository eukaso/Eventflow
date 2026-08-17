<?php

namespace EventFlow\Application\Communication;

enum CommunicationChannel: string
{
    case EMAIL = 'email';
    case SMS = 'sms';
}
