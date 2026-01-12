<?php

namespace App\Controller\Admin;

use App\Service\GoogleOAuthService;
use App\Service\SeoDataImportService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

#[Route('/saeiblauhjc/google')]
class GoogleOAuthController extends AbstractController
{
    public function __construct(
        private GoogleOAuthService $googleOAuthService,
        private SeoDataImportService $seoDataImportService,
    ) {}

    #[Route('/connect', name: 'admin_google_connect')]
    public function connect(): Response
    {
        if (!$this->googleOAuthService->isConfigured()) {
            $this->addFlash('error', 'Les credentials Google ne sont pas configurés. Ajoutez GOOGLE_CLIENT_ID et GOOGLE_CLIENT_SECRET dans le fichier .env');
            return $this->redirectToRoute('admin_business_dashboard');
        }

        $redirectUri = $this->generateUrl('admin_google_callback', [], UrlGeneratorInterface::ABSOLUTE_URL);
        $authUrl = $this->googleOAuthService->getAuthorizationUrl($redirectUri);

        return $this->redirect($authUrl);
    }

    #[Route('/callback', name: 'admin_google_callback')]
    public function callback(Request $request): Response
    {
        $code = $request->query->get('code');
        $error = $request->query->get('error');

        if ($error) {
            $this->addFlash('error', 'Connexion Google refusée: ' . $error);
            return $this->redirectToRoute('admin_business_dashboard');
        }

        if (!$code) {
            $this->addFlash('error', 'Code d\'autorisation manquant');
            return $this->redirectToRoute('admin_business_dashboard');
        }

        try {
            $redirectUri = $this->generateUrl('admin_google_callback', [], UrlGeneratorInterface::ABSOLUTE_URL);
            $this->googleOAuthService->handleCallback($code, $redirectUri);
            $this->addFlash('success', 'Google Search Console connecté avec succès !');
        } catch (\Exception $e) {
            $this->addFlash('error', 'Erreur lors de la connexion: ' . $e->getMessage());
        }

        return $this->redirectToRoute('admin_business_dashboard');
    }

    #[Route('/disconnect', name: 'admin_google_disconnect')]
    public function disconnect(): Response
    {
        $this->googleOAuthService->disconnect();
        $this->addFlash('success', 'Google Search Console déconnecté');
        return $this->redirectToRoute('admin_business_dashboard');
    }

    #[Route('/sync-seo', name: 'admin_google_sync_seo')]
    public function syncSeo(Request $request): Response
    {
        if (!$this->googleOAuthService->isConnected()) {
            $this->addFlash('error', 'Google Search Console n\'est pas connecté');
            return $this->redirectToRoute('admin_business_dashboard');
        }

        $force = $request->query->getBoolean('force', false);
        $result = $this->seoDataImportService->syncAllKeywords($force);

        if ($result['synced'] > 0 || $result['skipped'] > 0) {
            $this->addFlash('success', $result['message']);
        } elseif ($result['errors'] > 0) {
            $this->addFlash('error', $result['message']);
        } else {
            $this->addFlash('info', $result['message']);
        }

        return $this->redirectToRoute('admin_business_dashboard');
    }
}
