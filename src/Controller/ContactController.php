<?php

namespace App\Controller;

use App\Entity\ContactMessage;
use App\Form\ContactType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Routing\Annotation\Route;

class ContactController extends AbstractController
{
    #[Route('/contact', name: 'app_contact')]
    public function index(Request $request, EntityManagerInterface $entityManager, MailerInterface $mailer): Response
    {
        $contactMessage = new ContactMessage();
        $form = $this->createForm(ContactType::class, $contactMessage);

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Sauvegarder le message en base de données
            $entityManager->persist($contactMessage);
            $entityManager->flush();

            try {
                // 1. Email de confirmation au demandeur
                $confirmationEmail = (new TemplatedEmail())
                    ->from(new Address('no-reply@alre-web.bzh', 'Alré Web'))
                    ->to($contactMessage->getEmail())
                    ->subject('Confirmation de votre demande de contact')
                    ->htmlTemplate('emails/contact_confirmation.html.twig')
                    ->context([
                        'contact' => $contactMessage,
                    ]);

                $mailer->send($confirmationEmail);

                // 2. Email de notification pour l'admin
                $notificationEmail = (new TemplatedEmail())
                    ->from(new Address('no-reply@alre-web.bzh', 'Alré Web'))
                    ->to('contact@alre-web.bzh')
                    ->replyTo($contactMessage->getEmail())
                    ->subject('Nouveau message de contact - ' . $contactMessage->getFirstName() . ' ' . $contactMessage->getLastName())
                    ->htmlTemplate('emails/contact_notification.html.twig')
                    ->context([
                        'contact' => $contactMessage,
                    ]);

                $mailer->send($notificationEmail);

                $this->addFlash('success', 'Votre message a bien été envoyé ! Vous allez recevoir un email de confirmation. Je vous répondrai dans les plus brefs délais (sous 24h maximum).');
            } catch (\Exception $e) {
                // Si l'envoi d'email échoue, on informe quand même l'utilisateur que le message est sauvegardé
                $this->addFlash('warning', 'Votre message a été enregistré mais l\'email de confirmation n\'a pas pu être envoyé. Erreur: ' . $e->getMessage());
            }

            // Rediriger pour éviter la resoumission du formulaire
            return $this->redirectToRoute('app_contact');
        }

        return $this->render('contact/index.html.twig', [
            'contactForm' => $form->createView(),
        ]);
    }
}
