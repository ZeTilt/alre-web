<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251221142302 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE facture DROP INDEX UNIQ_FE86641041DEFADA, ADD INDEX IDX_FE86641041DEFADA (devis_id)');
        $this->addSql('ALTER TABLE facture CHANGE type type VARCHAR(20) NOT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE facture DROP INDEX IDX_FE86641041DEFADA, ADD UNIQUE INDEX UNIQ_FE86641041DEFADA (devis_id)');
        $this->addSql('ALTER TABLE facture CHANGE type type VARCHAR(20) DEFAULT \'standard\' NOT NULL');
    }
}
