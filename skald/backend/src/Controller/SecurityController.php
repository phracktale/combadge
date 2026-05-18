<?php

namespace App\Controller;

use App\Service\MagicLinkService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Authentification sans mot de passe (lien magique email).
 *
 * /login (GET)        : formulaire email
 * /login (POST)       : envoi du lien (réponse neutre)
 * /login/check (GET)  : validation du token → ouverture de session
 * /logout             : interception firewall (voir security.yaml)
 */
class SecurityController extends AbstractController
{
    #[Route('/login', name: 'app_login', methods: ['GET'])]
    public function login(): Response
    {
        return $this->render('security/login.html.twig');
    }

    #[Route('/login', name: 'app_login_submit', methods: ['POST'])]
    public function submit(Request $request, MagicLinkService $magic): Response
    {
        $email = (string) $request->request->get('email', '');
        $magic->requestLink($email);
        // Message neutre : ne révèle pas si l'email est autorisé.
        $this->addFlash('info', 'Si cet email est autorisé, un lien de connexion vient d’être envoyé.');

        return $this->redirectToRoute('app_login');
    }

    /**
     * La connexion est gérée par MagicLinkAuthenticator (firewall), qui
     * intercepte cette route, consomme le token et ouvre la session. Ce
     * contrôleur n'est normalement pas atteint ; redirection défensive.
     */
    #[Route('/login/check', name: 'app_login_check', methods: ['GET'])]
    public function check(): Response
    {
        return $this->redirectToRoute('app_recordings');
    }

    #[Route('/logout', name: 'app_logout')]
    public function logout(): never
    {
        // Interception par le LogoutListener du firewall (security.yaml).
        throw new \LogicException('Interceptée par le firewall logout.');
    }
}
