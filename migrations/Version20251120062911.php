<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251120062911 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add auto-entrepreneur parameters to Company entity for dashboard (plafond CA, taux cotisations URSSAF, objectifs)';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE company ADD plafond_ca_annuel NUMERIC(10, 2) DEFAULT NULL, ADD taux_cotisations_urssaf NUMERIC(5, 2) DEFAULT NULL, ADD objectif_ca_mensuel NUMERIC(10, 2) DEFAULT NULL, ADD objectif_ca_annuel NUMERIC(10, 2) DEFAULT NULL, ADD annee_fiscale_en_cours INT DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE company DROP plafond_ca_annuel, DROP taux_cotisations_urssaf, DROP objectif_ca_mensuel, DROP objectif_ca_annuel, DROP annee_fiscale_en_cours');
    }
}
