<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251116074713 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE project_image (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, image_filename VARCHAR(255) NOT NULL, alt_text VARCHAR(255) DEFAULT NULL, caption CLOB DEFAULT NULL, position INTEGER DEFAULT 0 NOT NULL, is_featured BOOLEAN DEFAULT 0 NOT NULL, created_at DATETIME NOT NULL, project_id INTEGER NOT NULL, CONSTRAINT FK_D6680DC1166D1F9C FOREIGN KEY (project_id) REFERENCES project (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('CREATE INDEX IDX_D6680DC1166D1F9C ON project_image (project_id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP TABLE project_image');
    }
}
