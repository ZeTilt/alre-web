<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260214120100 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create client_seo_report table';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE client_seo_report (
            id INT AUTO_INCREMENT NOT NULL,
            client_site_id INT NOT NULL,
            period_start DATE NOT NULL COMMENT \'(DC2Type:date_immutable)\',
            period_end DATE NOT NULL COMMENT \'(DC2Type:date_immutable)\',
            actions_html LONGTEXT DEFAULT NULL,
            next_actions_html LONGTEXT DEFAULT NULL,
            notes_html LONGTEXT DEFAULT NULL,
            status VARCHAR(10) DEFAULT \'draft\' NOT NULL,
            generated_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\',
            sent_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\',
            report_data JSON DEFAULT NULL,
            health_score SMALLINT DEFAULT 0 NOT NULL,
            INDEX IDX_CLIENT_SEO_REPORT_SITE (client_site_id),
            PRIMARY KEY(id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');

        $this->addSql('ALTER TABLE client_seo_report ADD CONSTRAINT FK_CLIENT_SEO_REPORT_SITE FOREIGN KEY (client_site_id) REFERENCES client_site (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE client_seo_report');
    }
}
