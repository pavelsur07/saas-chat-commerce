<?php

declare(strict_types=1);

namespace App\Channel\Service;

class GatewayRegistry
{
    /**
     * @var ChannelProviderInterface[]
     */
    private array $providers;

    /**
     * @param iterable<ChannelProviderInterface> $providers
     */
    public function __construct(iterable $providers)
    {
        $this->providers = is_array($providers) ? $providers : iterator_to_array($providers);
    }

    public function getProvider(string $type): ?ChannelProviderInterface
    {
        foreach ($this->providers as $provider) {
            if ($provider->supports($type)) {
                return $provider;
            }
        }

        return null;
    }
}
