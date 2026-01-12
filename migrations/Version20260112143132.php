<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260112143132 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE google_review (id INT AUTO_INCREMENT NOT NULL, google_review_id VARCHAR(255) NOT NULL, author_name VARCHAR(255) NOT NULL, rating SMALLINT NOT NULL, comment LONGTEXT DEFAULT NULL, review_date DATETIME NOT NULL, is_approved TINYINT NOT NULL, rejected_at DATETIME DEFAULT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, UNIQUE INDEX UNIQ_E965715EB9F1B03 (google_review_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP TABLE google_review');
    }
}
