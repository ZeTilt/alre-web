<?php

namespace App\Service\Workflow;

interface WorkflowConfigInterface
{
    /**
     * Returns the mapping of actions to statuses
     * Ex: ['markAsReady' => 'a_envoyer', ...]
     */
    public function getActionToStatusMap(): array;

    /**
     * Returns the mapping of statuses to actions
     * Ex: ['a_envoyer' => 'markAsReady', ...]
     */
    public function getStatusToActionMap(): array;

    /**
     * Returns possible transitions from each status
     * Ex: ['brouillon' => ['a_envoyer'], ...]
     */
    public function getTransitions(): array;

    /**
     * Returns status labels for flash messages
     * Ex: ['a_envoyer' => 'à envoyer', ...]
     */
    public function getStatusLabels(): array;

    /**
     * Returns Bootstrap badge classes per status
     * Ex: ['brouillon' => 'secondary', ...]
     */
    public function getBadgeClasses(): array;

    /**
     * Returns entity name for messages (e.g., 'Devis', 'Facture')
     */
    public function getEntityName(): string;

    /**
     * Returns grammatical gender ('m' or 'f') for French agreement
     */
    public function getGender(): string;
}
