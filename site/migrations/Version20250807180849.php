<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250807180849 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER INDEX idx_33a9b624a4e09516 RENAME TO IDX_DB021E96A0E2F38');
        $this->addSql('ALTER TABLE telegram_bots ADD last_update_id BIGINT DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE SCHEMA public');
        $this->addSql('ALTER INDEX idx_db021e96a0e2f38 RENAME TO idx_33a9b624a4e09516');
        $this->addSql('ALTER TABLE "telegram_bots" DROP last_update_id');
    }
}
