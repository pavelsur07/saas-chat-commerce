<?php

declare(strict_types=1);

namespace App\Tests\Integration\DB\Knowledge\Traits;

use Symfony\Component\Process\Process;

trait RunsMigrationsAndSeedsTestDB
{
    private static bool $migrationsDone = false;

    public static function runMigrationsOnce(): void
    {
        if (self::$migrationsDone) {
            return;
        }

        // 1) doctrine:migrations:migrate для test-окружения
        $migrate = new Process(['php', 'bin/console', 'doctrine:migrations:migrate', '-n', '--env=test']);
        $migrate->setTimeout(300);
        $migrate->run();
        if (!$migrate->isSuccessful()) {
            throw new \RuntimeException("Migrations failed:\n".$migrate->getErrorOutput().$migrate->getOutput());
        }

        // 2) (опционально) попытаться загрузить фикстуры, если есть команда
        $fixtures = new Process(['php', 'bin/console', 'doctrine:fixtures:load', '-n', '--env=test', '--append']);
        $fixtures->setTimeout(300);
        $fixtures->run(); // игнорируем ошибку — проект может быть без DoctrineFixturesBundle

        self::$migrationsDone = true;
    }
}
