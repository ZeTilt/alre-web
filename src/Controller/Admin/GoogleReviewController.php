<?php

namespace App\Controller\Admin;

use App\Service\ReviewSyncService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/saeiblauhjc/reviews')]
class GoogleReviewController extends AbstractController
{
    public function __construct(
        private ReviewSyncService $reviewSyncService,
    ) {}

    #[Route('/sync', name: 'admin_reviews_sync')]
    public function sync(Request $request): Response
    {
        $force = $request->query->getBoolean('force', false);
        $result = $this->reviewSyncService->syncReviews($force);

        if ($result['errors'] > 0 && $result['created'] === 0 && $result['updated'] === 0) {
            $this->addFlash('error', $result['message']);
        } elseif ($result['created'] > 0 || $result['updated'] > 0) {
            $this->addFlash('success', $result['message']);
        } else {
            $this->addFlash('info', $result['message']);
        }

        return $this->redirectToRoute('admin_seo_dashboard');
    }
}
