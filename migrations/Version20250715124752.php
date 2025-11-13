<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250715124752 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE devis ADD COLUMN acompte NUMERIC(10, 2) DEFAULT NULL');
        $this->addSql('ALTER TABLE devis ADD COLUMN acompte_percentage NUMERIC(5, 2) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TEMPORARY TABLE __temp__devis AS SELECT id, number, title, description, status, total_ht, vat_rate, total_ttc, date_creation, date_validite, date_envoi, date_reponse, conditions, notes, created_at, updated_at, client_id, created_by_id FROM devis');
        $this->addSql('DROP TABLE devis');
        $this->addSql('CREATE TABLE devis (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, number VARCHAR(50) NOT NULL, title VARCHAR(255) NOT NULL, description CLOB DEFAULT NULL, status VARCHAR(50) NOT NULL, total_ht NUMERIC(10, 2) NOT NULL, vat_rate NUMERIC(5, 2) NOT NULL, total_ttc NUMERIC(10, 2) NOT NULL, date_creation DATE NOT NULL, date_validite DATE DEFAULT NULL, date_envoi DATE DEFAULT NULL, date_reponse DATE DEFAULT NULL, conditions CLOB DEFAULT NULL, notes CLOB DEFAULT NULL, created_at DATETIME NOT NULL, updated_at DATETIME DEFAULT NULL, client_id INTEGER NOT NULL, created_by_id INTEGER NOT NULL, CONSTRAINT FK_8B27C52B19EB6921 FOREIGN KEY (client_id) REFERENCES client (id) NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_8B27C52BB03A8386 FOREIGN KEY (created_by_id) REFERENCES "user" (id) NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('INSERT INTO devis (id, number, title, description, status, total_ht, vat_rate, total_ttc, date_creation, date_validite, date_envoi, date_reponse, conditions, notes, created_at, updated_at, client_id, created_by_id) SELECT id, number, title, description, status, total_ht, vat_rate, total_ttc, date_creation, date_validite, date_envoi, date_reponse, conditions, notes, created_at, updated_at, client_id, created_by_id FROM __temp__devis');
        $this->addSql('DROP TABLE __temp__devis');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_8B27C52B96901F54 ON devis (number)');
        $this->addSql('CREATE INDEX IDX_8B27C52B19EB6921 ON devis (client_id)');
        $this->addSql('CREATE INDEX IDX_8B27C52BB03A8386 ON devis (created_by_id)');
    }
}
