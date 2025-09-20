<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20250830120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create CRM stage history table';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE "crm_stage_history" (id UUID NOT NULL, deal_id UUID NOT NULL, from_stage_id UUID DEFAULT NULL, to_stage_id UUID NOT NULL, changed_by_id UUID NOT NULL, changed_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, comment VARCHAR(240) DEFAULT NULL, spent_hours INT DEFAULT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_CRM_STAGE_HISTORY_DEAL ON "crm_stage_history" (deal_id)');
        $this->addSql('COMMENT ON COLUMN "crm_stage_history".changed_at IS ''(DC2Type:datetime_immutable)''');
        $this->addSql('ALTER TABLE "crm_stage_history" ADD CONSTRAINT FK_CRM_STAGE_HISTORY_DEAL FOREIGN KEY (deal_id) REFERENCES "crm_deals" (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE "crm_stage_history" ADD CONSTRAINT FK_CRM_STAGE_HISTORY_FROM_STAGE FOREIGN KEY (from_stage_id) REFERENCES "crm_stages" (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE "crm_stage_history" ADD CONSTRAINT FK_CRM_STAGE_HISTORY_TO_STAGE FOREIGN KEY (to_stage_id) REFERENCES "crm_stages" (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE "crm_stage_history" ADD CONSTRAINT FK_CRM_STAGE_HISTORY_CHANGED_BY FOREIGN KEY (changed_by_id) REFERENCES "user" (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE "crm_stage_history" DROP CONSTRAINT FK_CRM_STAGE_HISTORY_DEAL');
        $this->addSql('ALTER TABLE "crm_stage_history" DROP CONSTRAINT FK_CRM_STAGE_HISTORY_FROM_STAGE');
        $this->addSql('ALTER TABLE "crm_stage_history" DROP CONSTRAINT FK_CRM_STAGE_HISTORY_TO_STAGE');
        $this->addSql('ALTER TABLE "crm_stage_history" DROP CONSTRAINT FK_CRM_STAGE_HISTORY_CHANGED_BY');
        $this->addSql('DROP TABLE "crm_stage_history"');
    }
}
