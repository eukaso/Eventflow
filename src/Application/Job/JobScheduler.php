<?php

namespace EventFlow\Application\Job;

interface JobScheduler
{
    /** Best-effort wake-up only; the durable job table remains authoritative. */
    public function trigger(): void;
}
