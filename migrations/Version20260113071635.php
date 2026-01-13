<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260113071635 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add source, relevance_level, and last_seen_in_gsc fields to seo_keyword table';
    }

    public function up(Schema $schema): void
    {
        // Add columns with default values for existing rows
        $this->addSql("ALTER TABLE seo_keyword ADD source VARCHAR(20) NOT NULL DEFAULT 'manual', ADD relevance_level VARCHAR(20) NOT NULL DEFAULT 'medium', ADD last_seen_in_gsc DATETIME DEFAULT NULL COMMENT '(DC2Type:datetime_immutable)'");
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE seo_keyword DROP source, DROP relevance_level, DROP last_seen_in_gsc');
    }
}
