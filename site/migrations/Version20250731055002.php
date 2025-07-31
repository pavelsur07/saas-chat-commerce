<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250731055002 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE "companies" (id UUID NOT NULL, name VARCHAR(150) NOT NULL, slug VARCHAR(100) NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_8244AA3A989D9B62 ON "companies" (slug)');
        $this->addSql('COMMENT ON COLUMN "companies".created_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('CREATE TABLE "user_companies" (id UUID NOT NULL, user_id UUID DEFAULT NULL, company_id UUID DEFAULT NULL, role VARCHAR(30) NOT NULL, joined_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_82A427DEA76ED395 ON "user_companies" (user_id)');
        $this->addSql('CREATE INDEX IDX_82A427DE979B1AD6 ON "user_companies" (company_id)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_82A427DEA76ED395979B1AD6 ON "user_companies" (user_id, company_id)');
        $this->addSql('COMMENT ON COLUMN "user_companies".joined_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('ALTER TABLE "user_companies" ADD CONSTRAINT FK_82A427DEA76ED395 FOREIGN KEY (user_id) REFERENCES "user" (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE "user_companies" ADD CONSTRAINT FK_82A427DE979B1AD6 FOREIGN KEY (company_id) REFERENCES "companies" (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE SCHEMA public');
        $this->addSql('ALTER TABLE "user_companies" DROP CONSTRAINT FK_82A427DEA76ED395');
        $this->addSql('ALTER TABLE "user_companies" DROP CONSTRAINT FK_82A427DE979B1AD6');
        $this->addSql('DROP TABLE "companies"');
        $this->addSql('DROP TABLE "user_companies"');
    }
}
