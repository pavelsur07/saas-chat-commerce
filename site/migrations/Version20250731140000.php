<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20250731140000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add telegram_bot relation to messages';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE messages ADD telegram_bot_id UUID DEFAULT NULL');
        $this->addSql('ALTER TABLE messages ADD CONSTRAINT FK_33A9B624A4E09516 FOREIGN KEY (telegram_bot_id) REFERENCES telegram_bots (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('CREATE INDEX IDX_33A9B624A4E09516 ON messages (telegram_bot_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX IDX_33A9B624A4E09516');
        $this->addSql('ALTER TABLE messages DROP CONSTRAINT FK_33A9B624A4E09516');
        $this->addSql('ALTER TABLE messages DROP telegram_bot_id');
    }
}
