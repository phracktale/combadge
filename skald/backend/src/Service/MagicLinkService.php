<?php

namespace App\Service;

use App\Entity\LoginToken;
use App\Entity\User;
use App\Repository\LoginTokenRepository;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;

/**
 * Authentification sans mot de passe par lien magique.
 *
 * - requestLink() : si l'email est sur la liste blanche, génère un token à
 *   usage unique (stocké haché), envoie le lien par email et le logue
 *   (pratique en local sans SMTP réel).
 * - consume() : valide un token et renvoie l'utilisateur, ou null.
 *
 * Réponse volontairement neutre côté contrôleur : pas d'oracle d'existence.
 */
class MagicLinkService
{
    private const TOKEN_TTL = '+15 minutes';

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly UserRepository $users,
        private readonly LoginTokenRepository $tokens,
        private readonly MailerInterface $mailer,
        private readonly LoggerInterface $logger,
        #[Autowire('%env(SKALD_ALLOWED_EMAILS)%')]
        private readonly string $allowedEmails,
        #[Autowire('%env(APP_BASE_URL)%')]
        private readonly string $baseUrl,
        #[Autowire('%env(MAILER_FROM)%')]
        private readonly string $mailerFrom,
    ) {
    }

    private function isAllowed(string $email): bool
    {
        foreach (explode(',', strtolower($this->allowedEmails)) as $allowed) {
            if (trim($allowed) !== '' && trim($allowed) === strtolower($email)) {
                return true;
            }
        }

        return false;
    }

    public function requestLink(string $email): void
    {
        $email = trim($email);
        if (!$this->isAllowed($email)) {
            // Silencieux : pas de fuite d'information sur les emails autorisés.
            return;
        }

        $user = $this->users->findOneByEmail($email);
        if ($user === null) {
            $user = (new User())->setEmail($email);
            $this->em->persist($user);
        }

        $token = bin2hex(random_bytes(32));
        $now = new \DateTimeImmutable();
        $loginToken = new LoginToken(
            $user,
            hash('sha256', $token),
            $now->modify(self::TOKEN_TTL),
        );
        $this->em->persist($loginToken);
        $this->em->flush();

        $link = rtrim($this->baseUrl, '/') . '/login/check?token=' . $token;

        // Loggué pour pouvoir tester le flux en local sans SMTP réel.
        $this->logger->info('Lien magique généré', ['email' => $email, 'link' => $link]);

        $this->mailer->send(
            (new Email())
                ->from($this->mailerFrom)
                ->to($email)
                ->subject('Votre lien de connexion Skald')
                ->text("Bonjour,\n\nPour vous connecter à Skald, ouvrez ce lien "
                    . "(valable 15 minutes, usage unique) :\n\n$link\n\n"
                    . "Si vous n'êtes pas à l'origine de cette demande, ignorez "
                    . "cet email.\n")
        );
    }

    public function consume(string $token): ?User
    {
        if ($token === '') {
            return null;
        }
        $loginToken = $this->tokens->findOneByHash(hash('sha256', $token));
        if ($loginToken === null) {
            return null;
        }
        $now = new \DateTimeImmutable();
        if (!$loginToken->isUsable($now)) {
            return null;
        }
        $loginToken->markUsed($now);
        $this->em->flush();

        // Renvoie l'entité PLEINEMENT chargée, pas le proxy Doctrine de
        // l'association : un proxy non initialisé sérialisé en session fait
        // échouer getUserIdentifier() ($email non initialisé) sur les
        // requêtes suivantes (500 sur /app). getId() n'initialise pas le
        // proxy ; find() renvoie l'entité hydratée.
        return $this->users->find($loginToken->getUser()->getId());
    }
}
