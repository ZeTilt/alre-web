<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251117060923 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE partner (
            id INT AUTO_INCREMENT NOT NULL,
            name VARCHAR(255) NOT NULL,
            url VARCHAR(500) DEFAULT NULL,
            email VARCHAR(255) DEFAULT NULL,
            phone VARCHAR(50) DEFAULT NULL,
            domains JSON NOT NULL,
            logo VARCHAR(255) DEFAULT NULL,
            is_active TINYINT(1) NOT NULL,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            PRIMARY KEY(id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');

        $this->addSql('CREATE TABLE project_partner (
            id INT AUTO_INCREMENT NOT NULL,
            selected_domains JSON NOT NULL,
            created_at DATETIME NOT NULL,
            project_id INT NOT NULL,
            partner_id INT NOT NULL,
            INDEX IDX_A7353273166D1F9C (project_id),
            INDEX IDX_A73532739393F8FE (partner_id),
            PRIMARY KEY(id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');

        $this->addSql('ALTER TABLE project_partner ADD CONSTRAINT FK_A7353273166D1F9C FOREIGN KEY (project_id) REFERENCES project (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE project_partner ADD CONSTRAINT FK_A73532739393F8FE FOREIGN KEY (partner_id) REFERENCES partner (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE project_partner DROP FOREIGN KEY FK_A7353273166D1F9C');
        $this->addSql('ALTER TABLE project_partner DROP FOREIGN KEY FK_A73532739393F8FE');
        $this->addSql('DROP TABLE partner');
        $this->addSql('DROP TABLE project_partner');
    }
}
