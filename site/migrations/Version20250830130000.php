<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20250830130000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add stage_entered_at column to crm_deals';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE "crm_deals" ADD COLUMN stage_entered_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL DEFAULT NOW()');
        $this->addSql('UPDATE "crm_deals" SET stage_entered_at = opened_at');
        $this->addSql('ALTER TABLE "crm_deals" ALTER COLUMN stage_entered_at DROP DEFAULT');
        $this->addSql("COMMENT ON COLUMN \"crm_deals\".stage_entered_at IS '(DC2Type:datetime_immutable)'");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE "crm_deals" DROP COLUMN stage_entered_at');
    }
}
