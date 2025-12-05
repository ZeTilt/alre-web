<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251205155454 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE prospect (id INT AUTO_INCREMENT NOT NULL, company_name VARCHAR(255) NOT NULL, website VARCHAR(500) DEFAULT NULL, activity VARCHAR(255) DEFAULT NULL, city VARCHAR(255) DEFAULT NULL, postal_code VARCHAR(100) DEFAULT NULL, country VARCHAR(100) DEFAULT NULL, source VARCHAR(50) NOT NULL, source_detail VARCHAR(255) DEFAULT NULL, status VARCHAR(50) NOT NULL, notes LONGTEXT DEFAULT NULL, estimated_value NUMERIC(10, 2) DEFAULT NULL, created_at DATETIME NOT NULL, updated_at DATETIME DEFAULT NULL, last_contact_at DATETIME DEFAULT NULL, converted_client_id INT DEFAULT NULL, linked_devis_id INT DEFAULT NULL, INDEX IDX_C9CE8C7D5AA408DD (converted_client_id), INDEX IDX_C9CE8C7D2FDEB405 (linked_devis_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE prospect_contact (id INT AUTO_INCREMENT NOT NULL, first_name VARCHAR(255) NOT NULL, last_name VARCHAR(255) NOT NULL, email VARCHAR(255) DEFAULT NULL, phone VARCHAR(20) DEFAULT NULL, role VARCHAR(255) DEFAULT NULL, linkedin_url VARCHAR(500) DEFAULT NULL, is_primary TINYINT NOT NULL, created_at DATETIME NOT NULL, prospect_id INT NOT NULL, INDEX IDX_8F2EF6ECD182060A (prospect_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE prospect_follow_up (id INT AUTO_INCREMENT NOT NULL, title VARCHAR(255) NOT NULL, description LONGTEXT DEFAULT NULL, due_at DATE NOT NULL, priority VARCHAR(20) NOT NULL, is_completed TINYINT NOT NULL, completed_at DATETIME DEFAULT NULL, created_at DATETIME NOT NULL, prospect_id INT NOT NULL, INDEX IDX_835F89AFD182060A (prospect_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE prospect_interaction (id INT AUTO_INCREMENT NOT NULL, type VARCHAR(50) NOT NULL, direction VARCHAR(50) NOT NULL, subject VARCHAR(255) NOT NULL, content LONGTEXT DEFAULT NULL, created_at DATETIME NOT NULL, scheduled_at DATETIME DEFAULT NULL, notes LONGTEXT DEFAULT NULL, prospect_id INT NOT NULL, contact_id INT DEFAULT NULL, INDEX IDX_DC3CE31AD182060A (prospect_id), INDEX IDX_DC3CE31AE7A1254A (contact_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE prospect ADD CONSTRAINT FK_C9CE8C7D5AA408DD FOREIGN KEY (converted_client_id) REFERENCES client (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE prospect ADD CONSTRAINT FK_C9CE8C7D2FDEB405 FOREIGN KEY (linked_devis_id) REFERENCES devis (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE prospect_contact ADD CONSTRAINT FK_8F2EF6ECD182060A FOREIGN KEY (prospect_id) REFERENCES prospect (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE prospect_follow_up ADD CONSTRAINT FK_835F89AFD182060A FOREIGN KEY (prospect_id) REFERENCES prospect (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE prospect_interaction ADD CONSTRAINT FK_DC3CE31AD182060A FOREIGN KEY (prospect_id) REFERENCES prospect (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE prospect_interaction ADD CONSTRAINT FK_DC3CE31AE7A1254A FOREIGN KEY (contact_id) REFERENCES prospect_contact (id) ON DELETE SET NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE prospect DROP FOREIGN KEY FK_C9CE8C7D5AA408DD');
        $this->addSql('ALTER TABLE prospect DROP FOREIGN KEY FK_C9CE8C7D2FDEB405');
        $this->addSql('ALTER TABLE prospect_contact DROP FOREIGN KEY FK_8F2EF6ECD182060A');
        $this->addSql('ALTER TABLE prospect_follow_up DROP FOREIGN KEY FK_835F89AFD182060A');
        $this->addSql('ALTER TABLE prospect_interaction DROP FOREIGN KEY FK_DC3CE31AD182060A');
        $this->addSql('ALTER TABLE prospect_interaction DROP FOREIGN KEY FK_DC3CE31AE7A1254A');
        $this->addSql('DROP TABLE prospect');
        $this->addSql('DROP TABLE prospect_contact');
        $this->addSql('DROP TABLE prospect_follow_up');
        $this->addSql('DROP TABLE prospect_interaction');
    }
}
