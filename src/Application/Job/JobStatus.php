<?php

namespace EventFlow\Application\Job;

enum JobStatus: string
{
    case PENDING = 'pending';
    case RUNNING = 'running';
    case COMPLETED = 'completed';
    case FAILED = 'failed';
    case DEAD_LETTER = 'dead_letter';
}
