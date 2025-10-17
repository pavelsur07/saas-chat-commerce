<?php

namespace App\Service\WebChat;

use App\Repository\WebChat\WebChatSiteRepository;

final class WebChatSiteKeyGenerator
{
    public function __construct(private WebChatSiteRepository $repository)
    {
    }

    public function generate(): string
    {
        do {
            $key = rtrim(strtr(base64_encode(random_bytes(18)), '+/', '-_'), '=');
        } while ($this->repository->siteKeyExists($key));

        return $key;
    }
}
