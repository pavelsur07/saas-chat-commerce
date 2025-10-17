<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20250901090000WebChatSite extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create web_chat_sites table to store widget site configurations.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE "web_chat_sites" (id UUID NOT NULL, company_id UUID NOT NULL, name VARCHAR(120) NOT NULL, site_key VARCHAR(64) NOT NULL, allowed_origins JSONB DEFAULT \'[]\'::jsonb NOT NULL, is_active BOOLEAN DEFAULT TRUE NOT NULL, created_at TIMESTAMP(0) WITH TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITH TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE UNIQUE INDEX uniq_web_chat_sites_site_key ON "web_chat_sites" (site_key)');
        $this->addSql('CREATE INDEX idx_web_chat_sites_company_active ON "web_chat_sites" (company_id, is_active)');
        $this->addSql('ALTER TABLE "web_chat_sites" ADD CONSTRAINT fk_web_chat_sites_company FOREIGN KEY (company_id) REFERENCES "companies" (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE "web_chat_sites" DROP CONSTRAINT IF EXISTS fk_web_chat_sites_company');
        $this->addSql('DROP INDEX IF EXISTS uniq_web_chat_sites_site_key');
        $this->addSql('DROP INDEX IF EXISTS idx_web_chat_sites_company_active');
        $this->addSql('DROP TABLE IF EXISTS "web_chat_sites"');
    }
}
