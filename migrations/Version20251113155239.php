<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251113155239 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE client (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(255) NOT NULL, type VARCHAR(50) NOT NULL, company_name VARCHAR(255) DEFAULT NULL, siret VARCHAR(20) DEFAULT NULL, vat_number VARCHAR(20) DEFAULT NULL, contact_first_name VARCHAR(255) DEFAULT NULL, contact_last_name VARCHAR(255) DEFAULT NULL, email VARCHAR(255) DEFAULT NULL, phone VARCHAR(20) DEFAULT NULL, address LONGTEXT DEFAULT NULL, postal_code VARCHAR(10) DEFAULT NULL, city VARCHAR(100) DEFAULT NULL, country VARCHAR(100) DEFAULT NULL, notes LONGTEXT DEFAULT NULL, created_at DATETIME NOT NULL, updated_at DATETIME DEFAULT NULL, is_active TINYINT(1) NOT NULL, PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE company (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(255) NOT NULL, owner_name VARCHAR(255) NOT NULL, title VARCHAR(255) NOT NULL, address LONGTEXT NOT NULL, postal_code VARCHAR(10) NOT NULL, city VARCHAR(255) NOT NULL, phone VARCHAR(20) NOT NULL, email VARCHAR(255) NOT NULL, siret VARCHAR(20) NOT NULL, website VARCHAR(255) DEFAULT NULL, legal_status VARCHAR(255) DEFAULT NULL, legal_mentions LONGTEXT DEFAULT NULL, created_at DATETIME DEFAULT NULL, updated_at DATETIME DEFAULT NULL, PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE contact_message (id INT AUTO_INCREMENT NOT NULL, first_name VARCHAR(255) NOT NULL, last_name VARCHAR(255) NOT NULL, email VARCHAR(255) NOT NULL, phone VARCHAR(20) DEFAULT NULL, company VARCHAR(255) DEFAULT NULL, project_type VARCHAR(100) NOT NULL, budget VARCHAR(100) DEFAULT NULL, message LONGTEXT NOT NULL, rgpd_consent TINYINT(1) NOT NULL, created_at DATETIME NOT NULL, is_read TINYINT(1) NOT NULL, is_archived TINYINT(1) NOT NULL, PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE devis (id INT AUTO_INCREMENT NOT NULL, number VARCHAR(50) DEFAULT NULL, title VARCHAR(255) NOT NULL, description LONGTEXT DEFAULT NULL, status VARCHAR(50) NOT NULL, total_ht NUMERIC(10, 2) NOT NULL, vat_rate NUMERIC(5, 2) NOT NULL, total_ttc NUMERIC(10, 2) NOT NULL, date_creation DATE NOT NULL, date_validite DATE DEFAULT NULL, date_envoi DATE DEFAULT NULL, date_reponse DATE DEFAULT NULL, conditions LONGTEXT DEFAULT NULL, notes LONGTEXT DEFAULT NULL, created_at DATETIME NOT NULL, updated_at DATETIME DEFAULT NULL, acompte NUMERIC(10, 2) DEFAULT NULL, acompte_percentage NUMERIC(5, 2) DEFAULT NULL, client_id INT NOT NULL, created_by_id INT NOT NULL, UNIQUE INDEX UNIQ_8B27C52B96901F54 (number), INDEX IDX_8B27C52B19EB6921 (client_id), INDEX IDX_8B27C52BB03A8386 (created_by_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE devis_item (id INT AUTO_INCREMENT NOT NULL, description LONGTEXT NOT NULL, quantity NUMERIC(10, 2) NOT NULL, unit VARCHAR(50) DEFAULT NULL, unit_price NUMERIC(10, 2) NOT NULL, total NUMERIC(10, 2) NOT NULL, discount NUMERIC(5, 2) DEFAULT NULL, vat_rate NUMERIC(5, 2) DEFAULT NULL, position INT DEFAULT NULL, devis_id INT NOT NULL, INDEX IDX_50C944C141DEFADA (devis_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE facture (id INT AUTO_INCREMENT NOT NULL, number VARCHAR(50) DEFAULT NULL, title VARCHAR(255) NOT NULL, description LONGTEXT DEFAULT NULL, status VARCHAR(50) NOT NULL, total_ht NUMERIC(10, 2) NOT NULL, vat_rate NUMERIC(5, 2) NOT NULL, total_ttc NUMERIC(10, 2) NOT NULL, date_facture DATE NOT NULL, date_echeance DATE NOT NULL, date_envoi DATE DEFAULT NULL, date_paiement DATE DEFAULT NULL, mode_paiement VARCHAR(50) DEFAULT NULL, conditions LONGTEXT DEFAULT NULL, notes LONGTEXT DEFAULT NULL, created_at DATETIME NOT NULL, updated_at DATETIME DEFAULT NULL, client_id INT NOT NULL, devis_id INT DEFAULT NULL, created_by_id INT NOT NULL, UNIQUE INDEX UNIQ_FE86641096901F54 (number), INDEX IDX_FE86641019EB6921 (client_id), UNIQUE INDEX UNIQ_FE86641041DEFADA (devis_id), INDEX IDX_FE866410B03A8386 (created_by_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE facture_item (id INT AUTO_INCREMENT NOT NULL, description LONGTEXT NOT NULL, quantity NUMERIC(10, 2) NOT NULL, unit VARCHAR(50) DEFAULT NULL, unit_price NUMERIC(10, 2) NOT NULL, total NUMERIC(10, 2) NOT NULL, discount NUMERIC(5, 2) DEFAULT NULL, vat_rate NUMERIC(5, 2) DEFAULT NULL, position INT DEFAULT NULL, facture_id INT NOT NULL, INDEX IDX_F91D09D27F2DEE08 (facture_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE project (id INT AUTO_INCREMENT NOT NULL, title VARCHAR(255) NOT NULL, slug VARCHAR(255) NOT NULL, client_name VARCHAR(255) DEFAULT NULL, category VARCHAR(50) NOT NULL, short_description LONGTEXT NOT NULL, full_description LONGTEXT DEFAULT NULL, technologies JSON DEFAULT NULL, partners JSON DEFAULT NULL, context LONGTEXT DEFAULT NULL, solutions LONGTEXT DEFAULT NULL, results LONGTEXT DEFAULT NULL, image_filename VARCHAR(255) DEFAULT NULL, project_url VARCHAR(500) DEFAULT NULL, completion_date DATE DEFAULT NULL, featured TINYINT(1) NOT NULL, is_published TINYINT(1) NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, UNIQUE INDEX UNIQ_2FB3D0EE989D9B62 (slug), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE testimonial (id INT AUTO_INCREMENT NOT NULL, client_name VARCHAR(255) NOT NULL, client_company VARCHAR(255) DEFAULT NULL, content LONGTEXT NOT NULL, rating SMALLINT NOT NULL, project_type VARCHAR(100) DEFAULT NULL, photo VARCHAR(255) DEFAULT NULL, is_published TINYINT(1) NOT NULL, created_at DATETIME NOT NULL, PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE `user` (id INT AUTO_INCREMENT NOT NULL, username VARCHAR(180) NOT NULL, roles JSON NOT NULL, password VARCHAR(255) NOT NULL, first_name VARCHAR(255) DEFAULT NULL, last_name VARCHAR(255) DEFAULT NULL, email VARCHAR(255) DEFAULT NULL, created_at DATETIME NOT NULL, last_login_at DATETIME DEFAULT NULL, is_active TINYINT(1) NOT NULL, UNIQUE INDEX UNIQ_8D93D649F85E0677 (username), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE messenger_messages (id BIGINT AUTO_INCREMENT NOT NULL, body LONGTEXT NOT NULL, headers LONGTEXT NOT NULL, queue_name VARCHAR(190) NOT NULL, created_at DATETIME NOT NULL, available_at DATETIME NOT NULL, delivered_at DATETIME DEFAULT NULL, INDEX IDX_75EA56E0FB7336F0 (queue_name), INDEX IDX_75EA56E0E3BD61CE (available_at), INDEX IDX_75EA56E016BA31DB (delivered_at), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE devis ADD CONSTRAINT FK_8B27C52B19EB6921 FOREIGN KEY (client_id) REFERENCES client (id)');
        $this->addSql('ALTER TABLE devis ADD CONSTRAINT FK_8B27C52BB03A8386 FOREIGN KEY (created_by_id) REFERENCES `user` (id)');
        $this->addSql('ALTER TABLE devis_item ADD CONSTRAINT FK_50C944C141DEFADA FOREIGN KEY (devis_id) REFERENCES devis (id)');
        $this->addSql('ALTER TABLE facture ADD CONSTRAINT FK_FE86641019EB6921 FOREIGN KEY (client_id) REFERENCES client (id)');
        $this->addSql('ALTER TABLE facture ADD CONSTRAINT FK_FE86641041DEFADA FOREIGN KEY (devis_id) REFERENCES devis (id)');
        $this->addSql('ALTER TABLE facture ADD CONSTRAINT FK_FE866410B03A8386 FOREIGN KEY (created_by_id) REFERENCES `user` (id)');
        $this->addSql('ALTER TABLE facture_item ADD CONSTRAINT FK_F91D09D27F2DEE08 FOREIGN KEY (facture_id) REFERENCES facture (id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE devis DROP FOREIGN KEY FK_8B27C52B19EB6921');
        $this->addSql('ALTER TABLE devis DROP FOREIGN KEY FK_8B27C52BB03A8386');
        $this->addSql('ALTER TABLE devis_item DROP FOREIGN KEY FK_50C944C141DEFADA');
        $this->addSql('ALTER TABLE facture DROP FOREIGN KEY FK_FE86641019EB6921');
        $this->addSql('ALTER TABLE facture DROP FOREIGN KEY FK_FE86641041DEFADA');
        $this->addSql('ALTER TABLE facture DROP FOREIGN KEY FK_FE866410B03A8386');
        $this->addSql('ALTER TABLE facture_item DROP FOREIGN KEY FK_F91D09D27F2DEE08');
        $this->addSql('DROP TABLE client');
        $this->addSql('DROP TABLE company');
        $this->addSql('DROP TABLE contact_message');
        $this->addSql('DROP TABLE devis');
        $this->addSql('DROP TABLE devis_item');
        $this->addSql('DROP TABLE facture');
        $this->addSql('DROP TABLE facture_item');
        $this->addSql('DROP TABLE project');
        $this->addSql('DROP TABLE testimonial');
        $this->addSql('DROP TABLE `user`');
        $this->addSql('DROP TABLE messenger_messages');
    }
}
