<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251206061640 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE prospect_follow_up ADD type VARCHAR(50) NOT NULL, ADD contact_id INT DEFAULT NULL, DROP priority, CHANGE title subject VARCHAR(255) NOT NULL, CHANGE description content LONGTEXT DEFAULT NULL');
        $this->addSql('ALTER TABLE prospect_follow_up ADD CONSTRAINT FK_835F89AFE7A1254A FOREIGN KEY (contact_id) REFERENCES prospect_contact (id) ON DELETE SET NULL');
        $this->addSql('CREATE INDEX IDX_835F89AFE7A1254A ON prospect_follow_up (contact_id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE prospect_follow_up DROP FOREIGN KEY FK_835F89AFE7A1254A');
        $this->addSql('DROP INDEX IDX_835F89AFE7A1254A ON prospect_follow_up');
        $this->addSql('ALTER TABLE prospect_follow_up ADD priority VARCHAR(20) NOT NULL, DROP type, DROP contact_id, CHANGE subject title VARCHAR(255) NOT NULL, CHANGE content description LONGTEXT DEFAULT NULL');
    }
}
