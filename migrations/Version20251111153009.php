<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251111153009 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE project (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, title VARCHAR(255) NOT NULL, slug VARCHAR(255) NOT NULL, client_name VARCHAR(255) DEFAULT NULL, category VARCHAR(50) NOT NULL, short_description CLOB NOT NULL, full_description CLOB DEFAULT NULL, technologies CLOB DEFAULT NULL, context CLOB DEFAULT NULL, solutions CLOB DEFAULT NULL, results CLOB DEFAULT NULL, image_filename VARCHAR(255) DEFAULT NULL, project_url VARCHAR(500) DEFAULT NULL, completion_date DATE DEFAULT NULL, featured BOOLEAN NOT NULL, is_published BOOLEAN NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_2FB3D0EE989D9B62 ON project (slug)');
        $this->addSql('CREATE TABLE testimonial (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, client_name VARCHAR(255) NOT NULL, client_company VARCHAR(255) DEFAULT NULL, content CLOB NOT NULL, rating SMALLINT NOT NULL, project_type VARCHAR(100) DEFAULT NULL, photo VARCHAR(255) DEFAULT NULL, is_published BOOLEAN NOT NULL, created_at DATETIME NOT NULL)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP TABLE project');
        $this->addSql('DROP TABLE testimonial');
    }
}
