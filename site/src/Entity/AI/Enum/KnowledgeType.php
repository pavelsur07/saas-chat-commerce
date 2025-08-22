<?php
namespace App\Entity\AI\Enum;

enum KnowledgeType: string
{
    case FAQ = 'faq';
    case DELIVERY = 'delivery';
    case PRODUCT = 'product';
    case POLICY = 'policy';
}
