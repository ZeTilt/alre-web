<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250715092305 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE client (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, name VARCHAR(255) NOT NULL, type VARCHAR(50) NOT NULL, company_name VARCHAR(255) DEFAULT NULL, siret VARCHAR(20) DEFAULT NULL, vat_number VARCHAR(20) DEFAULT NULL, contact_first_name VARCHAR(255) DEFAULT NULL, contact_last_name VARCHAR(255) DEFAULT NULL, email VARCHAR(255) DEFAULT NULL, phone VARCHAR(20) DEFAULT NULL, address CLOB DEFAULT NULL, postal_code VARCHAR(10) DEFAULT NULL, city VARCHAR(100) DEFAULT NULL, country VARCHAR(100) DEFAULT NULL, notes CLOB DEFAULT NULL, created_at DATETIME NOT NULL, updated_at DATETIME DEFAULT NULL, is_active BOOLEAN NOT NULL)');
        $this->addSql('CREATE TABLE devis (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, number VARCHAR(50) NOT NULL, title VARCHAR(255) NOT NULL, description CLOB DEFAULT NULL, status VARCHAR(50) NOT NULL, total_ht NUMERIC(10, 2) NOT NULL, vat_rate NUMERIC(5, 2) NOT NULL, total_ttc NUMERIC(10, 2) NOT NULL, date_creation DATE NOT NULL, date_validite DATE DEFAULT NULL, date_envoi DATE DEFAULT NULL, date_reponse DATE DEFAULT NULL, conditions CLOB DEFAULT NULL, notes CLOB DEFAULT NULL, created_at DATETIME NOT NULL, updated_at DATETIME DEFAULT NULL, client_id INTEGER NOT NULL, created_by_id INTEGER NOT NULL, CONSTRAINT FK_8B27C52B19EB6921 FOREIGN KEY (client_id) REFERENCES client (id) NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_8B27C52BB03A8386 FOREIGN KEY (created_by_id) REFERENCES "user" (id) NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_8B27C52B96901F54 ON devis (number)');
        $this->addSql('CREATE INDEX IDX_8B27C52B19EB6921 ON devis (client_id)');
        $this->addSql('CREATE INDEX IDX_8B27C52BB03A8386 ON devis (created_by_id)');
        $this->addSql('CREATE TABLE devis_item (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, description CLOB NOT NULL, quantity NUMERIC(10, 2) NOT NULL, unit VARCHAR(50) DEFAULT NULL, unit_price NUMERIC(10, 2) NOT NULL, total NUMERIC(10, 2) NOT NULL, position INTEGER NOT NULL, devis_id INTEGER NOT NULL, CONSTRAINT FK_50C944C141DEFADA FOREIGN KEY (devis_id) REFERENCES devis (id) NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('CREATE INDEX IDX_50C944C141DEFADA ON devis_item (devis_id)');
        $this->addSql('CREATE TABLE facture (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, number VARCHAR(50) NOT NULL, title VARCHAR(255) NOT NULL, description CLOB DEFAULT NULL, status VARCHAR(50) NOT NULL, total_ht NUMERIC(10, 2) NOT NULL, vat_rate NUMERIC(5, 2) NOT NULL, total_ttc NUMERIC(10, 2) NOT NULL, date_facture DATE NOT NULL, date_echeance DATE NOT NULL, date_envoi DATE DEFAULT NULL, date_paiement DATE DEFAULT NULL, mode_paiement VARCHAR(50) DEFAULT NULL, conditions CLOB DEFAULT NULL, notes CLOB DEFAULT NULL, created_at DATETIME NOT NULL, updated_at DATETIME DEFAULT NULL, client_id INTEGER NOT NULL, devis_id INTEGER DEFAULT NULL, created_by_id INTEGER NOT NULL, CONSTRAINT FK_FE86641019EB6921 FOREIGN KEY (client_id) REFERENCES client (id) NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_FE86641041DEFADA FOREIGN KEY (devis_id) REFERENCES devis (id) NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_FE866410B03A8386 FOREIGN KEY (created_by_id) REFERENCES "user" (id) NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_FE86641096901F54 ON facture (number)');
        $this->addSql('CREATE INDEX IDX_FE86641019EB6921 ON facture (client_id)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_FE86641041DEFADA ON facture (devis_id)');
        $this->addSql('CREATE INDEX IDX_FE866410B03A8386 ON facture (created_by_id)');
        $this->addSql('CREATE TABLE facture_item (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, description CLOB NOT NULL, quantity NUMERIC(10, 2) NOT NULL, unit VARCHAR(50) DEFAULT NULL, unit_price NUMERIC(10, 2) NOT NULL, total NUMERIC(10, 2) NOT NULL, position INTEGER NOT NULL, facture_id INTEGER NOT NULL, CONSTRAINT FK_F91D09D27F2DEE08 FOREIGN KEY (facture_id) REFERENCES facture (id) NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('CREATE INDEX IDX_F91D09D27F2DEE08 ON facture_item (facture_id)');
        $this->addSql('CREATE TABLE "user" (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, username VARCHAR(180) NOT NULL, roles CLOB NOT NULL, password VARCHAR(255) NOT NULL, first_name VARCHAR(255) DEFAULT NULL, last_name VARCHAR(255) DEFAULT NULL, email VARCHAR(255) DEFAULT NULL, created_at DATETIME NOT NULL, last_login_at DATETIME DEFAULT NULL, is_active BOOLEAN NOT NULL)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_8D93D649F85E0677 ON "user" (username)');
        $this->addSql('CREATE TABLE messenger_messages (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, body CLOB NOT NULL, headers CLOB NOT NULL, queue_name VARCHAR(190) NOT NULL, created_at DATETIME NOT NULL, available_at DATETIME NOT NULL, delivered_at DATETIME DEFAULT NULL)');
        $this->addSql('CREATE INDEX IDX_75EA56E0FB7336F0 ON messenger_messages (queue_name)');
        $this->addSql('CREATE INDEX IDX_75EA56E0E3BD61CE ON messenger_messages (available_at)');
        $this->addSql('CREATE INDEX IDX_75EA56E016BA31DB ON messenger_messages (delivered_at)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP TABLE client');
        $this->addSql('DROP TABLE devis');
        $this->addSql('DROP TABLE devis_item');
        $this->addSql('DROP TABLE facture');
        $this->addSql('DROP TABLE facture_item');
        $this->addSql('DROP TABLE "user"');
        $this->addSql('DROP TABLE messenger_messages');
    }
}
