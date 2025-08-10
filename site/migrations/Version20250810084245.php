<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250810084245 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE ai_faq (id UUID NOT NULL, company_id UUID NOT NULL, created_by_id UUID DEFAULT NULL, updated_by_id UUID DEFAULT NULL, question TEXT NOT NULL, answer TEXT NOT NULL, language VARCHAR(10) NOT NULL, source VARCHAR(255) NOT NULL, tags JSONB DEFAULT NULL, is_active BOOLEAN DEFAULT true NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_6298DB4A979B1AD6 ON ai_faq (company_id)');
        $this->addSql('CREATE INDEX IDX_6298DB4AB03A8386 ON ai_faq (created_by_id)');
        $this->addSql('CREATE INDEX IDX_6298DB4A896DBBDE ON ai_faq (updated_by_id)');
        $this->addSql('COMMENT ON COLUMN ai_faq.language IS \'ru,en,...\'');
        $this->addSql('COMMENT ON COLUMN ai_faq.tags IS \'(DC2Type:jsonb)\'');
        $this->addSql('COMMENT ON COLUMN ai_faq.created_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN ai_faq.updated_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('CREATE TABLE ai_prompt_log (id UUID NOT NULL, company_id UUID NOT NULL, user_id UUID DEFAULT NULL, channel VARCHAR(32) NOT NULL, model VARCHAR(64) NOT NULL, prompt TEXT NOT NULL, prompt_tokens INT DEFAULT 0 NOT NULL, response TEXT DEFAULT NULL, completion_tokens INT DEFAULT 0 NOT NULL, total_tokens INT DEFAULT 0 NOT NULL, latency_ms INT DEFAULT 0 NOT NULL, status VARCHAR(255) NOT NULL, error_message VARCHAR(255) DEFAULT NULL, cost_usd NUMERIC(10, 5) DEFAULT NULL, metadata JSONB DEFAULT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_AD7A2E36979B1AD6 ON ai_prompt_log (company_id)');
        $this->addSql('CREATE INDEX IDX_AD7A2E36A76ED395 ON ai_prompt_log (user_id)');
        $this->addSql('COMMENT ON COLUMN ai_prompt_log.latency_ms IS \'латентность, мс\'');
        $this->addSql('COMMENT ON COLUMN ai_prompt_log.metadata IS \'(DC2Type:jsonb)\'');
        $this->addSql('COMMENT ON COLUMN ai_prompt_log.created_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN ai_prompt_log.updated_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('CREATE TABLE ai_scenario (id UUID NOT NULL, company_id UUID NOT NULL, created_by_id UUID DEFAULT NULL, updated_by_id UUID DEFAULT NULL, name VARCHAR(160) NOT NULL, slug VARCHAR(160) NOT NULL, version INT DEFAULT 1 NOT NULL, status VARCHAR(255) NOT NULL, graph JSONB NOT NULL, published_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, notes TEXT DEFAULT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_21A34EB8979B1AD6 ON ai_scenario (company_id)');
        $this->addSql('CREATE INDEX IDX_21A34EB8B03A8386 ON ai_scenario (created_by_id)');
        $this->addSql('CREATE INDEX IDX_21A34EB8896DBBDE ON ai_scenario (updated_by_id)');
        $this->addSql('CREATE UNIQUE INDEX uniq_company_slug_version ON ai_scenario (company_id, slug, version)');
        $this->addSql('COMMENT ON COLUMN ai_scenario.graph IS \'(DC2Type:jsonb)\'');
        $this->addSql('COMMENT ON COLUMN ai_scenario.published_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN ai_scenario.created_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN ai_scenario.updated_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('ALTER TABLE ai_faq ADD CONSTRAINT FK_6298DB4A979B1AD6 FOREIGN KEY (company_id) REFERENCES "companies" (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE ai_faq ADD CONSTRAINT FK_6298DB4AB03A8386 FOREIGN KEY (created_by_id) REFERENCES "user" (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE ai_faq ADD CONSTRAINT FK_6298DB4A896DBBDE FOREIGN KEY (updated_by_id) REFERENCES "user" (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE ai_prompt_log ADD CONSTRAINT FK_AD7A2E36979B1AD6 FOREIGN KEY (company_id) REFERENCES "companies" (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE ai_prompt_log ADD CONSTRAINT FK_AD7A2E36A76ED395 FOREIGN KEY (user_id) REFERENCES "user" (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE ai_scenario ADD CONSTRAINT FK_21A34EB8979B1AD6 FOREIGN KEY (company_id) REFERENCES "companies" (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE ai_scenario ADD CONSTRAINT FK_21A34EB8B03A8386 FOREIGN KEY (created_by_id) REFERENCES "user" (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE ai_scenario ADD CONSTRAINT FK_21A34EB8896DBBDE FOREIGN KEY (updated_by_id) REFERENCES "user" (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE SCHEMA public');
        $this->addSql('ALTER TABLE ai_faq DROP CONSTRAINT FK_6298DB4A979B1AD6');
        $this->addSql('ALTER TABLE ai_faq DROP CONSTRAINT FK_6298DB4AB03A8386');
        $this->addSql('ALTER TABLE ai_faq DROP CONSTRAINT FK_6298DB4A896DBBDE');
        $this->addSql('ALTER TABLE ai_prompt_log DROP CONSTRAINT FK_AD7A2E36979B1AD6');
        $this->addSql('ALTER TABLE ai_prompt_log DROP CONSTRAINT FK_AD7A2E36A76ED395');
        $this->addSql('ALTER TABLE ai_scenario DROP CONSTRAINT FK_21A34EB8979B1AD6');
        $this->addSql('ALTER TABLE ai_scenario DROP CONSTRAINT FK_21A34EB8B03A8386');
        $this->addSql('ALTER TABLE ai_scenario DROP CONSTRAINT FK_21A34EB8896DBBDE');
        $this->addSql('DROP TABLE ai_faq');
        $this->addSql('DROP TABLE ai_prompt_log');
        $this->addSql('DROP TABLE ai_scenario');
    }
}
