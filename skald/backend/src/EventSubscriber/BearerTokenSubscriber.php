<?php

namespace App\EventSubscriber;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Protège toute l'API par un Bearer token statique partagé.
 *
 * Tout chemin sous /api exige l'en-tête « Authorization: Bearer <token> »
 * correspondant à SKALD_API_TOKEN. /health reste public (sonde Docker /
 * Heimdall).
 *
 * v0.1 : secret unique partagé (changeable via l'env, sans redéploiement de
 * code). À faire évoluer vers une auth par device si besoin (cf. SECURITY.md).
 *
 * Fail-closed : si SKALD_API_TOKEN est vide, l'API est entièrement refusée.
 */
final class BearerTokenSubscriber implements EventSubscriberInterface
{
    public function __construct(
        #[Autowire('%env(SKALD_API_TOKEN)%')]
        private readonly string $apiToken,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        // Avant le routing (priorité élevée) : on filtre sur le chemin.
        return [KernelEvents::REQUEST => ['onRequest', 100]];
    }

    public function onRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $path = $event->getRequest()->getPathInfo();
        if (!str_starts_with($path, '/api')) {
            return; // /health et le reste : publics.
        }

        if ($this->apiToken === '') {
            $event->setResponse(new JsonResponse(
                ['error' => 'API token non configuré (SKALD_API_TOKEN).'],
                Response::HTTP_SERVICE_UNAVAILABLE,
            ));

            return;
        }

        $auth = $event->getRequest()->headers->get('Authorization', '');
        $provided = '';
        if (str_starts_with($auth, 'Bearer ')) {
            $provided = substr($auth, 7);
        }

        if ($provided === '' || !hash_equals($this->apiToken, $provided)) {
            $event->setResponse(new JsonResponse(
                ['error' => 'Bearer token manquant ou invalide.'],
                Response::HTTP_UNAUTHORIZED,
            ));
        }
    }
}
