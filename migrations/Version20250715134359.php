<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250715134359 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE devis_item ADD COLUMN discount NUMERIC(5, 2) DEFAULT NULL');
        $this->addSql('ALTER TABLE devis_item ADD COLUMN vat_rate NUMERIC(5, 2) DEFAULT NULL');
        $this->addSql('ALTER TABLE facture_item ADD COLUMN discount NUMERIC(5, 2) DEFAULT NULL');
        $this->addSql('ALTER TABLE facture_item ADD COLUMN vat_rate NUMERIC(5, 2) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TEMPORARY TABLE __temp__devis_item AS SELECT id, description, quantity, unit, unit_price, total, position, devis_id FROM devis_item');
        $this->addSql('DROP TABLE devis_item');
        $this->addSql('CREATE TABLE devis_item (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, description CLOB NOT NULL, quantity NUMERIC(10, 2) NOT NULL, unit VARCHAR(50) DEFAULT NULL, unit_price NUMERIC(10, 2) NOT NULL, total NUMERIC(10, 2) NOT NULL, position INTEGER NOT NULL, devis_id INTEGER NOT NULL, CONSTRAINT FK_50C944C141DEFADA FOREIGN KEY (devis_id) REFERENCES devis (id) NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('INSERT INTO devis_item (id, description, quantity, unit, unit_price, total, position, devis_id) SELECT id, description, quantity, unit, unit_price, total, position, devis_id FROM __temp__devis_item');
        $this->addSql('DROP TABLE __temp__devis_item');
        $this->addSql('CREATE INDEX IDX_50C944C141DEFADA ON devis_item (devis_id)');
        $this->addSql('CREATE TEMPORARY TABLE __temp__facture_item AS SELECT id, description, quantity, unit, unit_price, total, position, facture_id FROM facture_item');
        $this->addSql('DROP TABLE facture_item');
        $this->addSql('CREATE TABLE facture_item (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, description CLOB NOT NULL, quantity NUMERIC(10, 2) NOT NULL, unit VARCHAR(50) DEFAULT NULL, unit_price NUMERIC(10, 2) NOT NULL, total NUMERIC(10, 2) NOT NULL, position INTEGER NOT NULL, facture_id INTEGER NOT NULL, CONSTRAINT FK_F91D09D27F2DEE08 FOREIGN KEY (facture_id) REFERENCES facture (id) NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('INSERT INTO facture_item (id, description, quantity, unit, unit_price, total, position, facture_id) SELECT id, description, quantity, unit, unit_price, total, position, facture_id FROM __temp__facture_item');
        $this->addSql('DROP TABLE __temp__facture_item');
        $this->addSql('CREATE INDEX IDX_F91D09D27F2DEE08 ON facture_item (facture_id)');
    }
}
