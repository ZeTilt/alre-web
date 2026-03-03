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

        // Seed initial offers
        $this->addSql("INSERT INTO offer (name, slug, category, price, promo_price, promo_end_date, promo_label, is_recurring, price_suffix, short_description, is_featured, is_active, sort_order) VALUES
            ('Page Unique', 'page_unique', 'creation', 697.00, 493.00, '2026-03-31 23:59:59', 'Offre Lancement', 0, NULL, 'Landing page optimisée', 0, 1, 10),
            ('Site Vitrine', 'site_vitrine', 'creation', 1597.00, 1193.00, '2026-03-31 23:59:59', 'Offre Lancement', 0, NULL, 'Site professionnel complet', 1, 1, 20),
            ('Site E-commerce', 'ecommerce', 'creation', 3297.00, 2497.00, '2026-03-31 23:59:59', 'Offre Lancement', 0, NULL, 'Boutique en ligne complète', 0, 1, 30),
            ('Hébergement + Maintenance', 'hebergement_maintenance', 'hebergement', 77.00, 53.00, '2026-03-31 23:59:59', 'Offre Lancement', 1, NULL, 'Hébergement et maintenance tout inclus', 0, 1, 40),
            ('Hébergement Seul', 'hebergement', 'hebergement', 23.00, 13.00, '2026-03-31 23:59:59', 'Offre Lancement', 1, NULL, 'Hébergement performant et sécurisé', 0, 1, 50),
            ('Maintenance Seule', 'maintenance', 'hebergement', 63.00, 47.00, '2026-03-31 23:59:59', 'Offre Lancement', 1, NULL, 'Mises à jour et support technique', 0, 1, 60),
            ('Pack SEO Essentiel', 'seo_essentiel', 'seo', 397.00, 247.00, '2026-03-31 23:59:59', 'Offre Lancement', 0, NULL, 'Audit et optimisation SEO ponctuel', 0, 1, 70),
            ('Pack SEO Visibilité', 'seo_visibilite', 'seo', 197.00, 143.00, '2026-03-31 23:59:59', 'Offre Lancement', 1, NULL, 'Suivi SEO mensuel', 0, 1, 80),
            ('Pack SEO Performance', 'seo_performance', 'seo', 397.00, 227.00, '2026-03-31 23:59:59', 'Offre Lancement', 1, NULL, 'SEO complet avec suivi mensuel', 1, 1, 90),
            ('Optimisation SEO', 'optimisation_seo', 'ponctuel', 347.00, NULL, NULL, NULL, 0, NULL, 'Optimisation SEO ponctuelle', 0, 1, 100),
            ('Optimisation de contenu', 'optimisation_contenu', 'ponctuel', 33.00, NULL, NULL, NULL, 0, '/page', 'Rédaction et optimisation de contenu', 0, 1, 110),
            ('Site multilingue', 'site_multilingue', 'ponctuel', 93.00, NULL, NULL, NULL, 0, '/langue/page', 'Traduction et adaptation du site', 0, 1, 120),
            ('Refonte de site', 'refonte', 'ponctuel', 593.00, NULL, NULL, NULL, 0, NULL, 'Modernisation de site existant', 0, 1, 130)
        ");
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP TABLE offer');
    }
}
