<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260216120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add 4th service (referencement-local) fields to city + Create department_page table with seed data';
    }

    public function up(Schema $schema): void
    {
        // 4th service fields on city
        $this->addSql('ALTER TABLE city ADD title_referencement VARCHAR(70) DEFAULT NULL');
        $this->addSql('ALTER TABLE city ADD description_referencement LONGTEXT DEFAULT NULL');
        $this->addSql('ALTER TABLE city ADD description_referencement_long LONGTEXT DEFAULT NULL');

        // Department page table
        $this->addSql('CREATE TABLE department_page (
            id INT AUTO_INCREMENT NOT NULL,
            name VARCHAR(100) NOT NULL,
            slug VARCHAR(100) NOT NULL,
            number VARCHAR(5) NOT NULL,
            description LONGTEXT NOT NULL,
            title_developpeur VARCHAR(70) DEFAULT NULL,
            title_creation VARCHAR(70) DEFAULT NULL,
            title_agence VARCHAR(70) DEFAULT NULL,
            title_referencement VARCHAR(70) DEFAULT NULL,
            description_developpeur LONGTEXT DEFAULT NULL,
            description_creation LONGTEXT DEFAULT NULL,
            description_agence LONGTEXT DEFAULT NULL,
            description_referencement LONGTEXT DEFAULT NULL,
            description_developpeur_long LONGTEXT DEFAULT NULL,
            description_creation_long LONGTEXT DEFAULT NULL,
            description_agence_long LONGTEXT DEFAULT NULL,
            description_referencement_long LONGTEXT DEFAULT NULL,
            last_optimized_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\',
            is_active TINYINT(1) NOT NULL DEFAULT 1,
            created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\',
            updated_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\',
            UNIQUE INDEX UNIQ_DEPT_SLUG (slug),
            PRIMARY KEY(id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');

        // Seed 4 départements bretons
        $this->addSql("INSERT INTO department_page (name, slug, number, description, is_active, created_at) VALUES
            ('Morbihan', 'morbihan', '56', 'Votre partenaire web dans le Morbihan. Basé à Auray, j''accompagne les artisans et TPE du département dans leur présence en ligne.', 1, NOW()),
            ('Finistère', 'finistere', '29', 'Votre partenaire web dans le Finistère. De Brest à Quimper, j''accompagne les professionnels finistériens dans leur transformation digitale.', 1, NOW()),
            ('Côtes-d''Armor', 'cotes-d-armor', '22', 'Votre partenaire web dans les Côtes-d''Armor. De Saint-Brieuc à Lannion, j''accompagne les entreprises costarmoricaines.', 1, NOW()),
            ('Ille-et-Vilaine', 'ille-et-vilaine', '35', 'Votre partenaire web en Ille-et-Vilaine. De Rennes à Saint-Malo, j''accompagne les professionnels du département.', 1, NOW())
        ");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE department_page');
        $this->addSql('ALTER TABLE city DROP title_referencement');
        $this->addSql('ALTER TABLE city DROP description_referencement');
        $this->addSql('ALTER TABLE city DROP description_referencement_long');
    }
}
