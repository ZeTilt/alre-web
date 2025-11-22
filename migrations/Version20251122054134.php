<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251122054134 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE company ADD home_portrait_photo_credit VARCHAR(255) DEFAULT NULL, ADD home_portrait_photo_credit_url VARCHAR(255) DEFAULT NULL, ADD about_wide_photo_credit VARCHAR(255) DEFAULT NULL, ADD about_wide_photo_credit_url VARCHAR(255) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE company DROP home_portrait_photo_credit, DROP home_portrait_photo_credit_url, DROP about_wide_photo_credit, DROP about_wide_photo_credit_url');
    }
}
