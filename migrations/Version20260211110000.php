<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260211110000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add long description fields per service to city for unique local SEO content';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE city ADD description_developpeur_long LONGTEXT DEFAULT NULL, ADD description_creation_long LONGTEXT DEFAULT NULL, ADD description_agence_long LONGTEXT DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE city DROP description_developpeur_long, DROP description_creation_long, DROP description_agence_long');
    }
}
