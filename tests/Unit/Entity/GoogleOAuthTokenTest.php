<?php

namespace App\Tests\Unit\Entity;

use App\Entity\GoogleOAuthToken;
use PHPUnit\Framework\TestCase;

/**
 * Tests unitaires pour GoogleOAuthToken.
 *
 * Couvre:
 * - isExpired() - vérifie si le token est expiré
 * - isExpiringSoon() - vérifie si le token expire bientôt
 * - Setters fluent (return $this)
 * - __toString()
 */
class GoogleOAuthTokenTest extends TestCase
{
    // ===== isExpired() TESTS =====

    public function testIsExpiredReturnsTrueWhenTokenIsExpired(): void
    {
        $token = new GoogleOAuthToken();
        $token->setExpiresAt(new \DateTimeImmutable('-1 hour'));

        $this->assertTrue($token->isExpired());
    }

    public function testIsExpiredReturnsFalseWhenTokenIsValid(): void
    {
        $token = new GoogleOAuthToken();
        $token->setExpiresAt(new \DateTimeImmutable('+1 hour'));

        $this->assertFalse($token->isExpired());
    }

    public function testIsExpiredReturnsTrueWhenTokenExpiresNow(): void
    {
        $token = new GoogleOAuthToken();
        // Token qui expire exactement maintenant (donc expiré)
        $token->setExpiresAt(new \DateTimeImmutable('-1 second'));

        $this->assertTrue($token->isExpired());
    }

    // ===== isExpiringSoon() TESTS =====

    public function testIsExpiringSoonReturnsTrueWhenExpiresWithinThreshold(): void
    {
        $token = new GoogleOAuthToken();
        // Expire dans 3 minutes, threshold par défaut = 5 minutes
        $token->setExpiresAt(new \DateTimeImmutable('+3 minutes'));

        $this->assertTrue($token->isExpiringSoon());
    }

    public function testIsExpiringSoonReturnsFalseWhenExpiresAfterThreshold(): void
    {
        $token = new GoogleOAuthToken();
        // Expire dans 10 minutes, threshold par défaut = 5 minutes
        $token->setExpiresAt(new \DateTimeImmutable('+10 minutes'));

        $this->assertFalse($token->isExpiringSoon());
    }

    public function testIsExpiringSoonWithCustomThreshold(): void
    {
        $token = new GoogleOAuthToken();
        // Expire dans 15 minutes
        $token->setExpiresAt(new \DateTimeImmutable('+15 minutes'));

        // Threshold 10 minutes -> pas bientôt
        $this->assertFalse($token->isExpiringSoon(10));

        // Threshold 20 minutes -> bientôt
        $this->assertTrue($token->isExpiringSoon(20));
    }

    public function testIsExpiringSoonReturnsTrueWhenAlreadyExpired(): void
    {
        $token = new GoogleOAuthToken();
        $token->setExpiresAt(new \DateTimeImmutable('-1 hour'));

        // Un token expiré est forcément "bientôt expiré"
        $this->assertTrue($token->isExpiringSoon());
    }

    // ===== FLUENT SETTERS TESTS =====

    public function testSettersReturnSelf(): void
    {
        $token = new GoogleOAuthToken();

        $this->assertSame($token, $token->setAccessToken('access_token'));
        $this->assertSame($token, $token->setRefreshToken('refresh_token'));
        $this->assertSame($token, $token->setExpiresAt(new \DateTimeImmutable()));
        $this->assertSame($token, $token->setScope('openid email'));
        $this->assertSame($token, $token->setCreatedAt(new \DateTimeImmutable()));
        $this->assertSame($token, $token->setUpdatedAt(new \DateTimeImmutable()));
    }

    public function testSettersStoreValues(): void
    {
        $token = new GoogleOAuthToken();
        $expiresAt = new \DateTimeImmutable('+1 hour');

        $token->setAccessToken('my_access_token')
              ->setRefreshToken('my_refresh_token')
              ->setExpiresAt($expiresAt)
              ->setScope('https://www.googleapis.com/auth/webmasters.readonly');

        $this->assertEquals('my_access_token', $token->getAccessToken());
        $this->assertEquals('my_refresh_token', $token->getRefreshToken());
        $this->assertEquals($expiresAt, $token->getExpiresAt());
        $this->assertEquals('https://www.googleapis.com/auth/webmasters.readonly', $token->getScope());
    }

    // ===== CONSTRUCTOR TESTS =====

    public function testConstructorSetsCreatedAt(): void
    {
        $before = new \DateTimeImmutable();
        $token = new GoogleOAuthToken();
        $after = new \DateTimeImmutable();

        $this->assertNotNull($token->getCreatedAt());
        $this->assertGreaterThanOrEqual($before, $token->getCreatedAt());
        $this->assertLessThanOrEqual($after, $token->getCreatedAt());
    }

    // ===== __toString() TESTS =====

    public function testToStringWithoutId(): void
    {
        $token = new GoogleOAuthToken();

        $this->assertEquals('Google OAuth Token #new', (string) $token);
    }

    // Note: On ne peut pas tester __toString avec un ID car l'ID est généré par Doctrine
    // et n'est pas settable directement. Ce serait un test d'intégration.

    // ===== NULLABLE FIELDS TESTS =====

    public function testScopeCanBeNull(): void
    {
        $token = new GoogleOAuthToken();

        $this->assertNull($token->getScope());

        $token->setScope(null);
        $this->assertNull($token->getScope());
    }

    public function testUpdatedAtCanBeNull(): void
    {
        $token = new GoogleOAuthToken();

        $this->assertNull($token->getUpdatedAt());
    }
}
