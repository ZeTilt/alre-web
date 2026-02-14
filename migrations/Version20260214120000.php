<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260214120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add relevanceScore to ClientSeoKeyword + populate from relevanceLevel';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE client_seo_keyword ADD relevance_score SMALLINT DEFAULT 0 NOT NULL');

        // Populate from existing relevanceLevel
        $this->addSql("UPDATE client_seo_keyword SET relevance_score = 5 WHERE relevance_level = 'high'");
        $this->addSql("UPDATE client_seo_keyword SET relevance_score = 3 WHERE relevance_level = 'medium'");
        $this->addSql("UPDATE client_seo_keyword SET relevance_score = 1 WHERE relevance_level = 'low'");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE client_seo_keyword DROP relevance_score');
    }
}
