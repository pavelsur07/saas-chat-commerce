<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250731130412 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE companies ADD owner_id UUID NOT NULL');
        $this->addSql('ALTER TABLE companies ADD CONSTRAINT FK_8244AA3A7E3C61F9 FOREIGN KEY (owner_id) REFERENCES "user" (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('CREATE INDEX IDX_8244AA3A7E3C61F9 ON companies (owner_id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE SCHEMA public');
        $this->addSql('ALTER TABLE "companies" DROP CONSTRAINT FK_8244AA3A7E3C61F9');
        $this->addSql('DROP INDEX IDX_8244AA3A7E3C61F9');
        $this->addSql('ALTER TABLE "companies" DROP owner_id');
    }
}
