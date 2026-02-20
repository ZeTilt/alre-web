<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260220091412 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Remove obsolete CSV import scheduling columns from client_site';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE client_site DROP import_day, DROP import_slot');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE client_site ADD import_day SMALLINT DEFAULT NULL, ADD import_slot VARCHAR(10) DEFAULT NULL');
    }
}
