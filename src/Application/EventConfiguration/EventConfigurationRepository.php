<?php

namespace EventFlow\Application\EventConfiguration;

use DateTimeImmutable;
use EventFlow\Application\Persistence\EventScope;

interface EventConfigurationRepository
{
    public function find(EventScope $scope): ?EventConfigurationRecord;
    public function lock(EventScope $scope): ?EventConfigurationRecord;
    public function update(EventConfigurationRecord $current, EventConfigurationAttributes $replacement, int $actorUserId, DateTimeImmutable $now): EventConfigurationRecord;
}
