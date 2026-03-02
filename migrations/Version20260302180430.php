<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260302180430 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE offer (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(100) NOT NULL, slug VARCHAR(50) NOT NULL, category VARCHAR(30) NOT NULL, price NUMERIC(10, 2) NOT NULL, promo_price NUMERIC(10, 2) DEFAULT NULL, promo_end_date DATETIME DEFAULT NULL, promo_label VARCHAR(100) DEFAULT NULL, is_recurring TINYINT NOT NULL, price_suffix VARCHAR(30) DEFAULT NULL, short_description VARCHAR(255) DEFAULT NULL, is_featured TINYINT NOT NULL, is_active TINYINT NOT NULL, sort_order INT NOT NULL, UNIQUE INDEX UNIQ_29D6873E989D9B62 (slug), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP TABLE offer');
    }
}
