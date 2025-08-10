<?php

namespace App\Entity\AI\Enum;

enum PromptStatus: string
{
    case OK = 'ok';
    case ERROR = 'error';
    case TIMEOUT = 'timeout';
    case RATE_LIMITED = 'rate_limited';
}
