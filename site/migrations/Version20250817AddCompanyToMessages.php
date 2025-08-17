<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Малый объём данных: одна миграция (с коротким окном обслуживания)
 * - Добавить messages.company_id
 * - Заполнить из clients.company_id
 * - Индекс
 * - FK
 * - NOT NULL
 */
final class Version20250817AddCompanyToMessages extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add messages.company_id, backfill from clients.company_id, add index + FK + NOT NULL';
    }

    public function up(Schema $schema): void
    {
        // 1) Добавляем колонку (nullable)
        $this->addSql('ALTER TABLE "messages" ADD COLUMN company_id UUID');

        // 2) Бэкфилл исторических строк
        $this->addSql('
            UPDATE "messages" m
            SET company_id = c.company_id
            FROM "clients" c
            WHERE m.client_id = c.id
              AND m.company_id IS NULL
        ');

        // 3) Индекс для быстрых выборок
        $this->addSql('
            CREATE INDEX IF NOT EXISTS idx_messages_company_client_created
            ON "messages" (company_id, client_id, created_at DESC)
        ');

        // 4) FK (после бэкфилла)
        $this->addSql('
            ALTER TABLE "messages"
            ADD CONSTRAINT fk_messages_company
            FOREIGN KEY (company_id) REFERENCES "companies" (id)
            ON DELETE CASCADE
        ');

        // 5) NOT NULL — теперь можно
        $this->addSql('
            ALTER TABLE "messages" ALTER COLUMN company_id SET NOT NULL
        ');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE "messages" DROP CONSTRAINT IF EXISTS fk_messages_company');
        $this->addSql('DROP INDEX IF EXISTS idx_messages_company_client_created');
        $this->addSql('ALTER TABLE "messages" DROP COLUMN IF EXISTS company_id');
    }
}
