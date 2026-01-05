<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260105093224 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Remove expense and expense_generation tables (feature removed)';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE expense_generation DROP FOREIGN KEY `FK_633253327E2F2B24`');
        $this->addSql('ALTER TABLE expense_generation DROP FOREIGN KEY `FK_63325332D15E9EB4`');
        $this->addSql('DROP TABLE expense');
        $this->addSql('DROP TABLE expense_generation');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE expense (id INT AUTO_INCREMENT NOT NULL, title VARCHAR(255) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_0900_ai_ci`, description LONGTEXT CHARACTER SET utf8mb4 DEFAULT NULL COLLATE `utf8mb4_0900_ai_ci`, amount NUMERIC(10, 2) NOT NULL, date_expense DATE NOT NULL, category VARCHAR(50) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_0900_ai_ci`, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, recurrence VARCHAR(50) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_0900_ai_ci`, start_date DATE DEFAULT NULL, end_date DATE DEFAULT NULL, is_active TINYINT NOT NULL, PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_0900_ai_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->addSql('CREATE TABLE expense_generation (id INT AUTO_INCREMENT NOT NULL, generated_for_date DATE NOT NULL, generated_at DATETIME NOT NULL, template_expense_id INT NOT NULL, generated_expense_id INT DEFAULT NULL, INDEX IDX_633253327E2F2B24 (generated_expense_id), UNIQUE INDEX unique_template_date (template_expense_id, generated_for_date), INDEX IDX_63325332D15E9EB4 (template_expense_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_0900_ai_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->addSql('ALTER TABLE expense_generation ADD CONSTRAINT `FK_633253327E2F2B24` FOREIGN KEY (generated_expense_id) REFERENCES expense (id) ON UPDATE NO ACTION ON DELETE SET NULL');
        $this->addSql('ALTER TABLE expense_generation ADD CONSTRAINT `FK_63325332D15E9EB4` FOREIGN KEY (template_expense_id) REFERENCES expense (id) ON UPDATE NO ACTION ON DELETE CASCADE');
    }
}
