<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20250902090000CompanyOperators extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add role/status metadata to user_companies for operator management.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE "user_companies" RENAME COLUMN joined_at TO created_at');
        $this->addSql('ALTER TABLE "user_companies" ADD status VARCHAR(16) DEFAULT ''ACTIVE'' NOT NULL');
        $this->addSql('ALTER TABLE "user_companies" ADD invited_by UUID DEFAULT NULL');
        $this->addSql('ALTER TABLE "user_companies" ALTER COLUMN role TYPE VARCHAR(16)');
        $this->addSql('ALTER TABLE "user_companies" ALTER COLUMN role SET DEFAULT ''OPERATOR''');
        $this->addSql('UPDATE "user_companies" SET role = ''OWNER'' WHERE role IN (''admin'', ''ADMIN'', ''OWNER'')');
        $this->addSql('UPDATE "user_companies" SET role = ''OPERATOR'' WHERE role NOT IN (''OWNER'', ''OPERATOR'')');
        $this->addSql('ALTER TABLE "user_companies" ALTER COLUMN created_at SET DEFAULT NOW()');
        $this->addSql('COMMENT ON COLUMN "user_companies".created_at IS ''(DC2Type:datetime_immutable)''');
        $this->addSql('CREATE INDEX IF NOT EXISTS IDX_USER_COMPANIES_COMPANY_USER ON "user_companies" (company_id, user_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX IF EXISTS IDX_USER_COMPANIES_COMPANY_USER');
        $this->addSql('ALTER TABLE "user_companies" ALTER COLUMN role DROP DEFAULT');
        $this->addSql('ALTER TABLE "user_companies" ALTER COLUMN role TYPE VARCHAR(30)');
        $this->addSql('UPDATE "user_companies" SET role = ''admin'' WHERE role = ''OWNER''');
        $this->addSql('UPDATE "user_companies" SET role = ''operator'' WHERE role = ''OPERATOR''');
        $this->addSql('ALTER TABLE "user_companies" DROP COLUMN status');
        $this->addSql('ALTER TABLE "user_companies" DROP COLUMN invited_by');
        $this->addSql('ALTER TABLE "user_companies" ALTER COLUMN created_at DROP DEFAULT');
        $this->addSql('ALTER TABLE "user_companies" RENAME COLUMN created_at TO joined_at');
        $this->addSql('COMMENT ON COLUMN "user_companies".joined_at IS ''(DC2Type:datetime_immutable)''');
    }
}
