<?php

namespace EventFlow\Application\Seating;

enum RecommendationStatus: string
{
    case DRAFT = 'draft';
    case APPLIED = 'applied';
}
