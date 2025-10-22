<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20250905120000OwnerDefaults extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add is_default flag for user_companies and mark owners as default.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE "user_companies" ADD is_default BOOLEAN DEFAULT FALSE NOT NULL');
        $this->addSql("UPDATE \"user_companies\" SET is_default = TRUE WHERE role = 'OWNER'");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE "user_companies" DROP COLUMN is_default');
    }
}
