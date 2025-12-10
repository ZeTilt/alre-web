<?php

namespace App\Service\Workflow\Config;

use App\Entity\Prospect;
use App\Service\Workflow\WorkflowConfigInterface;

class ProspectWorkflowConfig implements WorkflowConfigInterface
{
    public function getActionToStatusMap(): array
    {
        return [
            'markAsIdentified' => Prospect::STATUS_IDENTIFIED,
            'markAsContacted' => Prospect::STATUS_CONTACTED,
            'markAsInDiscussion' => Prospect::STATUS_IN_DISCUSSION,
            'markAsQuoteSent' => Prospect::STATUS_QUOTE_SENT,
            'markAsWon' => Prospect::STATUS_WON,
            'markAsLost' => Prospect::STATUS_LOST,
        ];
    }

    public function getStatusToActionMap(): array
    {
        return array_flip($this->getActionToStatusMap());
    }

    public function getTransitions(): array
    {
        return [
            Prospect::STATUS_IDENTIFIED => [Prospect::STATUS_CONTACTED, Prospect::STATUS_LOST],
            Prospect::STATUS_CONTACTED => [Prospect::STATUS_IN_DISCUSSION, Prospect::STATUS_LOST],
            Prospect::STATUS_IN_DISCUSSION => [Prospect::STATUS_QUOTE_SENT, Prospect::STATUS_WON, Prospect::STATUS_LOST],
            Prospect::STATUS_QUOTE_SENT => [Prospect::STATUS_WON, Prospect::STATUS_LOST, Prospect::STATUS_IN_DISCUSSION],
            Prospect::STATUS_WON => [],
            Prospect::STATUS_LOST => [Prospect::STATUS_IDENTIFIED],
        ];
    }

    public function getStatusLabels(): array
    {
        return [
            Prospect::STATUS_IDENTIFIED => 'identifié',
            Prospect::STATUS_CONTACTED => 'contacté',
            Prospect::STATUS_IN_DISCUSSION => 'en discussion',
            Prospect::STATUS_QUOTE_SENT => 'devis envoyé',
            Prospect::STATUS_WON => 'gagné',
            Prospect::STATUS_LOST => 'perdu',
        ];
    }

    public function getBadgeClasses(): array
    {
        return [
            Prospect::STATUS_IDENTIFIED => 'secondary',
            Prospect::STATUS_CONTACTED => 'info',
            Prospect::STATUS_IN_DISCUSSION => 'warning',
            Prospect::STATUS_QUOTE_SENT => 'primary',
            Prospect::STATUS_WON => 'success',
            Prospect::STATUS_LOST => 'danger',
        ];
    }

    public function getEntityName(): string
    {
        return 'Prospect';
    }

    public function getGender(): string
    {
        return 'm';
    }
}
