<?php

namespace App\Service;

use App\Entity\User;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Twig\Environment;

class EmailVerificationService
{
    public function __construct(
        private readonly MailerInterface $mailer,
        private readonly Environment $twig
    ) {
    }

    public function sendVerificationEmail(User $user, string $frontendUrl): void
    {
        $verificationUrl = $frontendUrl . '/verify-email?token=' . $user->getVerificationToken();

        $email = (new Email())
            ->from('noreply@trackerpro.com')
            ->to($user->getEmail())
            ->subject('Potwierdź swój adres email - Tracker Pro')
            ->html($this->twig->render('emails/verification.html.twig', [
                'firstName' => $user->getFirstName(),
                'verificationUrl' => $verificationUrl,
            ]));

        $this->mailer->send($email);
    }
}
