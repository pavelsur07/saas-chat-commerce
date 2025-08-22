<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version202508221035_AiProfileAndKnowledge extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'AI: ai_company_profile (1:1 к company) и company_knowledge (N:1 к company)';
    }

    public function up(Schema $schema): void
    {
        // ai_company_profile
        $this->addSql("
            CREATE TABLE ai_company_profile (
                company_id UUID NOT NULL,
                tone_of_voice TEXT DEFAULT NULL,
                brand_notes  TEXT DEFAULT NULL,
                language     VARCHAR(16) NOT NULL DEFAULT 'ru-RU',
                PRIMARY KEY(company_id)
            )
        ");
        $this->addSql("ALTER TABLE ai_company_profile ADD CONSTRAINT FK_ai_cp_company FOREIGN KEY (company_id) REFERENCES company (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE");

        // company_knowledge
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
        $this->addSql("CREATE INDEX idx_ck_company_type  ON company_knowledge (company_id, type)");
        $this->addSql("CREATE INDEX idx_ck_company_title ON company_knowledge (company_id, title)");
        $this->addSql("ALTER TABLE company_knowledge ADD CONSTRAINT FK_ck_company FOREIGN KEY (company_id) REFERENCES company (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE company_knowledge');
        $this->addSql('DROP TABLE ai_company_profile');
    }
}
