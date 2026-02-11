<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260211140000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add lastOptimizedAt field to city';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE city ADD last_optimized_at DATETIME DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE city DROP last_optimized_at');
    }
}
