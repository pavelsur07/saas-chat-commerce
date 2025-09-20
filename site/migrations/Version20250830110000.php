<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20250830110000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create CRM deals table';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE "crm_deals" (id UUID NOT NULL, company_id UUID NOT NULL, pipeline_id UUID NOT NULL, stage_id UUID NOT NULL, client_id UUID DEFAULT NULL, owner_id UUID DEFAULT NULL, created_by_id UUID NOT NULL, title VARCHAR(160) NOT NULL, amount NUMERIC(14, 2) DEFAULT NULL, currency CHAR(3) DEFAULT \'RUB\' NOT NULL, source VARCHAR(40) DEFAULT NULL, meta JSONB DEFAULT \'{}\'::jsonb NOT NULL, opened_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, closed_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, is_closed BOOLEAN DEFAULT FALSE NOT NULL, loss_reason VARCHAR(120) DEFAULT NULL, note TEXT DEFAULT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_CRM_DEALS_COMPANY_PIPELINE ON "crm_deals" (company_id, pipeline_id)');
        $this->addSql('CREATE INDEX IDX_CRM_DEALS_STAGE ON "crm_deals" (stage_id)');
        $this->addSql('COMMENT ON COLUMN "crm_deals".opened_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN "crm_deals".closed_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN "crm_deals".created_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN "crm_deals".updated_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('ALTER TABLE "crm_deals" ADD CONSTRAINT FK_CRM_DEALS_COMPANY FOREIGN KEY (company_id) REFERENCES "companies" (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE "crm_deals" ADD CONSTRAINT FK_CRM_DEALS_PIPELINE FOREIGN KEY (pipeline_id) REFERENCES "crm_pipelines" (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE "crm_deals" ADD CONSTRAINT FK_CRM_DEALS_STAGE FOREIGN KEY (stage_id) REFERENCES "crm_stages" (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE "crm_deals" ADD CONSTRAINT FK_CRM_DEALS_CLIENT FOREIGN KEY (client_id) REFERENCES "clients" (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE "crm_deals" ADD CONSTRAINT FK_CRM_DEALS_OWNER FOREIGN KEY (owner_id) REFERENCES "user" (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE "crm_deals" ADD CONSTRAINT FK_CRM_DEALS_CREATED_BY FOREIGN KEY (created_by_id) REFERENCES "user" (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE "crm_deals" DROP CONSTRAINT FK_CRM_DEALS_COMPANY');
        $this->addSql('ALTER TABLE "crm_deals" DROP CONSTRAINT FK_CRM_DEALS_PIPELINE');
        $this->addSql('ALTER TABLE "crm_deals" DROP CONSTRAINT FK_CRM_DEALS_STAGE');
        $this->addSql('ALTER TABLE "crm_deals" DROP CONSTRAINT FK_CRM_DEALS_CLIENT');
        $this->addSql('ALTER TABLE "crm_deals" DROP CONSTRAINT FK_CRM_DEALS_OWNER');
        $this->addSql('ALTER TABLE "crm_deals" DROP CONSTRAINT FK_CRM_DEALS_CREATED_BY');
        $this->addSql('DROP TABLE "crm_deals"');
    }
}
