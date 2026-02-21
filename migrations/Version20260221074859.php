<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260221074859 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE seo_sync_log (id INT AUTO_INCREMENT NOT NULL, command VARCHAR(50) NOT NULL, started_at DATETIME NOT NULL, finished_at DATETIME DEFAULT NULL, duration_ms INT DEFAULT NULL, status VARCHAR(20) NOT NULL, details JSON DEFAULT NULL, error_message LONGTEXT DEFAULT NULL, client_site_id INT DEFAULT NULL, INDEX IDX_D0D6B054A480523B (client_site_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE seo_sync_log ADD CONSTRAINT FK_D0D6B054A480523B FOREIGN KEY (client_site_id) REFERENCES client_site (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE seo_sync_log DROP FOREIGN KEY FK_D0D6B054A480523B');
        $this->addSql('DROP TABLE seo_sync_log');
    }
}
