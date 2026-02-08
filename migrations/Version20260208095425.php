<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260208095425 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE client_seo_daily_total (id INT AUTO_INCREMENT NOT NULL, date DATE NOT NULL, clicks INT NOT NULL, impressions INT NOT NULL, position DOUBLE PRECISION NOT NULL, created_at DATETIME NOT NULL, client_site_id INT NOT NULL, INDEX IDX_FB4FF4F5A480523B (client_site_id), UNIQUE INDEX unique_client_seo_daily_total (client_site_id, date), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE client_seo_import (id INT AUTO_INCREMENT NOT NULL, type VARCHAR(30) NOT NULL, period_start DATE DEFAULT NULL, period_end DATE DEFAULT NULL, original_filename VARCHAR(255) NOT NULL, rows_imported INT NOT NULL, rows_skipped INT NOT NULL, imported_at DATETIME NOT NULL, status VARCHAR(20) NOT NULL, error_message LONGTEXT DEFAULT NULL, client_site_id INT NOT NULL, INDEX IDX_888EEBB0A480523B (client_site_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE client_seo_keyword (id INT AUTO_INCREMENT NOT NULL, keyword VARCHAR(255) NOT NULL, is_active TINYINT NOT NULL, created_at DATETIME NOT NULL, client_site_id INT NOT NULL, INDEX IDX_F2E16E4BA480523B (client_site_id), UNIQUE INDEX unique_client_seo_keyword (client_site_id, keyword), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE client_seo_page (id INT AUTO_INCREMENT NOT NULL, url VARCHAR(2000) NOT NULL, url_hash VARCHAR(64) NOT NULL, clicks INT NOT NULL, impressions INT NOT NULL, ctr DOUBLE PRECISION NOT NULL, position DOUBLE PRECISION NOT NULL, date DATE NOT NULL, client_site_id INT NOT NULL, INDEX IDX_C63A9634A480523B (client_site_id), INDEX idx_client_seo_page_url_hash (url_hash), UNIQUE INDEX unique_client_seo_page (client_site_id, url_hash, date), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE client_seo_position (id INT AUTO_INCREMENT NOT NULL, position DOUBLE PRECISION NOT NULL, clicks INT NOT NULL, impressions INT NOT NULL, date DATE NOT NULL, created_at DATETIME NOT NULL, client_seo_keyword_id INT NOT NULL, INDEX IDX_1681E7D636FD3324 (client_seo_keyword_id), INDEX idx_client_seo_position_date (date), UNIQUE INDEX unique_client_seo_position (client_seo_keyword_id, date), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE client_site (id INT AUTO_INCREMENT NOT NULL, url VARCHAR(500) NOT NULL, name VARCHAR(255) NOT NULL, is_active TINYINT NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME DEFAULT NULL, client_id INT NOT NULL, INDEX IDX_B8DFCA8619EB6921 (client_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE client_seo_daily_total ADD CONSTRAINT FK_FB4FF4F5A480523B FOREIGN KEY (client_site_id) REFERENCES client_site (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE client_seo_import ADD CONSTRAINT FK_888EEBB0A480523B FOREIGN KEY (client_site_id) REFERENCES client_site (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE client_seo_keyword ADD CONSTRAINT FK_F2E16E4BA480523B FOREIGN KEY (client_site_id) REFERENCES client_site (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE client_seo_page ADD CONSTRAINT FK_C63A9634A480523B FOREIGN KEY (client_site_id) REFERENCES client_site (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE client_seo_position ADD CONSTRAINT FK_1681E7D636FD3324 FOREIGN KEY (client_seo_keyword_id) REFERENCES client_seo_keyword (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE client_site ADD CONSTRAINT FK_B8DFCA8619EB6921 FOREIGN KEY (client_id) REFERENCES client (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE seo_keyword CHANGE deactivated_at deactivated_at DATETIME DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE client_seo_daily_total DROP FOREIGN KEY FK_FB4FF4F5A480523B');
        $this->addSql('ALTER TABLE client_seo_import DROP FOREIGN KEY FK_888EEBB0A480523B');
        $this->addSql('ALTER TABLE client_seo_keyword DROP FOREIGN KEY FK_F2E16E4BA480523B');
        $this->addSql('ALTER TABLE client_seo_page DROP FOREIGN KEY FK_C63A9634A480523B');
        $this->addSql('ALTER TABLE client_seo_position DROP FOREIGN KEY FK_1681E7D636FD3324');
        $this->addSql('ALTER TABLE client_site DROP FOREIGN KEY FK_B8DFCA8619EB6921');
        $this->addSql('DROP TABLE client_seo_daily_total');
        $this->addSql('DROP TABLE client_seo_import');
        $this->addSql('DROP TABLE client_seo_keyword');
        $this->addSql('DROP TABLE client_seo_page');
        $this->addSql('DROP TABLE client_seo_position');
        $this->addSql('DROP TABLE client_site');
        $this->addSql('ALTER TABLE seo_keyword CHANGE deactivated_at deactivated_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\'');
    }
}
