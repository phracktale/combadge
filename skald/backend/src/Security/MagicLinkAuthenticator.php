<?php

namespace App\Security;

use App\Service\MagicLinkService;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAuthenticationException;
use Symfony\Component\Security\Http\Authenticator\AbstractAuthenticator;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
use Symfony\Component\Security\Http\Authenticator\Passport\SelfValidatingPassport;

/**
 * Authentification par lien magique. Déclenché sur la route
 * app_login_check (/login/check?token=…).
 *
 * En passant par un vrai Authenticator, Symfony gère lui-même le cycle de
 * vie du token de sécurité (création, persistance session, refresh via le
 * user provider à chaque requête). On évite ainsi le bug de sérialisation
 * manuelle de l'entité User en session.
 */
final class MagicLinkAuthenticator extends AbstractAuthenticator
{
    public function __construct(
        private readonly MagicLinkService $magic,
        private readonly UrlGeneratorInterface $urls,
    ) {
    }

    public function supports(Request $request): ?bool
    {
        return $request->attributes->get('_route') === 'app_login_check';
    }

    public function authenticate(Request $request): Passport
    {
        $user = $this->magic->consume((string) $request->query->get('token', ''));
        if ($user === null) {
            throw new CustomUserMessageAuthenticationException('Lien invalide ou expiré.');
        }

        // SelfValidatingPassport : le token a déjà été validé/consommé.
        // Le UserBadge porte l'identifiant ; aux requêtes suivantes Symfony
        // recharge l'utilisateur via le provider (entity) → pas de proxy
        // sérialisé en session.
        return new SelfValidatingPassport(
            new UserBadge($user->getUserIdentifier(), static fn () => $user)
        );
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token, string $firewallName): ?Response
    {
        return new RedirectResponse($this->urls->generate('app_recordings'));
    }

    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): ?Response
    {
        $request->getSession()->getFlashBag()->add(
            'error',
            'Lien invalide ou expiré. Recommencez la connexion.'
        );

        return new RedirectResponse($this->urls->generate('app_login'));
    }
}
