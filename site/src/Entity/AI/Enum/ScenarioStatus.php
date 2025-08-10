<?php

namespace App\Entity\AI\Enum;

enum ScenarioStatus: string
{
    case DRAFT = 'draft';
    case PUBLISHED = 'published';
    case ARCHIVED = 'archived';
}
