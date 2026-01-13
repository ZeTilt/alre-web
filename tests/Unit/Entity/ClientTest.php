<?php

namespace App\Tests\Unit\Entity;

use App\Entity\Client;
use App\Entity\Devis;
use App\Entity\Facture;
use App\Entity\Project;
use PHPUnit\Framework\TestCase;

/**
 * Tests unitaires pour l'entité Client.
 *
 * Couvre:
 * - getDisplayName() - nom affiché selon le type
 * - getFullAddress() - adresse complète formatée
 * - getContactName() - nom complet du contact
 * - Collections: devis, factures, projects
 */
class ClientTest extends TestCase
{
    // ===== CONSTRUCTION TESTS =====

    public function testDefaultValues(): void
    {
        $client = new Client();

        $this->assertEquals(Client::TYPE_ENTREPRISE, $client->getType());
        $this->assertEquals('France', $client->getCountry());
        $this->assertTrue($client->isActive());
        $this->assertNotNull($client->getCreatedAt());
    }

    // ===== FLUENT SETTERS TESTS =====

    public function testFluentSetters(): void
    {
        $client = new Client();

        $result = $client->setName('Acme Inc')
            ->setType(Client::TYPE_ASSOCIATION)
            ->setCompanyName('Acme Corporation')
            ->setSiret('12345678901234')
            ->setVatNumber('FR12345678901')
            ->setContactFirstName('Jean')
            ->setContactLastName('Dupont')
            ->setEmail('jean@acme.com')
            ->setPhone('0601020304')
            ->setUrl('https://acme.com')
            ->setAddress('123 rue de Paris')
            ->setPostalCode('75001')
            ->setCity('Paris')
            ->setCountry('France')
            ->setNotes('Client VIP')
            ->setIsActive(false);

        $this->assertSame($client, $result);
        $this->assertEquals('Acme Inc', $client->getName());
        $this->assertEquals(Client::TYPE_ASSOCIATION, $client->getType());
        $this->assertEquals('Acme Corporation', $client->getCompanyName());
        $this->assertEquals('12345678901234', $client->getSiret());
        $this->assertEquals('FR12345678901', $client->getVatNumber());
        $this->assertEquals('Jean', $client->getContactFirstName());
        $this->assertEquals('Dupont', $client->getContactLastName());
        $this->assertEquals('jean@acme.com', $client->getEmail());
        $this->assertEquals('0601020304', $client->getPhone());
        $this->assertEquals('https://acme.com', $client->getUrl());
        $this->assertEquals('123 rue de Paris', $client->getAddress());
        $this->assertEquals('75001', $client->getPostalCode());
        $this->assertEquals('Paris', $client->getCity());
        $this->assertEquals('France', $client->getCountry());
        $this->assertEquals('Client VIP', $client->getNotes());
        $this->assertFalse($client->isActive());
    }

    // ===== GET DISPLAY NAME TESTS =====

    public function testGetDisplayNameReturnsCompanyNameForEntreprise(): void
    {
        $client = new Client();
        $client->setType(Client::TYPE_ENTREPRISE);
        $client->setName('Jean Dupont');
        $client->setCompanyName('Acme Corporation');

        $this->assertEquals('Acme Corporation', $client->getDisplayName());
    }

    public function testGetDisplayNameReturnsNameWhenNoCompanyName(): void
    {
        $client = new Client();
        $client->setType(Client::TYPE_ENTREPRISE);
        $client->setName('Jean Dupont');
        $client->setCompanyName(null);

        $this->assertEquals('Jean Dupont', $client->getDisplayName());
    }

    public function testGetDisplayNameReturnsNameForAssociation(): void
    {
        $client = new Client();
        $client->setType(Client::TYPE_ASSOCIATION);
        $client->setName('Association Sport');
        $client->setCompanyName('Autre nom');

        $this->assertEquals('Association Sport', $client->getDisplayName());
    }

    // ===== GET FULL ADDRESS TESTS =====

    public function testGetFullAddressWithAllFields(): void
    {
        $client = new Client();
        $client->setAddress('123 rue de Paris');
        $client->setPostalCode('75001');
        $client->setCity('Paris');
        $client->setCountry('Belgique');

        $this->assertEquals('123 rue de Paris, 75001 Paris, Belgique', $client->getFullAddress());
    }

    public function testGetFullAddressExcludesFrance(): void
    {
        $client = new Client();
        $client->setAddress('123 rue de Paris');
        $client->setPostalCode('75001');
        $client->setCity('Paris');
        $client->setCountry('France');

        $this->assertEquals('123 rue de Paris, 75001 Paris', $client->getFullAddress());
    }

    public function testGetFullAddressWithOnlyAddress(): void
    {
        $client = new Client();
        $client->setAddress('123 rue de Paris');
        $client->setPostalCode(null);
        $client->setCity(null);
        $client->setCountry('France');

        $this->assertEquals('123 rue de Paris', $client->getFullAddress());
    }

    public function testGetFullAddressWithOnlyPostalCodeAndCity(): void
    {
        $client = new Client();
        $client->setAddress(null);
        $client->setPostalCode('75001');
        $client->setCity('Paris');
        $client->setCountry('France');

        $this->assertEquals('75001 Paris', $client->getFullAddress());
    }

    public function testGetFullAddressWithOnlyCity(): void
    {
        $client = new Client();
        $client->setAddress(null);
        $client->setPostalCode(null);
        $client->setCity('Paris');
        $client->setCountry('France');

        $this->assertEquals('Paris', $client->getFullAddress());
    }

    public function testGetFullAddressEmptyWhenNoData(): void
    {
        $client = new Client();
        $client->setAddress(null);
        $client->setPostalCode(null);
        $client->setCity(null);
        $client->setCountry('France');

        $this->assertEquals('', $client->getFullAddress());
    }

    // ===== GET CONTACT NAME TESTS =====

    public function testGetContactNameWithBothNames(): void
    {
        $client = new Client();
        $client->setContactFirstName('Jean');
        $client->setContactLastName('Dupont');

        $this->assertEquals('Jean Dupont', $client->getContactName());
    }

    public function testGetContactNameWithOnlyFirstName(): void
    {
        $client = new Client();
        $client->setContactFirstName('Jean');
        $client->setContactLastName(null);

        $this->assertEquals('Jean', $client->getContactName());
    }

    public function testGetContactNameWithOnlyLastName(): void
    {
        $client = new Client();
        $client->setContactFirstName(null);
        $client->setContactLastName('Dupont');

        $this->assertEquals('Dupont', $client->getContactName());
    }

    public function testGetContactNameEmptyWhenNoNames(): void
    {
        $client = new Client();
        $client->setContactFirstName(null);
        $client->setContactLastName(null);

        $this->assertEquals('', $client->getContactName());
    }

    // ===== DEVIS COLLECTION TESTS =====

    public function testAddDeviSetsClientOnDevis(): void
    {
        $client = new Client();
        $devis = new Devis();

        $client->addDevi($devis);

        $this->assertTrue($client->getDevis()->contains($devis));
        $this->assertSame($client, $devis->getClient());
    }

    public function testAddDeviDoesNotAddDuplicate(): void
    {
        $client = new Client();
        $devis = new Devis();

        $client->addDevi($devis);
        $client->addDevi($devis);

        $this->assertCount(1, $client->getDevis());
    }

    public function testRemoveDeviRemovesClientFromDevis(): void
    {
        $client = new Client();
        $devis = new Devis();
        $client->addDevi($devis);

        $client->removeDevi($devis);

        $this->assertFalse($client->getDevis()->contains($devis));
        $this->assertNull($devis->getClient());
    }

    // ===== FACTURES COLLECTION TESTS =====

    public function testAddFactureSetsClientOnFacture(): void
    {
        $client = new Client();
        $facture = new Facture();

        $client->addFacture($facture);

        $this->assertTrue($client->getFactures()->contains($facture));
        $this->assertSame($client, $facture->getClient());
    }

    public function testRemoveFactureRemovesClientFromFacture(): void
    {
        $client = new Client();
        $facture = new Facture();
        $client->addFacture($facture);

        $client->removeFacture($facture);

        $this->assertFalse($client->getFactures()->contains($facture));
        $this->assertNull($facture->getClient());
    }

    // ===== PROJECTS COLLECTION TESTS =====

    public function testAddProjectSetsClientOnProject(): void
    {
        $client = new Client();
        $project = new Project();

        $client->addProject($project);

        $this->assertTrue($client->getProjects()->contains($project));
        $this->assertSame($client, $project->getClient());
    }

    public function testRemoveProjectRemovesClientFromProject(): void
    {
        $client = new Client();
        $project = new Project();
        $client->addProject($project);

        $client->removeProject($project);

        $this->assertFalse($client->getProjects()->contains($project));
        $this->assertNull($project->getClient());
    }

    // ===== TO STRING TESTS =====

    public function testToStringReturnsDisplayName(): void
    {
        $client = new Client();
        $client->setType(Client::TYPE_ENTREPRISE);
        $client->setName('Jean Dupont');
        $client->setCompanyName('Acme Corporation');

        $this->assertEquals('Acme Corporation', (string) $client);
    }

    public function testToStringReturnsNameWhenNoCompanyName(): void
    {
        $client = new Client();
        $client->setType(Client::TYPE_ENTREPRISE);
        $client->setName('Simple Name');
        // No company name set

        $this->assertEquals('Simple Name', (string) $client);
    }

    // ===== UPDATED AT TESTS =====

    public function testUpdatedAtCanBeSet(): void
    {
        $client = new Client();
        $date = new \DateTimeImmutable('2026-01-10');

        $client->setUpdatedAt($date);

        $this->assertSame($date, $client->getUpdatedAt());
    }

    // ===== STATIC METHODS TESTS =====

    public function testGetTypeChoicesReturnsAllTypes(): void
    {
        $choices = Client::getTypeChoices();

        $this->assertIsArray($choices);
        $this->assertArrayHasKey('Entreprise', $choices);
        $this->assertArrayHasKey('Association', $choices);
        $this->assertEquals(Client::TYPE_ENTREPRISE, $choices['Entreprise']);
        $this->assertEquals(Client::TYPE_ASSOCIATION, $choices['Association']);
    }
}
