<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20250829055517_CkSearchIndexes extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'company_knowledge: ts_ru (tsvector) + GIN(ts_ru), trigram GIN(title, content); ensure pg_trgm';
    }

    // CONCURRENTLY → нет транзакции
    public function isTransactional(): bool
    {
        return false;
    }

    public function up(Schema $schema): void
    {
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'postgresql', 'This migration only supports PostgreSQL');

        // расширения
        $this->addSql('CREATE EXTENSION IF NOT EXISTS pg_trgm');

        // tsvector STORED column (русский словарь)
        $this->addSql("ALTER TABLE company_knowledge ADD COLUMN IF NOT EXISTS ts_ru tsvector GENERATED ALWAYS AS (to_tsvector('russian', coalesce(title,'') || ' ' || coalesce(content,''))) STORED");

        // Индексы: GIN(ts_ru), trigram на title/content
        $this->addSql('CREATE INDEX CONCURRENTLY IF NOT EXISTS idx_ck_ts ON company_knowledge USING GIN (ts_ru)');
        $this->addSql('CREATE INDEX CONCURRENTLY IF NOT EXISTS idx_ck_title_trgm ON company_knowledge USING GIN (title gin_trgm_ops)');
        $this->addSql('CREATE INDEX CONCURRENTLY IF NOT EXISTS idx_ck_content_trgm ON company_knowledge USING GIN (content gin_trgm_ops)');
    }

    public function down(Schema $schema): void
    {
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'postgresql', 'This migration only supports PostgreSQL');

        // удаляем индексы и колонку
        $this->addSql('DROP INDEX IF EXISTS idx_ck_ts');
        $this->addSql('DROP INDEX IF EXISTS idx_ck_title_trgm');
        $this->addSql('DROP INDEX IF EXISTS idx_ck_content_trgm');
        $this->addSql('ALTER TABLE company_knowledge DROP COLUMN IF EXISTS ts_ru');
    }
}
