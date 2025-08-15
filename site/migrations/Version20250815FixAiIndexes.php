<?php

declare(strict_types=1);

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20250815FixAiIndexes extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Restore AI performance indexes dropped by Version20250815100103';
    }

    // ВАЖНО: эта миграция без транзакции (нужно для CONCURRENTLY)
    public function isTransactional(): bool
    {
        return false;
    }

    public function up(Schema $schema): void
    {
        // safety: работаем только на PostgreSQL
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'postgresql', 'This migration only supports PostgreSQL.');

        // расширения можно в транзакции, но раз миграция нетранзакционная — тоже ок
        $this->addSql('CREATE EXTENSION IF NOT EXISTS pg_trgm');
        $this->addSql('CREATE EXTENSION IF NOT EXISTS btree_gin');

        // ai_faq
        $this->addSql('CREATE INDEX CONCURRENTLY IF NOT EXISTS idx_ai_faq_tags_gin ON ai_faq USING GIN (tags jsonb_path_ops)');
        $this->addSql('CREATE INDEX CONCURRENTLY IF NOT EXISTS idx_ai_faq_question_trgm ON ai_faq USING GIN (question gin_trgm_ops)');
        $this->addSql('CREATE INDEX CONCURRENTLY IF NOT EXISTS idx_ai_faq_answer_trgm ON ai_faq USING GIN (answer gin_trgm_ops)');

        // ai_prompt_log
        $this->addSql('CREATE INDEX CONCURRENTLY IF NOT EXISTS idx_ai_prompt_log_metadata_gin ON ai_prompt_log USING GIN (metadata jsonb_path_ops)');
        $this->addSql('CREATE INDEX CONCURRENTLY IF NOT EXISTS idx_ai_prompt_log_created_brin ON ai_prompt_log USING BRIN (created_at)');
        $this->addSql("CREATE INDEX CONCURRENTLY IF NOT EXISTS idx_ai_prompt_log_only_errors ON ai_prompt_log (company_id, created_at DESC) WHERE status <> 'ok'");
    }

    public function down(Schema $schema): void
    {
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'postgresql', 'This migration only supports PostgreSQL.');

        // DROP INDEX CONCURRENTLY тоже нельзя в транзакции → оставляем миграцию нетранзакционной
        $this->addSql('DROP INDEX CONCURRENTLY IF EXISTS idx_ai_prompt_log_only_errors');
        $this->addSql('DROP INDEX CONCURRENTLY IF EXISTS idx_ai_prompt_log_created_brin');
        $this->addSql('DROP INDEX CONCURRENTLY IF EXISTS idx_ai_prompt_log_metadata_gin');
        $this->addSql('DROP INDEX CONCURRENTLY IF EXISTS idx_ai_faq_answer_trgm');
        $this->addSql('DROP INDEX CONCURRENTLY IF EXISTS idx_ai_faq_question_trgm');
        $this->addSql('DROP INDEX CONCURRENTLY IF EXISTS idx_ai_faq_tags_gin');
    }
}
