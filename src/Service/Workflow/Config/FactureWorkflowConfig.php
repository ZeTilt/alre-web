<?php

namespace App\Service\Workflow\Config;

use App\Entity\Facture;
use App\Service\Workflow\WorkflowConfigInterface;

class FactureWorkflowConfig implements WorkflowConfigInterface
{
    public function getActionToStatusMap(): array
    {
        return [
            'markAsReady' => Facture::STATUS_A_ENVOYER,
            'markAsSent' => Facture::STATUS_ENVOYE,
            'markAsToRelaunch' => Facture::STATUS_A_RELANCER,
            'markAsRelance' => Facture::STATUS_RELANCE,
            'markAsPaid' => Facture::STATUS_PAYE,
            'markAsOverdue' => Facture::STATUS_EN_RETARD,
            'markAsCancelled' => Facture::STATUS_ANNULE,
        ];
    }

    public function getStatusToActionMap(): array
    {
        return array_flip($this->getActionToStatusMap());
    }

    public function getTransitions(): array
    {
        return [
            Facture::STATUS_BROUILLON => [Facture::STATUS_A_ENVOYER],
            Facture::STATUS_A_ENVOYER => [Facture::STATUS_ENVOYE, Facture::STATUS_ANNULE],
            Facture::STATUS_ENVOYE => [Facture::STATUS_RELANCE, Facture::STATUS_PAYE, Facture::STATUS_EN_RETARD],
            Facture::STATUS_A_RELANCER => [Facture::STATUS_RELANCE, Facture::STATUS_PAYE],
            Facture::STATUS_RELANCE => [Facture::STATUS_PAYE, Facture::STATUS_EN_RETARD],
            Facture::STATUS_PAYE => [],
            Facture::STATUS_EN_RETARD => [Facture::STATUS_PAYE],
            Facture::STATUS_ANNULE => [],
        ];
    }

    public function getStatusLabels(): array
    {
        return [
            Facture::STATUS_A_ENVOYER => 'à envoyer',
            Facture::STATUS_ENVOYE => 'envoyée',
            Facture::STATUS_A_RELANCER => 'à relancer',
            Facture::STATUS_RELANCE => 'relancée',
            Facture::STATUS_PAYE => 'payée',
            Facture::STATUS_EN_RETARD => 'en retard',
            Facture::STATUS_ANNULE => 'annulée',
        ];
    }

    public function getBadgeClasses(): array
    {
        return [
            Facture::STATUS_BROUILLON => 'secondary',
            Facture::STATUS_A_ENVOYER => 'warning',
            Facture::STATUS_ENVOYE => 'info',
            Facture::STATUS_A_RELANCER => 'danger',
            Facture::STATUS_RELANCE => 'warning',
            Facture::STATUS_PAYE => 'success',
            Facture::STATUS_EN_RETARD => 'danger',
            Facture::STATUS_ANNULE => 'secondary',
        ];
    }

    public function getEntityName(): string
    {
        return 'Facture';
    }

    public function getGender(): string
    {
        return 'f';
    }
}
