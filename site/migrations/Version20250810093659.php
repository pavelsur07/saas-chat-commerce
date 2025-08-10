<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250810093659 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE INDEX idx_ai_faq_company_active ON ai_faq (company_id, is_active)');
        $this->addSql('CREATE INDEX idx_ai_faq_company_lang ON ai_faq (company_id, language)');
        $this->addSql('CREATE INDEX idx_ai_prompt_log_company_created_at ON ai_prompt_log (company_id, created_at)');
        $this->addSql('CREATE INDEX idx_ai_prompt_log_company_model_created ON ai_prompt_log (company_id, model, created_at)');
        $this->addSql('CREATE INDEX idx_ai_prompt_log_company_status_created ON ai_prompt_log (company_id, status, created_at)');
        $this->addSql('CREATE INDEX idx_ai_scenario_company_slug_status ON ai_scenario (company_id, slug, status)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE SCHEMA public');
        $this->addSql('DROP INDEX idx_ai_faq_company_active');
        $this->addSql('DROP INDEX idx_ai_faq_company_lang');
        $this->addSql('DROP INDEX idx_ai_prompt_log_company_created_at');
        $this->addSql('DROP INDEX idx_ai_prompt_log_company_model_created');
        $this->addSql('DROP INDEX idx_ai_prompt_log_company_status_created');
        $this->addSql('DROP INDEX idx_ai_scenario_company_slug_status');
    }
}
