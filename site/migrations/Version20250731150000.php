<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20250731150000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add username and first_name fields to telegram_bots';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE "telegram_bots" ADD username VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE "telegram_bots" ADD first_name VARCHAR(255) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE "telegram_bots" DROP username');
        $this->addSql('ALTER TABLE "telegram_bots" DROP first_name');
    }
}

