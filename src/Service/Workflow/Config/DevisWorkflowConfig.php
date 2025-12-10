<?php

namespace App\Service\Workflow\Config;

use App\Entity\Devis;
use App\Service\Workflow\WorkflowConfigInterface;

class DevisWorkflowConfig implements WorkflowConfigInterface
{
    public function getActionToStatusMap(): array
    {
        return [
            'markAsReady' => Devis::STATUS_A_ENVOYER,
            'markAsSent' => Devis::STATUS_ENVOYE,
            'markAsToRelaunch' => Devis::STATUS_A_RELANCER,
            'markAsRelance' => Devis::STATUS_RELANCE,
            'markAsAccepted' => Devis::STATUS_ACCEPTE,
            'markAsRejected' => Devis::STATUS_REFUSE,
            'markAsExpired' => Devis::STATUS_EXPIRE,
            'markAsCancelled' => Devis::STATUS_ANNULE,
        ];
    }

    public function getStatusToActionMap(): array
    {
        return array_flip($this->getActionToStatusMap());
    }

    public function getTransitions(): array
    {
        return [
            Devis::STATUS_BROUILLON => [Devis::STATUS_A_ENVOYER],
            Devis::STATUS_A_ENVOYER => [Devis::STATUS_ENVOYE, Devis::STATUS_ANNULE],
            Devis::STATUS_ENVOYE => [Devis::STATUS_RELANCE, Devis::STATUS_ACCEPTE, Devis::STATUS_REFUSE],
            Devis::STATUS_A_RELANCER => [Devis::STATUS_RELANCE, Devis::STATUS_ACCEPTE, Devis::STATUS_REFUSE],
            Devis::STATUS_RELANCE => [Devis::STATUS_ACCEPTE, Devis::STATUS_REFUSE, Devis::STATUS_EXPIRE],
            Devis::STATUS_ACCEPTE => [Devis::STATUS_ANNULE],
            Devis::STATUS_REFUSE => [],
            Devis::STATUS_EXPIRE => [],
            Devis::STATUS_ANNULE => [],
        ];
    }

    public function getStatusLabels(): array
    {
        return [
            Devis::STATUS_A_ENVOYER => 'à envoyer',
            Devis::STATUS_ENVOYE => 'envoyé',
            Devis::STATUS_A_RELANCER => 'à relancer',
            Devis::STATUS_RELANCE => 'relancé',
            Devis::STATUS_ACCEPTE => 'accepté',
            Devis::STATUS_REFUSE => 'refusé',
            Devis::STATUS_EXPIRE => 'expiré',
            Devis::STATUS_ANNULE => 'annulé',
        ];
    }

    public function getBadgeClasses(): array
    {
        return [
            Devis::STATUS_BROUILLON => 'secondary',
            Devis::STATUS_A_ENVOYER => 'warning',
            Devis::STATUS_ENVOYE => 'info',
            Devis::STATUS_A_RELANCER => 'danger',
            Devis::STATUS_RELANCE => 'warning',
            Devis::STATUS_ACCEPTE => 'success',
            Devis::STATUS_REFUSE => 'danger',
            Devis::STATUS_EXPIRE => 'dark',
            Devis::STATUS_ANNULE => 'secondary',
        ];
    }

    public function getEntityName(): string
    {
        return 'Devis';
    }

    public function getGender(): string
    {
        return 'm';
    }
}
