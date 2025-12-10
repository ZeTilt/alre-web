<?php

namespace App\Tests\Unit\Service\Workflow;

use App\Entity\Devis;
use App\Entity\Facture;
use App\Entity\Prospect;
use App\Service\Workflow\Config\DevisWorkflowConfig;
use App\Service\Workflow\Config\FactureWorkflowConfig;
use App\Service\Workflow\Config\ProspectWorkflowConfig;
use App\Service\Workflow\WorkflowService;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class WorkflowServiceTest extends TestCase
{
    private WorkflowService $service;
    private UrlGeneratorInterface $urlGenerator;

    protected function setUp(): void
    {
        $this->urlGenerator = $this->createMock(UrlGeneratorInterface::class);
        $this->urlGenerator->method('generate')->willReturn('/admin/test-url');

        $this->service = new WorkflowService($this->urlGenerator);
    }

    // ===== DEVIS WORKFLOW TESTS =====

    public function testDevisTransitionsFromBrouillon(): void
    {
        $config = new DevisWorkflowConfig();

        $transitions = $this->service->getPossibleTransitions($config, Devis::STATUS_BROUILLON);

        $this->assertCount(1, $transitions);
        $this->assertContains(Devis::STATUS_A_ENVOYER, $transitions);
    }

    public function testDevisTransitionsFromEnvoye(): void
    {
        $config = new DevisWorkflowConfig();

        $transitions = $this->service->getPossibleTransitions($config, Devis::STATUS_ENVOYE);

        $this->assertCount(3, $transitions);
        $this->assertContains(Devis::STATUS_RELANCE, $transitions);
        $this->assertContains(Devis::STATUS_ACCEPTE, $transitions);
        $this->assertContains(Devis::STATUS_REFUSE, $transitions);
    }

    public function testDevisNoTransitionsFromRefuse(): void
    {
        $config = new DevisWorkflowConfig();

        $transitions = $this->service->getPossibleTransitions($config, Devis::STATUS_REFUSE);

        $this->assertEmpty($transitions);
    }

    public function testDevisTransitionAllowed(): void
    {
        $config = new DevisWorkflowConfig();

        $this->assertTrue(
            $this->service->isTransitionAllowed($config, Devis::STATUS_BROUILLON, Devis::STATUS_A_ENVOYER)
        );
        $this->assertFalse(
            $this->service->isTransitionAllowed($config, Devis::STATUS_BROUILLON, Devis::STATUS_ACCEPTE)
        );
    }

    public function testDevisGetStatusForAction(): void
    {
        $config = new DevisWorkflowConfig();

        $this->assertEquals(Devis::STATUS_A_ENVOYER, $this->service->getStatusForAction($config, 'markAsReady'));
        $this->assertEquals(Devis::STATUS_ACCEPTE, $this->service->getStatusForAction($config, 'markAsAccepted'));
        $this->assertNull($this->service->getStatusForAction($config, 'invalidAction'));
    }

    public function testDevisStatusChangeMessage(): void
    {
        $config = new DevisWorkflowConfig();

        $message = $this->service->getStatusChangeMessage($config, Devis::STATUS_ACCEPTE);

        $this->assertEquals('Devis marqué comme accepté.', $message);
    }

    // ===== FACTURE WORKFLOW TESTS =====

    public function testFactureTransitionsFromEnvoye(): void
    {
        $config = new FactureWorkflowConfig();

        $transitions = $this->service->getPossibleTransitions($config, Facture::STATUS_ENVOYE);

        $this->assertCount(3, $transitions);
        $this->assertContains(Facture::STATUS_RELANCE, $transitions);
        $this->assertContains(Facture::STATUS_PAYE, $transitions);
        $this->assertContains(Facture::STATUS_EN_RETARD, $transitions);
    }

    public function testFactureNoTransitionsFromPaye(): void
    {
        $config = new FactureWorkflowConfig();

        $transitions = $this->service->getPossibleTransitions($config, Facture::STATUS_PAYE);

        $this->assertEmpty($transitions);
    }

    public function testFactureCanPayFromEnRetard(): void
    {
        $config = new FactureWorkflowConfig();

        $this->assertTrue(
            $this->service->isTransitionAllowed($config, Facture::STATUS_EN_RETARD, Facture::STATUS_PAYE)
        );
    }

    public function testFactureStatusChangeMessageFeminine(): void
    {
        $config = new FactureWorkflowConfig();

        $message = $this->service->getStatusChangeMessage($config, Facture::STATUS_PAYE);

        // Facture is feminine in French, so "marquée" not "marqué"
        $this->assertEquals('Facture marquée comme payée.', $message);
    }

    // ===== PROSPECT WORKFLOW TESTS =====

    public function testProspectTransitionsFromIdentified(): void
    {
        $config = new ProspectWorkflowConfig();

        $transitions = $this->service->getPossibleTransitions($config, Prospect::STATUS_IDENTIFIED);

        $this->assertCount(2, $transitions);
        $this->assertContains(Prospect::STATUS_CONTACTED, $transitions);
        $this->assertContains(Prospect::STATUS_LOST, $transitions);
    }

    public function testProspectCanReactivateFromLost(): void
    {
        $config = new ProspectWorkflowConfig();

        $transitions = $this->service->getPossibleTransitions($config, Prospect::STATUS_LOST);

        $this->assertCount(1, $transitions);
        $this->assertContains(Prospect::STATUS_IDENTIFIED, $transitions);
    }

    public function testProspectNoTransitionsFromWon(): void
    {
        $config = new ProspectWorkflowConfig();

        $transitions = $this->service->getPossibleTransitions($config, Prospect::STATUS_WON);

        $this->assertEmpty($transitions);
    }

    // ===== RENDER STATUS DROPDOWN TESTS =====

    public function testRenderStatusDropdownWithNoTransitions(): void
    {
        $config = new DevisWorkflowConfig();

        $devis = new Devis();
        $devis->setTitle('Test');
        // Set status to one with no transitions
        $reflection = new \ReflectionClass($devis);
        $statusProperty = $reflection->getProperty('status');
        $statusProperty->setValue($devis, Devis::STATUS_REFUSE);

        $html = $this->service->renderStatusDropdown(
            $devis,
            $config,
            'App\Controller\Admin\DevisCrudController',
            fn($e) => 1
        );

        // Should be a simple badge without dropdown
        $this->assertStringContainsString('badge', $html);
        $this->assertStringNotContainsString('dropdown-toggle', $html);
    }

    public function testRenderStatusDropdownWithTransitions(): void
    {
        $config = new DevisWorkflowConfig();

        $devis = new Devis();
        $devis->setTitle('Test');
        // Status brouillon has transitions

        $html = $this->service->renderStatusDropdown(
            $devis,
            $config,
            'App\Controller\Admin\DevisCrudController',
            fn($e) => 1
        );

        // Should be a dropdown
        $this->assertStringContainsString('dropdown-toggle', $html);
        $this->assertStringContainsString('dropdown-menu', $html);
    }

    // ===== ACTION NAME TESTS =====

    public function testGetActionNameForStatus(): void
    {
        $config = new DevisWorkflowConfig();

        $this->assertEquals('markAsReady', $this->service->getActionNameForStatus($config, Devis::STATUS_A_ENVOYER));
        $this->assertEquals('markAsSent', $this->service->getActionNameForStatus($config, Devis::STATUS_ENVOYE));
        $this->assertEquals('markAsAccepted', $this->service->getActionNameForStatus($config, Devis::STATUS_ACCEPTE));
    }

    public function testGetActionNameForUnknownStatusReturnsDefault(): void
    {
        $config = new DevisWorkflowConfig();

        $this->assertEquals('changeStatus', $this->service->getActionNameForStatus($config, 'unknown_status'));
    }
}
