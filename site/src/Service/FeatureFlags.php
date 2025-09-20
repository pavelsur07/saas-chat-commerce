<?php

namespace App\Service;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

final class FeatureFlags
{
    public function __construct(
        #[Autowire(param: 'feature_flags.crm_enabled')]
        private readonly bool $crmEnabled,
    ) {
    }

    public function isCrmEnabled(): bool
    {
        return $this->crmEnabled;
    }

    public static function isCrmEnabledFromParameters(ParameterBagInterface $parameterBag): bool
    {
        return (bool) $parameterBag->get('feature_flags.crm_enabled');
    }
}
