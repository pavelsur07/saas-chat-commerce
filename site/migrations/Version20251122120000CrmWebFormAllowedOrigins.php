<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20251122120000CrmWebFormAllowedOrigins extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add allowed_origins column to CRM web forms for CORS allow-list.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("ALTER TABLE \"crm_web_forms\" ADD allowed_origins JSONB DEFAULT '[]' NOT NULL");
        $this->addSql("COMMENT ON COLUMN \"crm_web_forms\".allowed_origins IS '(DC2Type:jsonb)'");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE "crm_web_forms" DROP COLUMN allowed_origins');
    }
}

