<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251120104725 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE expense_generation (id INT AUTO_INCREMENT NOT NULL, generated_for_date DATE NOT NULL, generated_at DATETIME NOT NULL, template_expense_id INT NOT NULL, generated_expense_id INT DEFAULT NULL, INDEX IDX_63325332D15E9EB4 (template_expense_id), INDEX IDX_633253327E2F2B24 (generated_expense_id), UNIQUE INDEX unique_template_date (template_expense_id, generated_for_date), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE expense_generation ADD CONSTRAINT FK_63325332D15E9EB4 FOREIGN KEY (template_expense_id) REFERENCES expense (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE expense_generation ADD CONSTRAINT FK_633253327E2F2B24 FOREIGN KEY (generated_expense_id) REFERENCES expense (id) ON DELETE SET NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE expense_generation DROP FOREIGN KEY FK_63325332D15E9EB4');
        $this->addSql('ALTER TABLE expense_generation DROP FOREIGN KEY FK_633253327E2F2B24');
        $this->addSql('DROP TABLE expense_generation');
    }
}
