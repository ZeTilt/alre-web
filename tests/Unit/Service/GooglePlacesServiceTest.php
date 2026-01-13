<?php

namespace App\Tests\Unit\Service;

use App\Service\GooglePlacesService;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

/**
 * Tests unitaires pour GooglePlacesService.
 *
 * Couvre:
 * - isConfigured() - vérifie si l'API est configurée
 * - fetchReviews() - récupère les avis depuis Google Places
 */
class GooglePlacesServiceTest extends TestCase
{
    private MockObject&HttpClientInterface $httpClient;
    private MockObject&LoggerInterface $logger;

    protected function setUp(): void
    {
        $this->httpClient = $this->createMock(HttpClientInterface::class);
        $this->logger = $this->createMock(LoggerInterface::class);
    }

    private function createService(string $apiKey = 'test_api_key', string $placeId = 'test_place_id'): GooglePlacesService
    {
        return new GooglePlacesService(
            $this->httpClient,
            $this->logger,
            $apiKey,
            $placeId
        );
    }

    // ===== isConfigured() TESTS =====

    public function testIsConfiguredReturnsTrueWhenBothSet(): void
    {
        $service = $this->createService('my_api_key', 'my_place_id');

        $this->assertTrue($service->isConfigured());
    }

    public function testIsConfiguredReturnsFalseWhenApiKeyEmpty(): void
    {
        $service = $this->createService('', 'my_place_id');

        $this->assertFalse($service->isConfigured());
    }

    public function testIsConfiguredReturnsFalseWhenPlaceIdEmpty(): void
    {
        $service = $this->createService('my_api_key', '');

        $this->assertFalse($service->isConfigured());
    }

    public function testIsConfiguredReturnsFalseWhenBothEmpty(): void
    {
        $service = $this->createService('', '');

        $this->assertFalse($service->isConfigured());
    }

    // ===== fetchReviews() TESTS =====

    public function testFetchReviewsReturnsNullWhenNotConfigured(): void
    {
        $service = $this->createService('', '');

        $this->assertNull($service->fetchReviews());
    }

    public function testFetchReviewsReturnsReviewsOnSuccess(): void
    {
        $service = $this->createService();

        $response = $this->createMock(ResponseInterface::class);
        $response->method('getStatusCode')->willReturn(200);
        $response->method('toArray')->willReturn([
            'reviews' => [
                [
                    'name' => 'places/123/reviews/abc',
                    'authorAttribution' => ['displayName' => 'Jean Dupont'],
                    'rating' => 5,
                    'text' => ['text' => 'Excellent service !'],
                    'publishTime' => '2026-01-10T14:30:00Z',
                ],
                [
                    'name' => 'places/123/reviews/def',
                    'authorAttribution' => ['displayName' => 'Marie Martin'],
                    'rating' => 4,
                    'text' => ['text' => 'Très bon travail'],
                    'publishTime' => '2026-01-08T10:00:00Z',
                ],
            ],
        ]);

        $this->httpClient->method('request')->willReturn($response);

        $reviews = $service->fetchReviews();

        $this->assertCount(2, $reviews);
        $this->assertEquals('places/123/reviews/abc', $reviews[0]['name']);
        $this->assertEquals('Jean Dupont', $reviews[0]['authorName']);
        $this->assertEquals(5, $reviews[0]['rating']);
        $this->assertEquals('Excellent service !', $reviews[0]['comment']);
    }

    public function testFetchReviewsReturnsEmptyArrayWhenNoReviews(): void
    {
        $service = $this->createService();

        $response = $this->createMock(ResponseInterface::class);
        $response->method('getStatusCode')->willReturn(200);
        $response->method('toArray')->willReturn([
            'reviews' => [],
        ]);

        $this->httpClient->method('request')->willReturn($response);

        $reviews = $service->fetchReviews();

        $this->assertEquals([], $reviews);
    }

    public function testFetchReviewsReturnsNullOnApiError(): void
    {
        $service = $this->createService();

        $response = $this->createMock(ResponseInterface::class);
        $response->method('getStatusCode')->willReturn(403);
        $response->method('getContent')->willReturn('{"error": "forbidden"}');

        $this->httpClient->method('request')->willReturn($response);

        $this->assertNull($service->fetchReviews());
    }

    public function testFetchReviewsHandlesMissingFields(): void
    {
        $service = $this->createService();

        $response = $this->createMock(ResponseInterface::class);
        $response->method('getStatusCode')->willReturn(200);
        $response->method('toArray')->willReturn([
            'reviews' => [
                [
                    'name' => 'places/123/reviews/xyz',
                    // Missing authorAttribution
                    'rating' => 3,
                    // Missing text
                    'publishTime' => '2026-01-05T08:00:00Z',
                ],
            ],
        ]);

        $this->httpClient->method('request')->willReturn($response);

        $reviews = $service->fetchReviews();

        $this->assertCount(1, $reviews);
        $this->assertEquals('Anonyme', $reviews[0]['authorName']); // Default
        $this->assertNull($reviews[0]['comment']); // null
    }

    public function testFetchReviewsCastsRatingToInt(): void
    {
        $service = $this->createService();

        $response = $this->createMock(ResponseInterface::class);
        $response->method('getStatusCode')->willReturn(200);
        $response->method('toArray')->willReturn([
            'reviews' => [
                [
                    'name' => 'places/123/reviews/test',
                    'authorAttribution' => ['displayName' => 'Test'],
                    'rating' => '4', // String from API
                    'text' => ['text' => 'Test'],
                    'publishTime' => '2026-01-01T00:00:00Z',
                ],
            ],
        ]);

        $this->httpClient->method('request')->willReturn($response);

        $reviews = $service->fetchReviews();

        $this->assertIsInt($reviews[0]['rating']);
        $this->assertEquals(4, $reviews[0]['rating']);
    }

    public function testFetchReviewsUsesCorrectHeaders(): void
    {
        $service = $this->createService('my_api_key', 'my_place_id');

        $response = $this->createMock(ResponseInterface::class);
        $response->method('getStatusCode')->willReturn(200);
        $response->method('toArray')->willReturn(['reviews' => []]);

        $this->httpClient
            ->expects($this->once())
            ->method('request')
            ->with(
                'GET',
                $this->stringContains('my_place_id'),
                $this->callback(function ($options) {
                    return isset($options['headers']['X-Goog-Api-Key'])
                        && $options['headers']['X-Goog-Api-Key'] === 'my_api_key'
                        && isset($options['headers']['X-Goog-FieldMask']);
                })
            )
            ->willReturn($response);

        $service->fetchReviews();
    }
}
