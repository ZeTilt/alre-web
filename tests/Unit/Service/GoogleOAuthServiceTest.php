<?php

namespace App\Tests\Unit\Service;

use App\Entity\GoogleOAuthToken;
use App\Repository\GoogleOAuthTokenRepository;
use App\Service\GoogleOAuthService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

/**
 * Tests unitaires pour GoogleOAuthService.
 *
 * Couvre:
 * - isConfigured() - vérifie si les credentials sont configurés
 * - getAuthorizationUrl() - génère l'URL d'autorisation
 * - handleCallback() - échange le code contre un token
 * - refreshToken() - rafraîchit un token expirant
 * - getValidTokenWithRefresh() - retourne un token valide
 * - isConnected() - vérifie si connecté
 * - disconnect() - supprime le token
 */
class GoogleOAuthServiceTest extends TestCase
{
    private MockObject&HttpClientInterface $httpClient;
    private MockObject&EntityManagerInterface $entityManager;
    private MockObject&GoogleOAuthTokenRepository $tokenRepository;
    private MockObject&LoggerInterface $logger;

    protected function setUp(): void
    {
        $this->httpClient = $this->createMock(HttpClientInterface::class);
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->tokenRepository = $this->createMock(GoogleOAuthTokenRepository::class);
        $this->logger = $this->createMock(LoggerInterface::class);
    }

    private function createService(string $clientId = 'test_client_id', string $clientSecret = 'test_client_secret'): GoogleOAuthService
    {
        return new GoogleOAuthService(
            $this->httpClient,
            $this->entityManager,
            $this->tokenRepository,
            $this->logger,
            $clientId,
            $clientSecret
        );
    }

    // ===== isConfigured() TESTS =====

    public function testIsConfiguredReturnsTrueWhenCredentialsSet(): void
    {
        $service = $this->createService('my_client_id', 'my_client_secret');

        $this->assertTrue($service->isConfigured());
    }

    public function testIsConfiguredReturnsFalseWhenClientIdEmpty(): void
    {
        $service = $this->createService('', 'my_client_secret');

        $this->assertFalse($service->isConfigured());
    }

    public function testIsConfiguredReturnsFalseWhenClientSecretEmpty(): void
    {
        $service = $this->createService('my_client_id', '');

        $this->assertFalse($service->isConfigured());
    }

    public function testIsConfiguredReturnsFalseWhenBothEmpty(): void
    {
        $service = $this->createService('', '');

        $this->assertFalse($service->isConfigured());
    }

    // ===== getAuthorizationUrl() TESTS =====

    public function testGetAuthorizationUrlContainsRequiredParams(): void
    {
        $service = $this->createService();
        $redirectUri = 'https://example.com/callback';

        $url = $service->getAuthorizationUrl($redirectUri);

        $this->assertStringContainsString('client_id=test_client_id', $url);
        $this->assertStringContainsString('redirect_uri=' . urlencode($redirectUri), $url);
        $this->assertStringContainsString('response_type=code', $url);
        $this->assertStringContainsString('access_type=offline', $url);
        $this->assertStringContainsString('prompt=consent', $url);
        $this->assertStringContainsString('scope=', $url);
    }

    public function testGetAuthorizationUrlIncludesStateWhenProvided(): void
    {
        $service = $this->createService();

        $url = $service->getAuthorizationUrl('https://example.com/callback', 'my_csrf_state');

        $this->assertStringContainsString('state=my_csrf_state', $url);
    }

    public function testGetAuthorizationUrlExcludesStateWhenEmpty(): void
    {
        $service = $this->createService();

        $url = $service->getAuthorizationUrl('https://example.com/callback', '');

        $this->assertStringNotContainsString('state=', $url);
    }

    // ===== handleCallback() TESTS =====

    public function testHandleCallbackReturnsTokenOnSuccess(): void
    {
        $service = $this->createService();

        $response = $this->createMock(ResponseInterface::class);
        $response->method('getStatusCode')->willReturn(200);
        $response->method('toArray')->willReturn([
            'access_token' => 'new_access_token',
            'refresh_token' => 'new_refresh_token',
            'expires_in' => 3600,
            'scope' => 'https://www.googleapis.com/auth/webmasters.readonly',
        ]);

        $this->httpClient->method('request')->willReturn($response);
        $this->entityManager->expects($this->once())->method('persist');
        $this->entityManager->expects($this->once())->method('flush');

        $token = $service->handleCallback('auth_code', 'https://example.com/callback');

        $this->assertInstanceOf(GoogleOAuthToken::class, $token);
        $this->assertEquals('new_access_token', $token->getAccessToken());
        $this->assertEquals('new_refresh_token', $token->getRefreshToken());
    }

    public function testHandleCallbackThrowsOnApiError(): void
    {
        $service = $this->createService();

        $response = $this->createMock(ResponseInterface::class);
        $response->method('getStatusCode')->willReturn(400);
        $response->method('getContent')->willReturn('{"error": "invalid_grant"}');

        $this->httpClient->method('request')->willReturn($response);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Échec de l\'échange du code OAuth');

        $service->handleCallback('invalid_code', 'https://example.com/callback');
    }

    // ===== refreshToken() TESTS =====

    public function testRefreshTokenUpdatesTokenOnSuccess(): void
    {
        $service = $this->createService();

        $token = new GoogleOAuthToken();
        $token->setAccessToken('old_access_token')
              ->setRefreshToken('valid_refresh_token')
              ->setExpiresAt(new \DateTimeImmutable('-1 hour'));

        $response = $this->createMock(ResponseInterface::class);
        $response->method('getStatusCode')->willReturn(200);
        $response->method('toArray')->willReturn([
            'access_token' => 'new_access_token',
            'expires_in' => 3600,
        ]);

        $this->httpClient->method('request')->willReturn($response);
        $this->entityManager->expects($this->once())->method('flush');

        $refreshedToken = $service->refreshToken($token);

        $this->assertEquals('new_access_token', $refreshedToken->getAccessToken());
        $this->assertFalse($refreshedToken->isExpired());
    }

    public function testRefreshTokenThrowsWhenNoRefreshToken(): void
    {
        $service = $this->createService();

        $token = new GoogleOAuthToken();
        $token->setAccessToken('access_token')
              ->setRefreshToken(''); // Pas de refresh token

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('No refresh token available');

        $service->refreshToken($token);
    }

    public function testRefreshTokenThrowsOnApiError(): void
    {
        $service = $this->createService();

        $token = new GoogleOAuthToken();
        $token->setRefreshToken('invalid_refresh_token');

        $response = $this->createMock(ResponseInterface::class);
        $response->method('getStatusCode')->willReturn(401);
        $response->method('getContent')->willReturn('{"error": "invalid_token"}');

        $this->httpClient->method('request')->willReturn($response);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Échec du refresh du token OAuth');

        $service->refreshToken($token);
    }

    public function testRefreshTokenUpdatesRefreshTokenIfProvided(): void
    {
        $service = $this->createService();

        $token = new GoogleOAuthToken();
        $token->setRefreshToken('old_refresh_token');

        $response = $this->createMock(ResponseInterface::class);
        $response->method('getStatusCode')->willReturn(200);
        $response->method('toArray')->willReturn([
            'access_token' => 'new_access_token',
            'refresh_token' => 'new_refresh_token',
            'expires_in' => 3600,
        ]);

        $this->httpClient->method('request')->willReturn($response);

        $refreshedToken = $service->refreshToken($token);

        $this->assertEquals('new_refresh_token', $refreshedToken->getRefreshToken());
    }

    // ===== getValidTokenWithRefresh() TESTS =====

    public function testGetValidTokenWithRefreshReturnsNullWhenNoToken(): void
    {
        $service = $this->createService();

        $this->tokenRepository->method('findLatestToken')->willReturn(null);

        $this->assertNull($service->getValidTokenWithRefresh());
    }

    public function testGetValidTokenWithRefreshReturnsTokenWithoutRefreshIfNotExpiring(): void
    {
        $service = $this->createService();

        $token = new GoogleOAuthToken();
        $token->setExpiresAt(new \DateTimeImmutable('+1 hour')); // Pas expiring

        $this->tokenRepository->method('findLatestToken')->willReturn($token);

        $result = $service->getValidTokenWithRefresh();

        $this->assertSame($token, $result);
    }

    public function testGetValidTokenWithRefreshRefreshesExpiringToken(): void
    {
        $service = $this->createService();

        $token = new GoogleOAuthToken();
        $token->setRefreshToken('refresh_token')
              ->setExpiresAt(new \DateTimeImmutable('+2 minutes')); // Expiring soon

        $this->tokenRepository->method('findLatestToken')->willReturn($token);

        $response = $this->createMock(ResponseInterface::class);
        $response->method('getStatusCode')->willReturn(200);
        $response->method('toArray')->willReturn([
            'access_token' => 'new_access_token',
            'expires_in' => 3600,
        ]);

        $this->httpClient->method('request')->willReturn($response);

        $result = $service->getValidTokenWithRefresh();

        $this->assertEquals('new_access_token', $result->getAccessToken());
    }

    public function testGetValidTokenWithRefreshReturnsOldTokenOnRefreshFailure(): void
    {
        $service = $this->createService();

        $token = new GoogleOAuthToken();
        $token->setAccessToken('old_access_token')
              ->setRefreshToken('invalid_refresh_token')
              ->setExpiresAt(new \DateTimeImmutable('+2 minutes'));

        $this->tokenRepository->method('findLatestToken')->willReturn($token);

        $response = $this->createMock(ResponseInterface::class);
        $response->method('getStatusCode')->willReturn(401);
        $response->method('getContent')->willReturn('{"error": "invalid_token"}');

        $this->httpClient->method('request')->willReturn($response);

        // Doit logger l'erreur
        $this->logger->expects($this->atLeastOnce())->method('error');

        $result = $service->getValidTokenWithRefresh();

        // Retourne le token même si refresh échoue
        $this->assertSame($token, $result);
    }

    // ===== isConnected() TESTS =====

    public function testIsConnectedReturnsTrueWhenTokenExists(): void
    {
        $service = $this->createService();

        $token = new GoogleOAuthToken();
        $this->tokenRepository->method('findLatestToken')->willReturn($token);

        $this->assertTrue($service->isConnected());
    }

    public function testIsConnectedReturnsFalseWhenNoToken(): void
    {
        $service = $this->createService();

        $this->tokenRepository->method('findLatestToken')->willReturn(null);

        $this->assertFalse($service->isConnected());
    }

    // ===== disconnect() TESTS =====

    public function testDisconnectRemovesToken(): void
    {
        $service = $this->createService();

        $token = new GoogleOAuthToken();
        $this->tokenRepository->method('findLatestToken')->willReturn($token);

        $this->entityManager->expects($this->once())->method('remove')->with($token);
        $this->entityManager->expects($this->once())->method('flush');

        $service->disconnect();
    }

    public function testDisconnectDoesNothingWhenNoToken(): void
    {
        $service = $this->createService();

        $this->tokenRepository->method('findLatestToken')->willReturn(null);

        $this->entityManager->expects($this->never())->method('remove');
        $this->entityManager->expects($this->never())->method('flush');

        $service->disconnect();
    }

    // ===== getValidToken() TESTS =====

    public function testGetValidTokenReturnsLatestToken(): void
    {
        $service = $this->createService();

        $token = new GoogleOAuthToken();
        $this->tokenRepository->method('findLatestToken')->willReturn($token);

        $this->assertSame($token, $service->getValidToken());
    }

    public function testGetValidTokenReturnsNullWhenNoToken(): void
    {
        $service = $this->createService();

        $this->tokenRepository->method('findLatestToken')->willReturn(null);

        $this->assertNull($service->getValidToken());
    }
}
