<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260129074755 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE blocked_ip (id INT AUTO_INCREMENT NOT NULL, ip_address VARCHAR(45) NOT NULL, reason VARCHAR(50) NOT NULL, description LONGTEXT DEFAULT NULL, is_automatic TINYINT NOT NULL, is_active TINYINT NOT NULL, created_at DATETIME NOT NULL, expires_at DATETIME DEFAULT NULL, hit_count INT NOT NULL, last_hit_at DATETIME DEFAULT NULL, trigger_data JSON DEFAULT NULL, UNIQUE INDEX UNIQ_3B25854B22FFD58C (ip_address), INDEX idx_blocked_ip_address (ip_address), INDEX idx_blocked_ip_expires (expires_at), INDEX idx_blocked_ip_active (is_active), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE security_log (id INT AUTO_INCREMENT NOT NULL, ip_address VARCHAR(45) NOT NULL, request_url LONGTEXT NOT NULL, request_method VARCHAR(10) NOT NULL, status_code SMALLINT NOT NULL, user_agent LONGTEXT DEFAULT NULL, referer LONGTEXT DEFAULT NULL, created_at DATETIME NOT NULL, extra_data JSON DEFAULT NULL, INDEX idx_security_log_ip (ip_address), INDEX idx_security_log_created (created_at), INDEX idx_security_log_status (status_code), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE seo_keyword CHANGE source source VARCHAR(20) NOT NULL, CHANGE relevance_level relevance_level VARCHAR(20) NOT NULL, CHANGE last_seen_in_gsc last_seen_in_gsc DATETIME DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP TABLE blocked_ip');
        $this->addSql('DROP TABLE security_log');
        $this->addSql('ALTER TABLE seo_keyword CHANGE source source VARCHAR(20) DEFAULT \'manual\' NOT NULL, CHANGE relevance_level relevance_level VARCHAR(20) DEFAULT \'medium\' NOT NULL, CHANGE last_seen_in_gsc last_seen_in_gsc DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\'');
    }
}
