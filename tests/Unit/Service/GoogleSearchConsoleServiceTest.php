<?php

namespace App\Tests\Unit\Service;

use App\Entity\GoogleOAuthToken;
use App\Service\GoogleOAuthService;
use App\Service\GoogleSearchConsoleService;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

/**
 * Tests unitaires pour GoogleSearchConsoleService.
 *
 * Couvre:
 * - isAvailable() - vérifie si le service est disponible
 * - fetchDataForKeyword() - récupère les données pour un mot-clé
 * - fetchAllKeywordsData() - récupère les données pour tous les mots-clés
 */
class GoogleSearchConsoleServiceTest extends TestCase
{
    private MockObject&HttpClientInterface $httpClient;
    private MockObject&GoogleOAuthService $oauthService;
    private MockObject&LoggerInterface $logger;

    protected function setUp(): void
    {
        $this->httpClient = $this->createMock(HttpClientInterface::class);
        $this->oauthService = $this->createMock(GoogleOAuthService::class);
        $this->logger = $this->createMock(LoggerInterface::class);
    }

    private function createService(string $siteUrl = 'https://alre-web.bzh'): GoogleSearchConsoleService
    {
        return new GoogleSearchConsoleService(
            $this->httpClient,
            $this->oauthService,
            $this->logger,
            $siteUrl
        );
    }

    private function createValidToken(): GoogleOAuthToken
    {
        $token = new GoogleOAuthToken();
        $token->setAccessToken('valid_access_token')
              ->setRefreshToken('valid_refresh_token')
              ->setExpiresAt(new \DateTimeImmutable('+1 hour'));
        return $token;
    }

    // ===== isAvailable() TESTS =====

    public function testIsAvailableReturnsTrueWhenAllConditionsMet(): void
    {
        $service = $this->createService('https://alre-web.bzh');

        $this->oauthService->method('isConfigured')->willReturn(true);
        $this->oauthService->method('isConnected')->willReturn(true);

        $this->assertTrue($service->isAvailable());
    }

    public function testIsAvailableReturnsFalseWhenSiteUrlEmpty(): void
    {
        $service = $this->createService('');

        $this->oauthService->method('isConfigured')->willReturn(true);
        $this->oauthService->method('isConnected')->willReturn(true);

        $this->assertFalse($service->isAvailable());
    }

    public function testIsAvailableReturnsFalseWhenNotConfigured(): void
    {
        $service = $this->createService('https://alre-web.bzh');

        $this->oauthService->method('isConfigured')->willReturn(false);
        $this->oauthService->method('isConnected')->willReturn(true);

        $this->assertFalse($service->isAvailable());
    }

    public function testIsAvailableReturnsFalseWhenNotConnected(): void
    {
        $service = $this->createService('https://alre-web.bzh');

        $this->oauthService->method('isConfigured')->willReturn(true);
        $this->oauthService->method('isConnected')->willReturn(false);

        $this->assertFalse($service->isAvailable());
    }

    // ===== fetchDataForKeyword() TESTS =====

    public function testFetchDataForKeywordReturnsNullWhenNoToken(): void
    {
        $service = $this->createService();

        $this->oauthService->method('getValidTokenWithRefresh')->willReturn(null);

        $this->assertNull($service->fetchDataForKeyword('test keyword'));
    }

    public function testFetchDataForKeywordReturnsDataOnSuccess(): void
    {
        $service = $this->createService();

        $this->oauthService->method('getValidTokenWithRefresh')->willReturn($this->createValidToken());

        $response = $this->createMock(ResponseInterface::class);
        $response->method('getStatusCode')->willReturn(200);
        $response->method('toArray')->willReturn([
            'rows' => [
                [
                    'keys' => ['création site web vannes'],
                    'position' => 4.7,
                    'clicks' => 25,
                    'impressions' => 500,
                ],
            ],
        ]);

        $this->httpClient->method('request')->willReturn($response);

        $result = $service->fetchDataForKeyword('création site web vannes');

        $this->assertIsArray($result);
        $this->assertEquals(4.7, $result['position']);
        $this->assertEquals(25, $result['clicks']);
        $this->assertEquals(500, $result['impressions']);
    }

    public function testFetchDataForKeywordReturnsNullWhenNoRows(): void
    {
        $service = $this->createService();

        $this->oauthService->method('getValidTokenWithRefresh')->willReturn($this->createValidToken());

        $response = $this->createMock(ResponseInterface::class);
        $response->method('getStatusCode')->willReturn(200);
        $response->method('toArray')->willReturn([
            'rows' => [],
        ]);

        $this->httpClient->method('request')->willReturn($response);

        $this->assertNull($service->fetchDataForKeyword('unknown keyword'));
    }

    public function testFetchDataForKeywordReturnsNullOn4xxError(): void
    {
        $service = $this->createService();

        $this->oauthService->method('getValidTokenWithRefresh')->willReturn($this->createValidToken());

        $response = $this->createMock(ResponseInterface::class);
        $response->method('getStatusCode')->willReturn(403);
        $response->method('getContent')->willReturn('{"error": "forbidden"}');

        $this->httpClient->method('request')->willReturn($response);

        $this->assertNull($service->fetchDataForKeyword('test'));
    }

    // ===== fetchAllKeywordsData() TESTS =====

    public function testFetchAllKeywordsDataReturnsEmptyArrayWhenNoToken(): void
    {
        $service = $this->createService();

        $this->oauthService->method('getValidTokenWithRefresh')->willReturn(null);

        $this->assertEquals([], $service->fetchAllKeywordsData());
    }

    public function testFetchAllKeywordsDataReturnsDataOnSuccess(): void
    {
        $service = $this->createService();

        $this->oauthService->method('getValidTokenWithRefresh')->willReturn($this->createValidToken());

        $response = $this->createMock(ResponseInterface::class);
        $response->method('getStatusCode')->willReturn(200);
        $response->method('toArray')->willReturn([
            'rows' => [
                [
                    'keys' => ['création site web vannes'],
                    'position' => 4.7,
                    'clicks' => 25,
                    'impressions' => 500,
                ],
                [
                    'keys' => ['développeur symfony'],
                    'position' => 12.3,
                    'clicks' => 10,
                    'impressions' => 200,
                ],
            ],
        ]);

        $this->httpClient->method('request')->willReturn($response);

        $result = $service->fetchAllKeywordsData();

        $this->assertCount(2, $result);
        $this->assertArrayHasKey('création site web vannes', $result);
        $this->assertArrayHasKey('développeur symfony', $result);
        $this->assertEquals(4.7, $result['création site web vannes']['position']);
        $this->assertEquals(12.3, $result['développeur symfony']['position']);
    }

    public function testFetchAllKeywordsDataNormalizesKeywordsToLowercase(): void
    {
        $service = $this->createService();

        $this->oauthService->method('getValidTokenWithRefresh')->willReturn($this->createValidToken());

        $response = $this->createMock(ResponseInterface::class);
        $response->method('getStatusCode')->willReturn(200);
        $response->method('toArray')->willReturn([
            'rows' => [
                [
                    'keys' => ['Création Site Web'],
                    'position' => 5.0,
                    'clicks' => 10,
                    'impressions' => 100,
                ],
            ],
        ]);

        $this->httpClient->method('request')->willReturn($response);

        $result = $service->fetchAllKeywordsData();

        $this->assertArrayHasKey('création site web', $result); // lowercase
        $this->assertArrayNotHasKey('Création Site Web', $result);
    }

    public function testFetchAllKeywordsDataReturnsEmptyArrayOnApiError(): void
    {
        $service = $this->createService();

        $this->oauthService->method('getValidTokenWithRefresh')->willReturn($this->createValidToken());

        $response = $this->createMock(ResponseInterface::class);
        $response->method('getStatusCode')->willReturn(500);
        $response->method('getContent')->willReturn('{"error": "internal error"}');

        $this->httpClient->method('request')->willReturn($response);

        // Le service retry 3 fois donc on doit simuler 3 réponses
        // Dans ce test simplifié, on retourne juste la même réponse d'erreur

        $result = $service->fetchAllKeywordsData();

        $this->assertEquals([], $result);
    }

    // ===== DATA PARSING TESTS =====

    public function testFetchDataRoundsPositionToOneDecimal(): void
    {
        $service = $this->createService();

        $this->oauthService->method('getValidTokenWithRefresh')->willReturn($this->createValidToken());

        $response = $this->createMock(ResponseInterface::class);
        $response->method('getStatusCode')->willReturn(200);
        $response->method('toArray')->willReturn([
            'rows' => [
                [
                    'keys' => ['test'],
                    'position' => 4.7654321,
                    'clicks' => 10,
                    'impressions' => 100,
                ],
            ],
        ]);

        $this->httpClient->method('request')->willReturn($response);

        $result = $service->fetchDataForKeyword('test');

        $this->assertEquals(4.8, $result['position']); // Arrondi à 1 décimale
    }

    public function testFetchDataCastsClicksAndImpressionsToInt(): void
    {
        $service = $this->createService();

        $this->oauthService->method('getValidTokenWithRefresh')->willReturn($this->createValidToken());

        $response = $this->createMock(ResponseInterface::class);
        $response->method('getStatusCode')->willReturn(200);
        $response->method('toArray')->willReturn([
            'rows' => [
                [
                    'keys' => ['test'],
                    'position' => 5.0,
                    'clicks' => '25', // String from API
                    'impressions' => '500.5', // Float from API
                ],
            ],
        ]);

        $this->httpClient->method('request')->willReturn($response);

        $result = $service->fetchDataForKeyword('test');

        $this->assertIsInt($result['clicks']);
        $this->assertIsInt($result['impressions']);
        $this->assertEquals(25, $result['clicks']);
        $this->assertEquals(500, $result['impressions']);
    }
}
