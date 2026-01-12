<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260112140130 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE seo_keyword (id INT AUTO_INCREMENT NOT NULL, keyword VARCHAR(255) NOT NULL, target_url VARCHAR(500) DEFAULT NULL, is_active TINYINT NOT NULL, last_sync_at DATETIME DEFAULT NULL, created_at DATETIME NOT NULL, PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE seo_position (id INT AUTO_INCREMENT NOT NULL, position DOUBLE PRECISION NOT NULL, clicks INT NOT NULL, impressions INT NOT NULL, date DATETIME NOT NULL, created_at DATETIME NOT NULL, keyword_id INT NOT NULL, INDEX IDX_A78B45D7115D4552 (keyword_id), INDEX idx_seo_position_date (date), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE seo_position ADD CONSTRAINT FK_A78B45D7115D4552 FOREIGN KEY (keyword_id) REFERENCES seo_keyword (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE seo_position DROP FOREIGN KEY FK_A78B45D7115D4552');
        $this->addSql('DROP TABLE seo_keyword');
        $this->addSql('DROP TABLE seo_position');
    }
}
