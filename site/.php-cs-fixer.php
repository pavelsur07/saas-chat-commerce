<?php

declare(strict_types=1);
use PhpCsFixer\Config;
use PhpCsFixer\Finder;

return
    (new Config())
        ->setCacheFile(__DIR__.'/var/cache/.php_cs')
        ->setFinder(
            Finder::create()
                ->in([
                    __DIR__.'/src',
                    __DIR__.'/tests',
                ])
                ->append([
                    __FILE__,
                ])
        )
        ->setRules([
            '@Symfony' => true,
        ]);
