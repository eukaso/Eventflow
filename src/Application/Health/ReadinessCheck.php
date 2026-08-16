<?php

namespace EventFlow\Application\Health;

interface ReadinessCheck
{
    /** Stable low-cardinality identifier; never an account, Event, URL, or provider response. */
    public function identifier(): string;

    public function impact(): CheckImpact;

    public function check(): ReadinessCheckResult;
}
