<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260220065619 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add Bing Webmaster Tools: BingConfig, ClientBing entities, SeoDailyTotal source, ClientSite bingEnabled';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE bing_config (id INT AUTO_INCREMENT NOT NULL, api_key VARCHAR(255) DEFAULT NULL, index_now_key VARCHAR(255) DEFAULT NULL, updated_at DATETIME NOT NULL, PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE client_bing_daily_total (id INT AUTO_INCREMENT NOT NULL, date DATE NOT NULL, clicks INT NOT NULL, impressions INT NOT NULL, position DOUBLE PRECISION NOT NULL, created_at DATETIME NOT NULL, client_site_id INT NOT NULL, INDEX IDX_117FC672A480523B (client_site_id), UNIQUE INDEX unique_client_bing_daily_total (client_site_id, date), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE client_bing_keyword (id INT AUTO_INCREMENT NOT NULL, keyword VARCHAR(255) NOT NULL, is_active TINYINT NOT NULL, created_at DATETIME NOT NULL, client_site_id INT NOT NULL, INDEX IDX_99FAA9C5A480523B (client_site_id), UNIQUE INDEX unique_client_bing_keyword (client_site_id, keyword), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE client_bing_position (id INT AUTO_INCREMENT NOT NULL, position DOUBLE PRECISION NOT NULL, clicks INT NOT NULL, impressions INT NOT NULL, date DATE NOT NULL, created_at DATETIME NOT NULL, client_bing_keyword_id INT NOT NULL, INDEX IDX_1CEA5236D3888DA0 (client_bing_keyword_id), INDEX idx_client_bing_position_date (date), UNIQUE INDEX unique_client_bing_position (client_bing_keyword_id, date), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE client_bing_daily_total ADD CONSTRAINT FK_117FC672A480523B FOREIGN KEY (client_site_id) REFERENCES client_site (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE client_bing_keyword ADD CONSTRAINT FK_99FAA9C5A480523B FOREIGN KEY (client_site_id) REFERENCES client_site (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE client_bing_position ADD CONSTRAINT FK_1CEA5236D3888DA0 FOREIGN KEY (client_bing_keyword_id) REFERENCES client_bing_keyword (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE client_site ADD bing_enabled TINYINT DEFAULT 0 NOT NULL');
        $this->addSql('DROP INDEX unique_daily_total_date ON seo_daily_total');
        $this->addSql('ALTER TABLE seo_daily_total ADD source VARCHAR(10) DEFAULT \'google\' NOT NULL');
        $this->addSql('CREATE UNIQUE INDEX unique_daily_total_date_source ON seo_daily_total (date, source)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE client_bing_daily_total DROP FOREIGN KEY FK_117FC672A480523B');
        $this->addSql('ALTER TABLE client_bing_keyword DROP FOREIGN KEY FK_99FAA9C5A480523B');
        $this->addSql('ALTER TABLE client_bing_position DROP FOREIGN KEY FK_1CEA5236D3888DA0');
        $this->addSql('DROP TABLE bing_config');
        $this->addSql('DROP TABLE client_bing_daily_total');
        $this->addSql('DROP TABLE client_bing_keyword');
        $this->addSql('DROP TABLE client_bing_position');
        $this->addSql('ALTER TABLE client_site DROP bing_enabled');
        $this->addSql('DROP INDEX unique_daily_total_date_source ON seo_daily_total');
        $this->addSql('ALTER TABLE seo_daily_total DROP source');
        $this->addSql('CREATE UNIQUE INDEX unique_daily_total_date ON seo_daily_total (date)');
    }
}
