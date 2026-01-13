<?php

namespace App\Tests\Unit\Entity;

use App\Entity\GoogleReview;
use PHPUnit\Framework\TestCase;

/**
 * Tests unitaires pour GoogleReview.
 *
 * Couvre:
 * - setRating() - valide la note entre 1 et 5
 * - setIsApproved() - efface rejectedAt si approuvé
 * - reject() - marque l'avis comme rejeté
 * - isRejected() - vérifie si rejeté
 * - isPending() - vérifie si en attente de modération
 * - getCommentExcerpt() - extrait du commentaire
 */
class GoogleReviewTest extends TestCase
{
    // ===== setRating() VALIDATION TESTS =====

    public function testSetRatingClampsToMinimum(): void
    {
        $review = new GoogleReview();
        $review->setRating(0);

        // Rating 0 devrait être clampé à 1
        $this->assertEquals(1, $review->getRating());
    }

    public function testSetRatingClampsNegativeToMinimum(): void
    {
        $review = new GoogleReview();
        $review->setRating(-5);

        // Rating négatif devrait être clampé à 1
        $this->assertEquals(1, $review->getRating());
    }

    public function testSetRatingClampsToMaximum(): void
    {
        $review = new GoogleReview();
        $review->setRating(10);

        // Rating > 5 devrait être clampé à 5
        $this->assertEquals(5, $review->getRating());
    }

    public function testSetRatingAcceptsValidValues(): void
    {
        $review = new GoogleReview();

        foreach ([1, 2, 3, 4, 5] as $rating) {
            $review->setRating($rating);
            $this->assertEquals($rating, $review->getRating());
        }
    }

    // ===== setIsApproved() TESTS =====

    public function testSetIsApprovedClearsRejectedAt(): void
    {
        $review = new GoogleReview();

        // D'abord rejeter l'avis
        $review->reject();
        $this->assertNotNull($review->getRejectedAt());

        // Puis approuver
        $review->setIsApproved(true);

        $this->assertTrue($review->isApproved());
        $this->assertNull($review->getRejectedAt());
    }

    public function testSetIsApprovedToFalseDoesNotSetRejectedAt(): void
    {
        $review = new GoogleReview();
        $review->setIsApproved(false);

        $this->assertFalse($review->isApproved());
        $this->assertNull($review->getRejectedAt());
    }

    // ===== reject() TESTS =====

    public function testRejectSetsIsApprovedToFalse(): void
    {
        $review = new GoogleReview();
        $review->setIsApproved(true);

        $review->reject();

        $this->assertFalse($review->isApproved());
    }

    public function testRejectSetsRejectedAtToNow(): void
    {
        $before = new \DateTimeImmutable();
        $review = new GoogleReview();

        $review->reject();
        $after = new \DateTimeImmutable();

        $this->assertNotNull($review->getRejectedAt());
        $this->assertGreaterThanOrEqual($before, $review->getRejectedAt());
        $this->assertLessThanOrEqual($after, $review->getRejectedAt());
    }

    public function testRejectReturnsSelf(): void
    {
        $review = new GoogleReview();

        $this->assertSame($review, $review->reject());
    }

    // ===== isRejected() TESTS =====

    public function testIsRejectedReturnsTrueWhenRejected(): void
    {
        $review = new GoogleReview();
        $review->reject();

        $this->assertTrue($review->isRejected());
    }

    public function testIsRejectedReturnsFalseWhenNotRejected(): void
    {
        $review = new GoogleReview();

        $this->assertFalse($review->isRejected());
    }

    public function testIsRejectedReturnsFalseAfterApproval(): void
    {
        $review = new GoogleReview();
        $review->reject();
        $review->setIsApproved(true);

        $this->assertFalse($review->isRejected());
    }

    // ===== isPending() TESTS =====

    public function testIsPendingReturnsTrueByDefault(): void
    {
        $review = new GoogleReview();

        // Nouveau review: pas approuvé, pas rejeté = en attente
        $this->assertTrue($review->isPending());
    }

    public function testIsPendingReturnsFalseWhenApproved(): void
    {
        $review = new GoogleReview();
        $review->setIsApproved(true);

        $this->assertFalse($review->isPending());
    }

    public function testIsPendingReturnsFalseWhenRejected(): void
    {
        $review = new GoogleReview();
        $review->reject();

        $this->assertFalse($review->isPending());
    }

    // ===== getCommentExcerpt() TESTS =====

    public function testGetCommentExcerptReturnsEmptyStringWhenNoComment(): void
    {
        $review = new GoogleReview();

        $this->assertEquals('', $review->getCommentExcerpt());
    }

    public function testGetCommentExcerptReturnsFullCommentWhenShort(): void
    {
        $review = new GoogleReview();
        $review->setComment('Excellent service !');

        $this->assertEquals('Excellent service !', $review->getCommentExcerpt());
    }

    public function testGetCommentExcerptTruncatesLongComment(): void
    {
        $review = new GoogleReview();
        $longComment = str_repeat('A', 150); // 150 caractères
        $review->setComment($longComment);

        $excerpt = $review->getCommentExcerpt(100);

        $this->assertEquals(103, mb_strlen($excerpt)); // 100 + "..."
        $this->assertStringEndsWith('...', $excerpt);
    }

    public function testGetCommentExcerptWithCustomMaxLength(): void
    {
        $review = new GoogleReview();
        $review->setComment('Ce développeur a fait un travail exceptionnel sur mon site web.');

        $excerpt = $review->getCommentExcerpt(20);

        $this->assertEquals('Ce développeur a fai...', $excerpt);
    }

    public function testGetCommentExcerptAtExactLength(): void
    {
        $review = new GoogleReview();
        $comment = str_repeat('X', 100); // Exactement 100 caractères
        $review->setComment($comment);

        $excerpt = $review->getCommentExcerpt(100);

        // Pas de troncature si exactement égal
        $this->assertEquals($comment, $excerpt);
        $this->assertStringEndsNotWith('...', $excerpt);
    }

    public function testGetCommentExcerptWithUnicodeCharacters(): void
    {
        $review = new GoogleReview();
        $review->setComment('Service très professionnel avec des émojis 👍🎉 et accents éàù');

        $excerpt = $review->getCommentExcerpt(30);

        // mb_strlen compte correctement les caractères unicode
        $this->assertEquals(33, mb_strlen($excerpt)); // 30 + "..."
    }

    // ===== DEFAULT VALUES TESTS =====

    public function testIsApprovedDefaultsToFalse(): void
    {
        $review = new GoogleReview();

        $this->assertFalse($review->isApproved());
    }

    public function testRejectedAtDefaultsToNull(): void
    {
        $review = new GoogleReview();

        $this->assertNull($review->getRejectedAt());
    }

    // ===== FLUENT SETTERS TESTS =====

    public function testSettersReturnSelf(): void
    {
        $review = new GoogleReview();

        $this->assertSame($review, $review->setGoogleReviewId('review_123'));
        $this->assertSame($review, $review->setAuthorName('Jean Dupont'));
        $this->assertSame($review, $review->setRating(5));
        $this->assertSame($review, $review->setComment('Super !'));
        $this->assertSame($review, $review->setReviewDate(new \DateTimeImmutable()));
        $this->assertSame($review, $review->setIsApproved(true));
        $this->assertSame($review, $review->setRejectedAt(new \DateTimeImmutable()));
        $this->assertSame($review, $review->setCreatedAt(new \DateTimeImmutable()));
        $this->assertSame($review, $review->setUpdatedAt(new \DateTimeImmutable()));
    }

    public function testSettersStoreValues(): void
    {
        $review = new GoogleReview();
        $reviewDate = new \DateTimeImmutable('2026-01-10');

        $review->setGoogleReviewId('abc123xyz')
               ->setAuthorName('Marie Martin')
               ->setRating(4)
               ->setComment('Très bon travail')
               ->setReviewDate($reviewDate);

        $this->assertEquals('abc123xyz', $review->getGoogleReviewId());
        $this->assertEquals('Marie Martin', $review->getAuthorName());
        $this->assertEquals(4, $review->getRating());
        $this->assertEquals('Très bon travail', $review->getComment());
        $this->assertEquals($reviewDate, $review->getReviewDate());
    }

    // ===== NULLABLE FIELDS TESTS =====

    public function testCommentCanBeNull(): void
    {
        $review = new GoogleReview();

        $this->assertNull($review->getComment());

        $review->setComment('Un commentaire');
        $this->assertEquals('Un commentaire', $review->getComment());

        $review->setComment(null);
        $this->assertNull($review->getComment());
    }

    // ===== STATE MACHINE TESTS =====

    public function testStateTransitionPendingToApproved(): void
    {
        $review = new GoogleReview();

        $this->assertTrue($review->isPending());
        $this->assertFalse($review->isApproved());
        $this->assertFalse($review->isRejected());

        $review->setIsApproved(true);

        $this->assertFalse($review->isPending());
        $this->assertTrue($review->isApproved());
        $this->assertFalse($review->isRejected());
    }

    public function testStateTransitionPendingToRejected(): void
    {
        $review = new GoogleReview();

        $this->assertTrue($review->isPending());

        $review->reject();

        $this->assertFalse($review->isPending());
        $this->assertFalse($review->isApproved());
        $this->assertTrue($review->isRejected());
    }

    public function testStateTransitionRejectedToApproved(): void
    {
        $review = new GoogleReview();
        $review->reject();

        $this->assertTrue($review->isRejected());

        $review->setIsApproved(true);

        $this->assertFalse($review->isPending());
        $this->assertTrue($review->isApproved());
        $this->assertFalse($review->isRejected());
    }

    public function testStateTransitionApprovedToRejected(): void
    {
        $review = new GoogleReview();
        $review->setIsApproved(true);

        $this->assertTrue($review->isApproved());

        $review->reject();

        $this->assertFalse($review->isPending());
        $this->assertFalse($review->isApproved());
        $this->assertTrue($review->isRejected());
    }
}
