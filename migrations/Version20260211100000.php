<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260211100000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add scheduling fields to client_site and lastOptimizedAt/relevanceLevel to client_seo_keyword';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE client_site ADD import_day SMALLINT DEFAULT NULL, ADD import_slot VARCHAR(10) DEFAULT NULL, ADD report_week_of_month SMALLINT DEFAULT NULL, ADD report_day_of_week SMALLINT DEFAULT NULL, ADD report_slot VARCHAR(10) DEFAULT NULL');
        $this->addSql('ALTER TABLE client_seo_keyword ADD last_optimized_at DATETIME DEFAULT NULL, ADD relevance_level VARCHAR(10) NOT NULL DEFAULT \'medium\'');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE client_site DROP import_day, DROP import_slot, DROP report_week_of_month, DROP report_day_of_week, DROP report_slot');
        $this->addSql('ALTER TABLE client_seo_keyword DROP last_optimized_at, DROP relevance_level');
    }
}
