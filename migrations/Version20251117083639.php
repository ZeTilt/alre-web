<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251117083639 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE project DROP client_name, DROP partners, DROP image_filename');
        $this->addSql('ALTER TABLE project_image CHANGE caption caption LONGTEXT DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE project ADD client_name VARCHAR(255) DEFAULT NULL, ADD partners JSON DEFAULT NULL, ADD image_filename VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE project_image CHANGE caption caption TEXT DEFAULT NULL');
    }
}
