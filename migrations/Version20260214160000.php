<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260214160000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Make client_seo_report.client_site_id nullable (for own-site reports)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE client_seo_report MODIFY client_site_id INT DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DELETE FROM client_seo_report WHERE client_site_id IS NULL');
        $this->addSql('ALTER TABLE client_seo_report MODIFY client_site_id INT NOT NULL');
    }
}
