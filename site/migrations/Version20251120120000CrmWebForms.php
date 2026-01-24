<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20251120120000CrmWebForms extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create CRM web forms storage.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("CREATE TABLE \"crm_web_forms\" (id UUID NOT NULL, company_id UUID NOT NULL, pipeline_id UUID NOT NULL, stage_id UUID NOT NULL, owner_id UUID DEFAULT NULL, name VARCHAR(160) NOT NULL, slug VARCHAR(140) NOT NULL, public_key VARCHAR(64) NOT NULL, description TEXT DEFAULT NULL, fields JSONB DEFAULT '[]' NOT NULL, success_type VARCHAR(20) NOT NULL, success_message TEXT DEFAULT NULL, success_redirect_url VARCHAR(255) DEFAULT NULL, tags JSONB DEFAULT '[]' NOT NULL, is_active BOOLEAN DEFAULT TRUE NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))");
        $this->addSql("CREATE INDEX idx_crm_web_forms_company ON \"crm_web_forms\" (company_id)");
        $this->addSql("CREATE UNIQUE INDEX crm_web_forms_company_slug_unique ON \"crm_web_forms\" (company_id, slug)");
        $this->addSql("COMMENT ON COLUMN \"crm_web_forms\".fields IS '(DC2Type:jsonb)'");
        $this->addSql("COMMENT ON COLUMN \"crm_web_forms\".tags IS '(DC2Type:jsonb)'");
        $this->addSql("ALTER TABLE \"crm_web_forms\" ADD CONSTRAINT FK_6A60EEE4979B1AD6 FOREIGN KEY (company_id) REFERENCES \"companies\" (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE");
        $this->addSql("ALTER TABLE \"crm_web_forms\" ADD CONSTRAINT FK_6A60EEE4D8C67102 FOREIGN KEY (pipeline_id) REFERENCES \"crm_pipelines\" (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE");
        $this->addSql("ALTER TABLE \"crm_web_forms\" ADD CONSTRAINT FK_6A60EEE429CCBAD0 FOREIGN KEY (stage_id) REFERENCES \"crm_stages\" (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE");
        $this->addSql("ALTER TABLE \"crm_web_forms\" ADD CONSTRAINT FK_6A60EEE47E3C61F9 FOREIGN KEY (owner_id) REFERENCES \"user\" (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE "crm_web_forms"');
    }
}
