<?php

namespace EventFlow\Application\Import;

enum ImportStatus: string
{
    case UPLOADED = 'uploaded';
    case STAGED = 'staged';
    case VALIDATED = 'validated';
    case APPLYING = 'applying';
    case COMPLETED = 'completed';
    case FAILED = 'failed';
    case CANCELLED = 'cancelled';
}
