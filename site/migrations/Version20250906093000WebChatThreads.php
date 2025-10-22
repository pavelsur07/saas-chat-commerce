<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20250906093000WebChatThreads extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Introduce web chat threads, visitor tracking, and message delivery metadata.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE "clients" ADD web_chat_site_id UUID DEFAULT NULL');
        $this->addSql('ALTER TABLE "clients" ADD last_seen_at TIMESTAMP(0) WITH TIME ZONE DEFAULT NULL');
        $this->addSql('CREATE INDEX idx_clients_web_chat_site ON "clients" (web_chat_site_id)');
        $this->addSql('ALTER TABLE "clients" ADD CONSTRAINT fk_clients_web_chat_site FOREIGN KEY (web_chat_site_id) REFERENCES "web_chat_sites" (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql("CREATE UNIQUE INDEX idx_clients_web_channel_visitor ON \"clients\" (company_id, external_id, web_chat_site_id) WHERE channel = 'web'");

        $this->addSql('CREATE TABLE "web_chat_threads" (id UUID NOT NULL, site_id UUID NOT NULL, client_id UUID NOT NULL, is_open BOOLEAN DEFAULT TRUE NOT NULL, closed_at TIMESTAMP(0) WITH TIME ZONE DEFAULT NULL, reopened_count INT DEFAULT 0 NOT NULL, last_message_at TIMESTAMP(0) WITH TIME ZONE DEFAULT NULL, created_at TIMESTAMP(0) WITH TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITH TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX idx_web_chat_threads_client_open ON "web_chat_threads" (client_id, is_open)');
        $this->addSql('CREATE INDEX idx_web_chat_threads_site_created ON "web_chat_threads" (site_id, created_at)');
        $this->addSql('ALTER TABLE "web_chat_threads" ADD CONSTRAINT fk_web_chat_threads_site FOREIGN KEY (site_id) REFERENCES "web_chat_sites" (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE "web_chat_threads" ADD CONSTRAINT fk_web_chat_threads_client FOREIGN KEY (client_id) REFERENCES "clients" (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');

        $this->addSql('ALTER TABLE "messages" ADD thread_id UUID DEFAULT NULL');
        $this->addSql('ALTER TABLE "messages" ADD source_id VARCHAR(120) DEFAULT NULL');
        $this->addSql('ALTER TABLE "messages" ADD dedupe_key VARCHAR(128) DEFAULT NULL');
        $this->addSql('ALTER TABLE "messages" ADD delivered_at TIMESTAMP(0) WITH TIME ZONE DEFAULT NULL');
        $this->addSql('ALTER TABLE "messages" ADD read_at TIMESTAMP(0) WITH TIME ZONE DEFAULT NULL');
        $this->addSql('CREATE INDEX idx_messages_thread_created_at ON "messages" (thread_id, created_at)');
        $this->addSql('CREATE UNIQUE INDEX idx_messages_thread_dedupe ON "messages" (thread_id, dedupe_key) WHERE dedupe_key IS NOT NULL');
        $this->addSql('ALTER TABLE "messages" ADD CONSTRAINT fk_messages_thread FOREIGN KEY (thread_id) REFERENCES "web_chat_threads" (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE "messages" DROP CONSTRAINT fk_messages_thread');
        $this->addSql('DROP INDEX IF EXISTS idx_messages_thread_created_at');
        $this->addSql('DROP INDEX IF EXISTS idx_messages_thread_dedupe');
        $this->addSql('ALTER TABLE "messages" DROP COLUMN thread_id');
        $this->addSql('ALTER TABLE "messages" DROP COLUMN source_id');
        $this->addSql('ALTER TABLE "messages" DROP COLUMN dedupe_key');
        $this->addSql('ALTER TABLE "messages" DROP COLUMN delivered_at');
        $this->addSql('ALTER TABLE "messages" DROP COLUMN read_at');

        $this->addSql('DROP TABLE IF EXISTS "web_chat_threads"');

        $this->addSql('ALTER TABLE "clients" DROP CONSTRAINT fk_clients_web_chat_site');
        $this->addSql('DROP INDEX IF EXISTS idx_clients_web_chat_site');
        $this->addSql('DROP INDEX IF EXISTS idx_clients_web_channel_visitor');
        $this->addSql('ALTER TABLE "clients" DROP COLUMN web_chat_site_id');
        $this->addSql('ALTER TABLE "clients" DROP COLUMN last_seen_at');
    }
}
