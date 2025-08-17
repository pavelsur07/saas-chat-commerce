<?php
declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20250817AddFeatureToAiPromptLog extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add ai_prompt_log.feature and composite index (company_id, feature, created_at)';
    }

    public function up(Schema $schema): void
    {
        // 1) Добавляем колонку (с временным DEFAULT, чтобы быстро проставить)
        $this->addSql("ALTER TABLE ai_prompt_log ADD COLUMN feature VARCHAR(64)");
        $this->addSql("UPDATE ai_prompt_log SET feature = 'unknown' WHERE feature IS NULL");
        $this->addSql("ALTER TABLE ai_prompt_log ALTER COLUMN feature SET NOT NULL");

        // 2) Индекс
        $this->addSql('
            CREATE INDEX IF NOT EXISTS idx_ai_prompt_log_company_feature_created
            ON ai_prompt_log (company_id, feature, created_at DESC)
        ');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX IF EXISTS idx_ai_prompt_log_company_feature_created');
        $this->addSql('ALTER TABLE ai_prompt_log DROP COLUMN feature');
    }
}
