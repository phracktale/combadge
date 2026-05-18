<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * UI web : tables user + login_token (auth magic-link).
 * Migration écrite à la main (pas de base au moment du dev pour un diff auto).
 */
final class Version20260518090000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Web UI auth: user + login_token tables';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE "user" (
                id SERIAL NOT NULL,
                email VARCHAR(180) NOT NULL,
                PRIMARY KEY(id)
            )
            SQL);
        $this->addSql('CREATE UNIQUE INDEX uniq_user_email ON "user" (email)');

        $this->addSql(<<<'SQL'
            CREATE TABLE login_token (
                id SERIAL NOT NULL,
                user_id INT NOT NULL,
                token_hash VARCHAR(64) NOT NULL,
                expires_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
                used_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL,
                PRIMARY KEY(id)
            )
            SQL);
        $this->addSql('CREATE INDEX idx_login_token_hash ON login_token (token_hash)');
        $this->addSql(
            "COMMENT ON COLUMN login_token.expires_at IS '(DC2Type:datetime_immutable)'"
        );
        $this->addSql(
            "COMMENT ON COLUMN login_token.used_at IS '(DC2Type:datetime_immutable)'"
        );
        $this->addSql(<<<'SQL'
            ALTER TABLE login_token
                ADD CONSTRAINT fk_login_token_user
                FOREIGN KEY (user_id) REFERENCES "user" (id)
                ON DELETE CASCADE
            SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE login_token');
        $this->addSql('DROP TABLE "user"');
    }
}
