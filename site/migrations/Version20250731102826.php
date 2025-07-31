<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250731102826 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE "telegram_bots" (id UUID NOT NULL, company_id UUID DEFAULT NULL, token VARCHAR(255) NOT NULL, webhook_url VARCHAR(255) DEFAULT NULL, is_active BOOLEAN NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_DACD6ED979B1AD6 ON "telegram_bots" (company_id)');
        $this->addSql('ALTER TABLE "telegram_bots" ADD CONSTRAINT FK_DACD6ED979B1AD6 FOREIGN KEY (company_id) REFERENCES "companies" (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE SCHEMA public');
        $this->addSql('ALTER TABLE "telegram_bots" DROP CONSTRAINT FK_DACD6ED979B1AD6');
        $this->addSql('DROP TABLE "telegram_bots"');
    }
}
