<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20251123100000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add platform admin users table.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE platform_user (id UUID NOT NULL, email VARCHAR(180) NOT NULL, roles JSON NOT NULL, password VARCHAR(255) NOT NULL, is_active BOOLEAN DEFAULT TRUE NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_PLATFORM_ADMIN_EMAIL ON platform_user (email)');
        $this->addSql('COMMENT ON COLUMN platform_user.created_at IS \"(DC2Type:datetime_immutable)\"');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE platform_user');
    }
}
