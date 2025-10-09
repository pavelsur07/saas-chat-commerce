<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20250831ClientReadStates extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create client_read_states table to store per-user read markers.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE "client_read_states" (id UUID NOT NULL, company_id UUID NOT NULL, client_id UUID NOT NULL, user_id UUID NOT NULL, last_read_at TIMESTAMP(0) WITH TIME ZONE DEFAULT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE UNIQUE INDEX uniq_client_read_states_company_client_user ON "client_read_states" (company_id, client_id, user_id)');
        $this->addSql('CREATE INDEX idx_client_read_states_company_user ON "client_read_states" (company_id, user_id)');
        $this->addSql('CREATE INDEX idx_client_read_states_company_client ON "client_read_states" (company_id, client_id)');
        $this->addSql('ALTER TABLE "client_read_states" ADD CONSTRAINT fk_client_read_states_company FOREIGN KEY (company_id) REFERENCES "companies" (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE "client_read_states" ADD CONSTRAINT fk_client_read_states_client FOREIGN KEY (client_id) REFERENCES "clients" (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE "client_read_states" ADD CONSTRAINT fk_client_read_states_user FOREIGN KEY (user_id) REFERENCES "user" (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE "client_read_states" DROP CONSTRAINT IF EXISTS fk_client_read_states_company');
        $this->addSql('ALTER TABLE "client_read_states" DROP CONSTRAINT IF EXISTS fk_client_read_states_client');
        $this->addSql('ALTER TABLE "client_read_states" DROP CONSTRAINT IF EXISTS fk_client_read_states_user');
        $this->addSql('DROP INDEX IF EXISTS uniq_client_read_states_company_client_user');
        $this->addSql('DROP INDEX IF EXISTS idx_client_read_states_company_user');
        $this->addSql('DROP INDEX IF EXISTS idx_client_read_states_company_client');
        $this->addSql('DROP TABLE IF EXISTS "client_read_states"');
    }
}
