<?php

namespace App\Service;

use App\Entity\GoogleOAuthToken;
use App\Repository\GoogleOAuthTokenRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class GoogleOAuthService
{
    private const AUTH_URL = 'https://accounts.google.com/o/oauth2/v2/auth';
    private const TOKEN_URL = 'https://oauth2.googleapis.com/token';
    private const SCOPES = [
        'https://www.googleapis.com/auth/webmasters.readonly',
    ];

    public function __construct(
        private HttpClientInterface $httpClient,
        private EntityManagerInterface $entityManager,
        private GoogleOAuthTokenRepository $tokenRepository,
        private LoggerInterface $logger,
        private string $googleClientId,
        private string $googleClientSecret,
    ) {}

    public function isConfigured(): bool
    {
        return !empty($this->googleClientId) && !empty($this->googleClientSecret);
    }

    public function getAuthorizationUrl(string $redirectUri, string $state = ''): string
    {
        $params = [
            'client_id' => $this->googleClientId,
            'redirect_uri' => $redirectUri,
            'response_type' => 'code',
            'scope' => implode(' ', self::SCOPES),
            'access_type' => 'offline',
            'prompt' => 'consent',
        ];

        if ($state) {
            $params['state'] = $state;
        }

        return self::AUTH_URL . '?' . http_build_query($params);
    }

    public function handleCallback(string $code, string $redirectUri): GoogleOAuthToken
    {
        $response = $this->httpClient->request('POST', self::TOKEN_URL, [
            'body' => [
                'client_id' => $this->googleClientId,
                'client_secret' => $this->googleClientSecret,
                'code' => $code,
                'grant_type' => 'authorization_code',
                'redirect_uri' => $redirectUri,
            ],
        ]);

        $statusCode = $response->getStatusCode();
        if ($statusCode !== 200) {
            $this->logger->error('Google OAuth token exchange failed', [
                'status_code' => $statusCode,
                'response' => $response->getContent(false),
            ]);
            throw new \RuntimeException('Échec de l\'échange du code OAuth: ' . $response->getContent(false));
        }

        $data = $response->toArray();

        $token = new GoogleOAuthToken();
        $token->setAccessToken($data['access_token'])
            ->setRefreshToken($data['refresh_token'] ?? '')
            ->setExpiresAt(new \DateTimeImmutable('+' . ($data['expires_in'] ?? 3600) . ' seconds'))
            ->setScope($data['scope'] ?? implode(' ', self::SCOPES));

        $this->entityManager->persist($token);
        $this->entityManager->flush();

        $this->logger->info('Google OAuth token stored successfully');

        return $token;
    }

    public function getValidToken(): ?GoogleOAuthToken
    {
        return $this->tokenRepository->findLatestToken();
    }

    public function isConnected(): bool
    {
        $token = $this->getValidToken();
        return $token !== null;
    }

    public function disconnect(): void
    {
        $token = $this->tokenRepository->findLatestToken();
        if ($token) {
            $this->entityManager->remove($token);
            $this->entityManager->flush();
            $this->logger->info('Google OAuth token removed');
        }
    }

    /**
     * Rafraîchit un token OAuth en utilisant le refresh_token.
     */
    public function refreshToken(GoogleOAuthToken $token): GoogleOAuthToken
    {
        if (!$token->getRefreshToken()) {
            throw new \RuntimeException('No refresh token available');
        }

        $response = $this->httpClient->request('POST', self::TOKEN_URL, [
            'body' => [
                'client_id' => $this->googleClientId,
                'client_secret' => $this->googleClientSecret,
                'refresh_token' => $token->getRefreshToken(),
                'grant_type' => 'refresh_token',
            ],
        ]);

        $statusCode = $response->getStatusCode();
        if ($statusCode !== 200) {
            $this->logger->error('Google OAuth token refresh failed', [
                'status_code' => $statusCode,
                'response' => $response->getContent(false),
            ]);
            throw new \RuntimeException('Échec du refresh du token OAuth');
        }

        $data = $response->toArray();

        $token->setAccessToken($data['access_token'])
            ->setExpiresAt(new \DateTimeImmutable('+' . ($data['expires_in'] ?? 3600) . ' seconds'))
            ->setUpdatedAt(new \DateTimeImmutable());

        // Google peut renvoyer un nouveau refresh_token
        if (isset($data['refresh_token'])) {
            $token->setRefreshToken($data['refresh_token']);
        }

        $this->entityManager->flush();
        $this->logger->info('Google OAuth token refreshed successfully');

        return $token;
    }

    /**
     * Retourne un token valide, en le rafraîchissant si nécessaire.
     * Utilisé avant chaque appel API Google.
     */
    public function getValidTokenWithRefresh(): ?GoogleOAuthToken
    {
        $token = $this->tokenRepository->findLatestToken();

        if (!$token) {
            return null;
        }

        // Rafraîchir si expire dans moins de 5 minutes
        if ($token->isExpiringSoon(5)) {
            try {
                $token = $this->refreshToken($token);
            } catch (\Exception $e) {
                $this->logger->error('Failed to refresh Google OAuth token', [
                    'error' => $e->getMessage(),
                ]);
                // Retourner le token même si refresh échoue, l'appelant décidera
            }
        }

        return $token;
    }
}
