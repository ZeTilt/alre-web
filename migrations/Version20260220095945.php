<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260220095945 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add lastSeenInGsc and deactivatedAt fields to ClientSeoKeyword for auto-deactivation tracking';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE client_seo_keyword ADD last_seen_in_gsc DATETIME DEFAULT NULL, ADD deactivated_at DATETIME DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE client_seo_keyword DROP last_seen_in_gsc, DROP deactivated_at');
    }
}
