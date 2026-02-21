<?php

namespace App\Controller\Admin;

use App\Repository\SeoSyncLogRepository;
use App\Service\DashboardSeoService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_ADMIN')]
class SeoSyncLogController extends AbstractController
{
    #[Route('/saeiblauhjc/seo/sync-history', name: 'admin_seo_sync_history')]
    public function index(SeoSyncLogRepository $repository): Response
    {
        $logs = $repository->findLatest(50);

        return $this->render('admin/seo_sync_log/index.html.twig', [
            'logs' => $logs,
        ]);
    }

    #[Route('/saeiblauhjc/seo/share-card', name: 'admin_seo_share_card')]
    public function shareCard(DashboardSeoService $dashboardSeoService): Response
    {
        $data = $dashboardSeoService->getFullData();

        return $this->render('admin/seo_sync_log/share_card.html.twig', $data);
    }
}
