<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251128143459 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE `event` (id INT AUTO_INCREMENT NOT NULL, title VARCHAR(255) NOT NULL, description LONGTEXT DEFAULT NULL, type VARCHAR(20) NOT NULL, start_at DATETIME NOT NULL, end_at DATETIME DEFAULT NULL, all_day TINYINT(1) NOT NULL, location VARCHAR(255) DEFAULT NULL, color VARCHAR(7) DEFAULT NULL, created_at DATETIME NOT NULL, updated_at DATETIME DEFAULT NULL, client_id INT DEFAULT NULL, INDEX IDX_3BAE0AA719EB6921 (client_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE `event` ADD CONSTRAINT FK_3BAE0AA719EB6921 FOREIGN KEY (client_id) REFERENCES client (id) ON DELETE SET NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE `event` DROP FOREIGN KEY FK_3BAE0AA719EB6921');
        $this->addSql('DROP TABLE `event`');
    }
}
