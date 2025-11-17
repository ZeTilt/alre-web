<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251116091122 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE client ADD COLUMN url VARCHAR(500) DEFAULT NULL');
        $this->addSql('CREATE TEMPORARY TABLE __temp__project AS SELECT id, title, slug, category, short_description, full_description, technologies, context, solutions, results, project_url, completion_date, featured, is_published, created_at, updated_at, partners FROM project');
        $this->addSql('DROP TABLE project');
        $this->addSql('CREATE TABLE project (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, title VARCHAR(255) NOT NULL, slug VARCHAR(255) NOT NULL, category VARCHAR(50) NOT NULL, short_description CLOB NOT NULL, full_description CLOB DEFAULT NULL, technologies CLOB DEFAULT NULL, context CLOB DEFAULT NULL, solutions CLOB DEFAULT NULL, results CLOB DEFAULT NULL, project_url VARCHAR(500) DEFAULT NULL, completion_date DATE DEFAULT NULL, featured BOOLEAN NOT NULL, is_published BOOLEAN NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, partners CLOB DEFAULT NULL, client_id INTEGER DEFAULT NULL, CONSTRAINT FK_2FB3D0EE19EB6921 FOREIGN KEY (client_id) REFERENCES client (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('INSERT INTO project (id, title, slug, category, short_description, full_description, technologies, context, solutions, results, project_url, completion_date, featured, is_published, created_at, updated_at, partners) SELECT id, title, slug, category, short_description, full_description, technologies, context, solutions, results, project_url, completion_date, featured, is_published, created_at, updated_at, partners FROM __temp__project');
        $this->addSql('DROP TABLE __temp__project');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_2FB3D0EE989D9B62 ON project (slug)');
        $this->addSql('CREATE INDEX IDX_2FB3D0EE19EB6921 ON project (client_id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TEMPORARY TABLE __temp__client AS SELECT id, name, type, company_name, siret, vat_number, contact_first_name, contact_last_name, email, phone, address, postal_code, city, country, notes, created_at, updated_at, is_active FROM client');
        $this->addSql('DROP TABLE client');
        $this->addSql('CREATE TABLE client (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, name VARCHAR(255) NOT NULL, type VARCHAR(50) NOT NULL, company_name VARCHAR(255) DEFAULT NULL, siret VARCHAR(20) DEFAULT NULL, vat_number VARCHAR(20) DEFAULT NULL, contact_first_name VARCHAR(255) DEFAULT NULL, contact_last_name VARCHAR(255) DEFAULT NULL, email VARCHAR(255) DEFAULT NULL, phone VARCHAR(20) DEFAULT NULL, address CLOB DEFAULT NULL, postal_code VARCHAR(10) DEFAULT NULL, city VARCHAR(100) DEFAULT NULL, country VARCHAR(100) DEFAULT NULL, notes CLOB DEFAULT NULL, created_at DATETIME NOT NULL, updated_at DATETIME DEFAULT NULL, is_active BOOLEAN NOT NULL)');
        $this->addSql('INSERT INTO client (id, name, type, company_name, siret, vat_number, contact_first_name, contact_last_name, email, phone, address, postal_code, city, country, notes, created_at, updated_at, is_active) SELECT id, name, type, company_name, siret, vat_number, contact_first_name, contact_last_name, email, phone, address, postal_code, city, country, notes, created_at, updated_at, is_active FROM __temp__client');
        $this->addSql('DROP TABLE __temp__client');
        $this->addSql('CREATE TEMPORARY TABLE __temp__project AS SELECT id, title, slug, category, short_description, full_description, technologies, partners, context, solutions, results, project_url, completion_date, featured, is_published, created_at, updated_at FROM project');
        $this->addSql('DROP TABLE project');
        $this->addSql('CREATE TABLE project (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, title VARCHAR(255) NOT NULL, slug VARCHAR(255) NOT NULL, category VARCHAR(50) NOT NULL, short_description CLOB NOT NULL, full_description CLOB DEFAULT NULL, technologies CLOB DEFAULT NULL, partners CLOB DEFAULT NULL, context CLOB DEFAULT NULL, solutions CLOB DEFAULT NULL, results CLOB DEFAULT NULL, project_url VARCHAR(500) DEFAULT NULL, completion_date DATE DEFAULT NULL, featured BOOLEAN NOT NULL, is_published BOOLEAN NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, client_name VARCHAR(255) DEFAULT NULL, image_filename VARCHAR(255) DEFAULT NULL)');
        $this->addSql('INSERT INTO project (id, title, slug, category, short_description, full_description, technologies, partners, context, solutions, results, project_url, completion_date, featured, is_published, created_at, updated_at) SELECT id, title, slug, category, short_description, full_description, technologies, partners, context, solutions, results, project_url, completion_date, featured, is_published, created_at, updated_at FROM __temp__project');
        $this->addSql('DROP TABLE __temp__project');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_2FB3D0EE989D9B62 ON project (slug)');
    }
}
