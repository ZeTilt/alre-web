<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260214104850 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE client_seo_report CHANGE period_start period_start DATE NOT NULL, CHANGE period_end period_end DATE NOT NULL, CHANGE generated_at generated_at DATETIME NOT NULL, CHANGE sent_at sent_at DATETIME DEFAULT NULL');
        $this->addSql('ALTER TABLE client_seo_report RENAME INDEX idx_client_seo_report_site TO IDX_D1EF5229A480523B');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE client_seo_report CHANGE period_start period_start DATE NOT NULL COMMENT \'(DC2Type:date_immutable)\', CHANGE period_end period_end DATE NOT NULL COMMENT \'(DC2Type:date_immutable)\', CHANGE generated_at generated_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', CHANGE sent_at sent_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\'');
        $this->addSql('ALTER TABLE client_seo_report RENAME INDEX idx_d1ef5229a480523b TO IDX_CLIENT_SEO_REPORT_SITE');
    }
}
