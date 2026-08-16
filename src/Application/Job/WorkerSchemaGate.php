<?php

namespace EventFlow\Application\Job;

interface WorkerSchemaGate
{
    public function assertCompatible(): void;
}
