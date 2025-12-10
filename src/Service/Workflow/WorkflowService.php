<?php

namespace App\Service\Workflow;

use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class WorkflowService
{
    public function __construct(
        private readonly UrlGeneratorInterface $urlGenerator
    ) {
    }

    /**
     * Get possible status transitions for a given current status
     */
    public function getPossibleTransitions(WorkflowConfigInterface $config, string $currentStatus): array
    {
        $transitions = $config->getTransitions();
        return $transitions[$currentStatus] ?? [];
    }

    /**
     * Check if a transition from current status to new status is allowed
     */
    public function isTransitionAllowed(WorkflowConfigInterface $config, string $currentStatus, string $newStatus): bool
    {
        $possibleTransitions = $this->getPossibleTransitions($config, $currentStatus);
        return in_array($newStatus, $possibleTransitions, true);
    }

    /**
     * Get action name for a given status
     */
    public function getActionNameForStatus(WorkflowConfigInterface $config, string $status): string
    {
        $statusToAction = $config->getStatusToActionMap();
        return $statusToAction[$status] ?? 'changeStatus';
    }

    /**
     * Get status for a given action name
     */
    public function getStatusForAction(WorkflowConfigInterface $config, string $actionName): ?string
    {
        $actionToStatus = $config->getActionToStatusMap();
        return $actionToStatus[$actionName] ?? null;
    }

    /**
     * Get the flash message after a status change
     */
    public function getStatusChangeMessage(WorkflowConfigInterface $config, string $newStatus): string
    {
        $labels = $config->getStatusLabels();
        $label = $labels[$newStatus] ?? $newStatus;
        $entityName = $config->getEntityName();

        return sprintf('%s marqué%s comme %s.',
            $entityName,
            $config->getGender() === 'f' ? 'e' : '',
            $label
        );
    }

    /**
     * Render status dropdown with transition actions
     *
     * @param object $entity Entity with getStatus() method
     * @param WorkflowConfigInterface $config Workflow configuration
     * @param string $controllerFqcn FQCN of the controller for URL generation
     * @param callable $getEntityId Function to get entity ID
     */
    public function renderStatusDropdown(
        object $entity,
        WorkflowConfigInterface $config,
        string $controllerFqcn,
        callable $getEntityId
    ): string {
        $currentStatus = $entity->getStatus();
        $badgeClasses = $config->getBadgeClasses();
        $badgeClass = $badgeClasses[$currentStatus] ?? 'secondary';

        // Get current label from entity's choices (which have "Label" => "value" format)
        $entityClass = get_class($entity);
        $statusChoices = [];
        if (method_exists($entityClass, 'getStatusChoices')) {
            $statusChoices = array_flip($entityClass::getStatusChoices());
        }
        $currentLabel = $statusChoices[$currentStatus] ?? $currentStatus;

        $possibleStatuses = $this->getPossibleTransitions($config, $currentStatus);

        // If no transitions available, just return a simple badge
        if (empty($possibleStatuses)) {
            return sprintf(
                '<span class="badge badge-%s">%s</span>',
                $badgeClass,
                htmlspecialchars($currentLabel)
            );
        }

        // Build dropdown HTML
        $dropdown = '<div class="btn-group">';
        $dropdown .= sprintf(
            '<span class="badge badge-%s dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false" style="cursor: pointer;">%s <i class="fas fa-chevron-down fa-xs"></i></span>',
            $badgeClass,
            htmlspecialchars($currentLabel)
        );
        $dropdown .= '<ul class="dropdown-menu">';

        foreach ($possibleStatuses as $status) {
            $label = $statusChoices[$status] ?? $status;
            $actionName = $this->getActionNameForStatus($config, $status);
            $url = $this->urlGenerator->generate('admin', [
                'crudAction' => 'changeStatus',
                'crudControllerFqcn' => $controllerFqcn,
                'entityId' => $getEntityId($entity),
                'action' => $actionName
            ]);
            $dropdown .= sprintf(
                '<li><a class="dropdown-item" href="%s">%s</a></li>',
                $url,
                htmlspecialchars($label)
            );
        }

        $dropdown .= '</ul></div>';

        return $dropdown;
    }
}
