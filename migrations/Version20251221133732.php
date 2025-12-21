<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251221133732 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE devis CHANGE acompte_verse acompte_verse TINYINT NOT NULL');
        $this->addSql('ALTER TABLE facture ADD acompte_paye TINYINT NOT NULL DEFAULT 1');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE devis CHANGE acompte_verse acompte_verse TINYINT DEFAULT 1 NOT NULL');
        $this->addSql('ALTER TABLE facture DROP acompte_paye');
    }
}
