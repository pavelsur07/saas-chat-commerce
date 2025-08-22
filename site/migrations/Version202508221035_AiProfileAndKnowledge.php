<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * AI: ai_company_profile (1:1 к companies) и company_knowledge (N:1 к companies)
 */
final class Version202508221200_AiProfileAndKnowledge extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'AI: профиль компании (tone of voice) и база знаний компании (FAQ/доставка/продукты/политики)';
    }

    public function up(Schema $schema): void
    {
        // ai_company_profile: 1:1 к companies (company_id — PK и FK)
        $this->addSql("
            CREATE TABLE ai_company_profile (
                company_id UUID NOT NULL,
                tone_of_voice TEXT DEFAULT NULL,
                brand_notes  TEXT DEFAULT NULL,
                language     VARCHAR(16) NOT NULL DEFAULT 'ru-RU',
                PRIMARY KEY(company_id)
            )
        ");

        // FK на companies(id)
        $this->addSql("
            ALTER TABLE ai_company_profile
            ADD CONSTRAINT FK_ai_cp_company
            FOREIGN KEY (company_id) REFERENCES companies (id)
            ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE
        ");

        // company_knowledge: N:1 к companies
        $this->addSql("
            CREATE TABLE company_knowledge (
                id UUID NOT NULL,
                company_id UUID NOT NULL,
                type VARCHAR(32) NOT NULL,
                title VARCHAR(160) NOT NULL,
                content TEXT NOT NULL,
                tags VARCHAR(255) DEFAULT NULL,
                created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
                PRIMARY KEY(id)
            )
        ");

        // Индексы по компании/типу и компании/заголовку
        $this->addSql("CREATE INDEX idx_ck_company_type  ON company_knowledge (company_id, type)");
        $this->addSql("CREATE INDEX idx_ck_company_title ON company_knowledge (company_id, title)");

        // FK на companies(id)
        $this->addSql("
            ALTER TABLE company_knowledge
            ADD CONSTRAINT FK_ck_company
            FOREIGN KEY (company_id) REFERENCES companies (id)
            ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE
        ");
    }

    public function down(Schema $schema): void
    {
        // Сначала снимаем FK (PostgreSQL сам снимет при DROP TABLE, но делаем явно для читаемости)
        $this->addSql('ALTER TABLE IF EXISTS ai_company_profile DROP CONSTRAINT IF EXISTS FK_ai_cp_company');
        $this->addSql('ALTER TABLE IF EXISTS company_knowledge DROP CONSTRAINT IF EXISTS FK_ck_company');

        // Затем удаляем таблицы
        $this->addSql('DROP TABLE IF EXISTS company_knowledge');
        $this->addSql('DROP TABLE IF EXISTS ai_company_profile');
    }
}
