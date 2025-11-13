<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251113144827 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE project ADD COLUMN partners CLOB DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TEMPORARY TABLE __temp__project AS SELECT id, title, slug, client_name, category, short_description, full_description, technologies, context, solutions, results, image_filename, project_url, completion_date, featured, is_published, created_at, updated_at FROM project');
        $this->addSql('DROP TABLE project');
        $this->addSql('CREATE TABLE project (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, title VARCHAR(255) NOT NULL, slug VARCHAR(255) NOT NULL, client_name VARCHAR(255) DEFAULT NULL, category VARCHAR(50) NOT NULL, short_description CLOB NOT NULL, full_description CLOB DEFAULT NULL, technologies CLOB DEFAULT NULL, context CLOB DEFAULT NULL, solutions CLOB DEFAULT NULL, results CLOB DEFAULT NULL, image_filename VARCHAR(255) DEFAULT NULL, project_url VARCHAR(500) DEFAULT NULL, completion_date DATE DEFAULT NULL, featured BOOLEAN NOT NULL, is_published BOOLEAN NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL)');
        $this->addSql('INSERT INTO project (id, title, slug, client_name, category, short_description, full_description, technologies, context, solutions, results, image_filename, project_url, completion_date, featured, is_published, created_at, updated_at) SELECT id, title, slug, client_name, category, short_description, full_description, technologies, context, solutions, results, image_filename, project_url, completion_date, featured, is_published, created_at, updated_at FROM __temp__project');
        $this->addSql('DROP TABLE __temp__project');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_2FB3D0EE989D9B62 ON project (slug)');
    }
}
