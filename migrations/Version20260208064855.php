<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260208064855 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add deactivated_at to seo_keyword for auto-deactivation/reactivation tracking';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE seo_keyword ADD deactivated_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\'');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE seo_keyword DROP deactivated_at');
    }
}
