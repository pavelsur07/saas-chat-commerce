<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20250830100000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create CRM stages table';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE "crm_stages" (id UUID NOT NULL, pipeline_id UUID NOT NULL, name VARCHAR(80) NOT NULL, position INT NOT NULL, color VARCHAR(7) DEFAULT ''#CBD5E1'' NOT NULL, probability SMALLINT DEFAULT 0 NOT NULL, is_start BOOLEAN DEFAULT FALSE NOT NULL, is_won BOOLEAN DEFAULT FALSE NOT NULL, is_lost BOOLEAN DEFAULT FALSE NOT NULL, sla_hours INT DEFAULT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_CRM_STAGES_PIPELINE_ID ON "crm_stages" (pipeline_id)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_CRM_STAGES_PIPELINE_POSITION ON "crm_stages" (pipeline_id, position)');
        $this->addSql('COMMENT ON COLUMN "crm_stages".created_at IS ''(DC2Type:datetime_immutable)''');
        $this->addSql('COMMENT ON COLUMN "crm_stages".updated_at IS ''(DC2Type:datetime_immutable)''');
        $this->addSql('ALTER TABLE "crm_stages" ADD CONSTRAINT FK_CRM_STAGES_PIPELINE FOREIGN KEY (pipeline_id) REFERENCES "crm_pipelines" (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE "crm_stages" DROP CONSTRAINT FK_CRM_STAGES_PIPELINE');
        $this->addSql('DROP TABLE "crm_stages"');
    }
}
