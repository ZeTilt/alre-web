<?php

namespace App\Service;

use App\Entity\Prospect;
use App\Entity\ProspectContact;
use App\Entity\ProspectInteraction;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;

class ProspectionEmailService
{
    private const FROM_EMAIL = 'fabrice@alre-web.bzh';
    private const FROM_NAME = 'Fabrice Dhuicque - Alré Web';

    public function __construct(
        private readonly MailerInterface $mailer,
        private readonly EntityManagerInterface $entityManager
    ) {
    }

    public function sendProspectionEmail(
        Prospect $prospect,
        string $toEmail,
        string $subject,
        string $content,
        ?ProspectContact $contact = null
    ): ProspectInteraction {
        // Create and send the email
        $email = (new TemplatedEmail())
            ->from(new Address(self::FROM_EMAIL, self::FROM_NAME))
            ->to($toEmail)
            ->subject($subject)
            ->htmlTemplate('admin/prospection/email_template.html.twig')
            ->context([
                'prospect' => $prospect,
                'contact' => $contact,
                'subject' => $subject,
                'content' => $content,
            ]);

        $this->mailer->send($email);

        // Create interaction record
        $interaction = new ProspectInteraction();
        $interaction->setProspect($prospect);
        $interaction->setContact($contact);
        $interaction->setType(ProspectInteraction::TYPE_EMAIL);
        $interaction->setDirection(ProspectInteraction::DIRECTION_SENT);
        $interaction->setSubject($subject);
        $interaction->setContent($content);

        // Update prospect's lastContactAt
        $prospect->setLastContactAt(new \DateTimeImmutable());

        // Update status if still "identified"
        if ($prospect->getStatus() === Prospect::STATUS_IDENTIFIED) {
            $prospect->setStatus(Prospect::STATUS_CONTACTED);
        }

        $this->entityManager->persist($interaction);
        $this->entityManager->flush();

        return $interaction;
    }

    public function getDefaultEmailContent(Prospect $prospect): string
    {
        $contact = $prospect->getPrimaryContact();
        $greeting = $contact ? 'Bonjour ' . $contact->getFirstName() : 'Bonjour';

        return <<<HTML
{$greeting},

Je me permets de vous contacter car je pense que mes services pourraient vous intéresser.

Je suis Fabrice Dhuicque, développeur web freelance basé en Bretagne. Je crée des sites web sur-mesure, performants et éco-responsables.

Seriez-vous disponible pour un court échange téléphonique ou en visio afin de discuter de vos besoins ?

Bien cordialement,
Fabrice Dhuicque
HTML;
    }

    public function getDefaultSubject(Prospect $prospect): string
    {
        return 'Création de votre site web - ' . $prospect->getCompanyName();
    }
}
