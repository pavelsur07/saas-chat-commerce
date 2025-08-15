<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250815100103 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP INDEX idx_ai_faq_answer_trgm');
        $this->addSql('DROP INDEX idx_ai_faq_question_trgm');
        $this->addSql('DROP INDEX idx_ai_faq_tags_gin');
        $this->addSql('DROP INDEX idx_ai_prompt_log_created_brin');
        $this->addSql('DROP INDEX idx_ai_prompt_log_metadata_gin');
        $this->addSql('DROP INDEX idx_ai_prompt_log_only_errors');
        $this->addSql('ALTER TABLE messages ADD meta JSON DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE SCHEMA public');
        $this->addSql('CREATE INDEX idx_ai_faq_answer_trgm ON ai_faq (answer)');
        $this->addSql('CREATE INDEX idx_ai_faq_question_trgm ON ai_faq (question)');
        $this->addSql('CREATE INDEX idx_ai_faq_tags_gin ON ai_faq (tags)');
        $this->addSql('CREATE INDEX idx_ai_prompt_log_created_brin ON ai_prompt_log (created_at)');
        $this->addSql('CREATE INDEX idx_ai_prompt_log_metadata_gin ON ai_prompt_log (metadata)');
        $this->addSql('CREATE INDEX idx_ai_prompt_log_only_errors ON ai_prompt_log (company_id, created_at) WHERE ((status)::text <> \'ok\'::text)');
        $this->addSql('ALTER TABLE "messages" DROP meta');
    }
}
