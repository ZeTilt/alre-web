<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251117060923 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE partner (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, name VARCHAR(255) NOT NULL, url VARCHAR(500) DEFAULT NULL, email VARCHAR(255) DEFAULT NULL, phone VARCHAR(50) DEFAULT NULL, domains CLOB NOT NULL, logo VARCHAR(255) DEFAULT NULL, is_active BOOLEAN NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL)');
        $this->addSql('CREATE TABLE project_partner (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, selected_domains CLOB NOT NULL, created_at DATETIME NOT NULL, project_id INTEGER NOT NULL, partner_id INTEGER NOT NULL, CONSTRAINT FK_A7353273166D1F9C FOREIGN KEY (project_id) REFERENCES project (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_A73532739393F8FE FOREIGN KEY (partner_id) REFERENCES partner (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('CREATE INDEX IDX_A7353273166D1F9C ON project_partner (project_id)');
        $this->addSql('CREATE INDEX IDX_A73532739393F8FE ON project_partner (partner_id)');
        $this->addSql('CREATE TEMPORARY TABLE __temp__project AS SELECT id, title, slug, category, short_description, full_description, technologies, context, solutions, results, project_url, completion_date, featured, is_published, created_at, updated_at, client_id FROM project');
        $this->addSql('DROP TABLE project');
        $this->addSql('CREATE TABLE project (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, title VARCHAR(255) NOT NULL, slug VARCHAR(255) NOT NULL, category VARCHAR(50) NOT NULL, short_description CLOB NOT NULL, full_description CLOB DEFAULT NULL, technologies CLOB DEFAULT NULL, context CLOB DEFAULT NULL, solutions CLOB DEFAULT NULL, results CLOB DEFAULT NULL, project_url VARCHAR(500) DEFAULT NULL, completion_date DATE DEFAULT NULL, featured BOOLEAN NOT NULL, is_published BOOLEAN NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, client_id INTEGER DEFAULT NULL, CONSTRAINT FK_2FB3D0EE19EB6921 FOREIGN KEY (client_id) REFERENCES client (id) ON UPDATE NO ACTION ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('INSERT INTO project (id, title, slug, category, short_description, full_description, technologies, context, solutions, results, project_url, completion_date, featured, is_published, created_at, updated_at, client_id) SELECT id, title, slug, category, short_description, full_description, technologies, context, solutions, results, project_url, completion_date, featured, is_published, created_at, updated_at, client_id FROM __temp__project');
        $this->addSql('DROP TABLE __temp__project');
        $this->addSql('CREATE INDEX IDX_2FB3D0EE19EB6921 ON project (client_id)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_2FB3D0EE989D9B62 ON project (slug)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP TABLE partner');
        $this->addSql('DROP TABLE project_partner');
        $this->addSql('ALTER TABLE project ADD COLUMN partners CLOB DEFAULT NULL');
    }
}
