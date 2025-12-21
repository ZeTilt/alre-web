<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251221142044 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE facture ADD type VARCHAR(20) NOT NULL DEFAULT \'standard\', ADD facture_acompte_id INT DEFAULT NULL, CHANGE acompte_paye acompte_paye TINYINT NOT NULL');
        $this->addSql('ALTER TABLE facture ADD CONSTRAINT FK_FE866410237C9AB2 FOREIGN KEY (facture_acompte_id) REFERENCES facture (id)');
        $this->addSql('CREATE INDEX IDX_FE866410237C9AB2 ON facture (facture_acompte_id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE facture DROP FOREIGN KEY FK_FE866410237C9AB2');
        $this->addSql('DROP INDEX IDX_FE866410237C9AB2 ON facture');
        $this->addSql('ALTER TABLE facture DROP type, DROP facture_acompte_id, CHANGE acompte_paye acompte_paye TINYINT DEFAULT 1 NOT NULL');
    }
}
