<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251212143122 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add additionalInfo field to devis and facture tables';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE devis ADD additional_info LONGTEXT DEFAULT NULL');
        $this->addSql('ALTER TABLE facture ADD additional_info LONGTEXT DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE devis DROP additional_info');
        $this->addSql('ALTER TABLE facture DROP additional_info');
    }
}
