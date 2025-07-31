<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250731105340 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE "messages" (id UUID NOT NULL, client_id UUID DEFAULT NULL, channel VARCHAR(20) NOT NULL, direction VARCHAR(20) NOT NULL, text TEXT DEFAULT NULL, payload JSON DEFAULT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_DB021E9619EB6921 ON "messages" (client_id)');
        $this->addSql('COMMENT ON COLUMN "messages".created_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('ALTER TABLE "messages" ADD CONSTRAINT FK_DB021E9619EB6921 FOREIGN KEY (client_id) REFERENCES "clients" (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE SCHEMA public');
        $this->addSql('ALTER TABLE "messages" DROP CONSTRAINT FK_DB021E9619EB6921');
        $this->addSql('DROP TABLE "messages"');
    }
}
