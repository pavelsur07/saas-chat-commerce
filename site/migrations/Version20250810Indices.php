<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20250810Indices extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'PostgreSQL extensions + GIN/BRIN/partial indexes for ai_faq, ai_prompt_log';
    }

    /**
     * Важно: используем CONCURRENTLY → миграция должна идти БЕЗ транзакции.
     */
    public function isTransactional(): bool
    {
        return false;
    }

    public function up(Schema $schema): void
    {
        // 1) Extensions (safe if already installed)
        $this->addSql("CREATE EXTENSION IF NOT EXISTS pg_trgm");
        $this->addSql("CREATE EXTENSION IF NOT EXISTS btree_gin");

        // 2) ai_faq — JSONB tags (GIN)
        $this->addSql("
            CREATE INDEX CONCURRENTLY IF NOT EXISTS idx_ai_faq_tags_gin
            ON ai_faq USING GIN (tags jsonb_path_ops)
        ");

        // 3) ai_faq — trigram indexes for ILIKE/substring search
        $this->addSql("
            CREATE INDEX CONCURRENTLY IF NOT EXISTS idx_ai_faq_question_trgm
            ON ai_faq USING GIN (question gin_trgm_ops)
        ");
        $this->addSql("
            CREATE INDEX CONCURRENTLY IF NOT EXISTS idx_ai_faq_answer_trgm
            ON ai_faq USING GIN (answer gin_trgm_ops)
        ");

        // 4) ai_prompt_log — JSONB metadata (GIN)
        $this->addSql("
            CREATE INDEX CONCURRENTLY IF NOT EXISTS idx_ai_prompt_log_metadata_gin
            ON ai_prompt_log USING GIN (metadata jsonb_path_ops)
        ");

        // 5) ai_prompt_log — BRIN по дате для больших таблиц логов
        $this->addSql("
            CREATE INDEX CONCURRENTLY IF NOT EXISTS idx_ai_prompt_log_created_brin
            ON ai_prompt_log USING BRIN (created_at)
        ");

        // 6) ai_prompt_log — partial: только записи с ошибками
        $this->addSql("
            CREATE INDEX CONCURRENTLY IF NOT EXISTS idx_ai_prompt_log_only_errors
            ON ai_prompt_log (company_id, created_at DESC)
            WHERE status <> 'ok'
        ");
    }

    public function down(Schema $schema): void
    {
        // Откатываем только индексы (расширения оставляем: их могут использовать другие объекты)
        $this->addSql("DROP INDEX CONCURRENTLY IF EXISTS idx_ai_prompt_log_only_errors");
        $this->addSql("DROP INDEX CONCURRENTLY IF EXISTS idx_ai_prompt_log_created_brin");
        $this->addSql("DROP INDEX CONCURRENTLY IF EXISTS idx_ai_prompt_log_metadata_gin");
        $this->addSql("DROP INDEX CONCURRENTLY IF EXISTS idx_ai_faq_answer_trgm");
        $this->addSql("DROP INDEX CONCURRENTLY IF EXISTS idx_ai_faq_question_trgm");
        $this->addSql("DROP INDEX CONCURRENTLY IF EXISTS idx_ai_faq_tags_gin");

        // Если принципиально нужно убирать расширения — раскомментируй (риск сломать другие объекты):
        // $this->addSql("DROP EXTENSION IF EXISTS btree_gin");
        // $this->addSql("DROP EXTENSION IF EXISTS pg_trgm");
    }
}
