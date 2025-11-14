<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20251114151711 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Convert clients.raw_data column to JSONB for proper equality support.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("ALTER TABLE \"clients\" ALTER COLUMN raw_data TYPE JSONB USING raw_data::jsonb");
        $this->addSql("COMMENT ON COLUMN \"clients\".raw_data IS '(DC2Type:jsonb)'");
    }

    public function down(Schema $schema): void
    {
        $this->addSql("ALTER TABLE \"clients\" ALTER COLUMN raw_data TYPE JSON USING raw_data::json");
        $this->addSql("COMMENT ON COLUMN \"clients\".raw_data IS NULL");
    }
}
