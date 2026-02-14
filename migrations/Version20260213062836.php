<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260213062836 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add custom SEO title fields (titleDeveloppeur, titleCreation, titleAgence) to city table';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE city ADD title_developpeur VARCHAR(70) DEFAULT NULL, ADD title_creation VARCHAR(70) DEFAULT NULL, ADD title_agence VARCHAR(70) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE city DROP title_developpeur, DROP title_creation, DROP title_agence');
    }
}
