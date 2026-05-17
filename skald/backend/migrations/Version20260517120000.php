<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Schéma initial Skald : extension pgvector + table recording.
 *
 * Migration écrite à la main (pas de base disponible au moment du dev pour
 * un diff automatique). pgvector est activé dès maintenant pour préparer la
 * recherche sémantique (Phase 2) ; aucune colonne d'embedding en v0.1.
 */
final class Version20260517120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Initial Skald schema: pgvector extension + recording table';
    }

    public function up(Schema $schema): void
    {
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof \Doctrine\DBAL\Platforms\PostgreSQLPlatform,
            'Migration prévue pour PostgreSQL uniquement.',
        );

        $this->addSql('CREATE EXTENSION IF NOT EXISTS vector');

        $this->addSql(<<<'SQL'
            CREATE TABLE recording (
                id VARCHAR(36) NOT NULL,
                original_filename VARCHAR(255) NOT NULL,
                storage_path VARCHAR(255) NOT NULL,
                mime_type VARCHAR(100) NOT NULL,
                size_bytes BIGINT NOT NULL,
                device_id VARCHAR(128) DEFAULT NULL,
                recorded_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL,
                uploaded_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
                status VARCHAR(20) NOT NULL,
                PRIMARY KEY(id)
            )
            SQL);

        $this->addSql(
            "COMMENT ON COLUMN recording.recorded_at IS '(DC2Type:datetime_immutable)'"
        );
        $this->addSql(
            "COMMENT ON COLUMN recording.uploaded_at IS '(DC2Type:datetime_immutable)'"
        );
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE recording');
        // L'extension vector est laissée en place : potentiellement partagée.
    }
}
