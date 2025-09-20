<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20250830090000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create CRM pipelines table';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE "crm_pipelines" (id UUID NOT NULL, company_id UUID NOT NULL, name VARCHAR(120) NOT NULL, slug VARCHAR(140) NOT NULL, is_default BOOLEAN DEFAULT FALSE NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_2E32ED83979B1AD6 ON "crm_pipelines" (company_id)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_2E32ED83979B1AD6989D9B62 ON "crm_pipelines" (company_id, slug)');
        $this->addSql('COMMENT ON COLUMN "crm_pipelines".created_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN "crm_pipelines".updated_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('ALTER TABLE "crm_pipelines" ADD CONSTRAINT FK_2E32ED83979B1AD6 FOREIGN KEY (company_id) REFERENCES "companies" (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE "crm_pipelines" DROP CONSTRAINT FK_2E32ED83979B1AD6');
        $this->addSql('DROP TABLE "crm_pipelines"');
    }
}
