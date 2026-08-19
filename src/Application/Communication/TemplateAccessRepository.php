<?php

namespace EventFlow\Application\Communication;

use DateTimeImmutable;
use EventFlow\Application\Persistence\EventScope;

interface TemplateAccessRepository
{
    public function listTemplates(EventScope $scope, int $limit, ?int $afterTemplateId): TemplatePage;
    public function findTemplate(EventScope $scope, int $templateId): ?TemplateRecord;
    public function lockTemplate(EventScope $scope, int $templateId): ?TemplateRecord;
    public function updateTemplate(EventScope $scope, TemplateRecord $current, TemplateReplacement $replacement, int $actorUserId, DateTimeImmutable $now): TemplateRecord;
    public function createTemplateVersion(EventScope $scope, TemplateRecord $source, int $actorUserId, DateTimeImmutable $now): TemplateRecord;
    public function archiveTemplate(EventScope $scope, TemplateRecord $current, int $actorUserId, DateTimeImmutable $now): TemplateRecord;
    public function templateHasMutableCampaigns(EventScope $scope, int $templateId): bool;
}
