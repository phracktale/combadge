<?php

namespace App\Security;

use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Http\EntryPoint\AuthenticationEntryPointInterface;

/**
 * Point d'entrée d'authentification du firewall : un accès non authentifié
 * à l'UI est redirigé vers /login (au lieu d'un 401 brut). C'est le
 * mécanisme Symfony idiomatique pour ça (remplace l'ancien subscriber).
 */
final class LoginEntryPoint implements AuthenticationEntryPointInterface
{
    public function __construct(private readonly UrlGeneratorInterface $urls)
    {
    }

    public function start(Request $request, ?\Throwable $authException = null): Response
    {
        return new RedirectResponse($this->urls->generate('app_login'));
    }
}
