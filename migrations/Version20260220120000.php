<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260220120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Merge Bing entities into unified SEO tables: add source columns, lastSeenInBing, drop Bing-specific tables';
    }

    public function up(Schema $schema): void
    {
        // Add source column to client_seo_position
        $this->addSql('ALTER TABLE client_seo_position ADD source VARCHAR(10) DEFAULT \'google\' NOT NULL');

        // Drop old unique constraint and create new one with source
        $this->addSql('ALTER TABLE client_seo_position DROP INDEX unique_client_seo_position, ADD UNIQUE INDEX unique_client_seo_position_source (client_seo_keyword_id, date, source)');

        // Add source column to client_seo_daily_total
        $this->addSql('ALTER TABLE client_seo_daily_total ADD source VARCHAR(10) DEFAULT \'google\' NOT NULL');

        // Drop old unique constraint and create new one with source
        $this->addSql('ALTER TABLE client_seo_daily_total DROP INDEX unique_client_seo_daily_total, ADD UNIQUE INDEX unique_client_seo_daily_total_source (client_site_id, date, source)');

        // Add lastSeenInBing to client_seo_keyword
        $this->addSql('ALTER TABLE client_seo_keyword ADD last_seen_in_bing DATETIME DEFAULT NULL');

        // Drop Bing-specific tables (no data to migrate, tables were just created)
        $this->addSql('DROP TABLE IF EXISTS client_bing_position');
        $this->addSql('DROP TABLE IF EXISTS client_bing_keyword');
        $this->addSql('DROP TABLE IF EXISTS client_bing_daily_total');
    }

    public function down(Schema $schema): void
    {
        // Restore Bing tables
        $this->addSql('CREATE TABLE client_bing_keyword (id INT AUTO_INCREMENT NOT NULL, client_site_id INT NOT NULL, keyword VARCHAR(255) NOT NULL, is_active TINYINT(1) DEFAULT 1 NOT NULL, created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', INDEX IDX_bing_kw_site (client_site_id), UNIQUE INDEX unique_client_bing_keyword (client_site_id, keyword), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE client_bing_position (id INT AUTO_INCREMENT NOT NULL, client_bing_keyword_id INT NOT NULL, position DOUBLE PRECISION NOT NULL, clicks INT DEFAULT 0 NOT NULL, impressions INT DEFAULT 0 NOT NULL, date DATE NOT NULL COMMENT \'(DC2Type:date_immutable)\', created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', INDEX IDX_bing_pos_kw (client_bing_keyword_id), UNIQUE INDEX unique_client_bing_position (client_bing_keyword_id, date), INDEX idx_client_bing_position_date (date), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE client_bing_daily_total (id INT AUTO_INCREMENT NOT NULL, client_site_id INT NOT NULL, date DATE NOT NULL COMMENT \'(DC2Type:date_immutable)\', clicks INT DEFAULT 0 NOT NULL, impressions INT DEFAULT 0 NOT NULL, position DOUBLE PRECISION DEFAULT 0 NOT NULL, created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', INDEX IDX_bing_dt_site (client_site_id), UNIQUE INDEX unique_client_bing_daily_total (client_site_id, date), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');

        // Remove new columns
        $this->addSql('ALTER TABLE client_seo_keyword DROP last_seen_in_bing');

        // Restore old unique constraints
        $this->addSql('ALTER TABLE client_seo_daily_total DROP INDEX unique_client_seo_daily_total_source, ADD UNIQUE INDEX unique_client_seo_daily_total (client_site_id, date)');
        $this->addSql('ALTER TABLE client_seo_daily_total DROP source');

        $this->addSql('ALTER TABLE client_seo_position DROP INDEX unique_client_seo_position_source, ADD UNIQUE INDEX unique_client_seo_position (client_seo_keyword_id, date)');
        $this->addSql('ALTER TABLE client_seo_position DROP source');
    }
}
