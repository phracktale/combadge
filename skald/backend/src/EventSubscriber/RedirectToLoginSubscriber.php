<?php

namespace App\EventSubscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\Security\Core\Exception\AuthenticationException;

/**
 * Sans authenticator de firewall, un accès non authentifié lève une
 * AccessDeniedException → 403. Pour une UI web on préfère rediriger vers
 * /login. /api (Bearer) et /health ne sont pas concernés.
 */
final class RedirectToLoginSubscriber implements EventSubscriberInterface
{
    public function __construct(private readonly UrlGeneratorInterface $urls)
    {
    }

    public static function getSubscribedEvents(): array
    {
        return [KernelEvents::EXCEPTION => 'onException'];
    }

    public function onException(ExceptionEvent $event): void
    {
        $e = $event->getThrowable();
        if (!$e instanceof AccessDeniedException && !$e instanceof AuthenticationException) {
            return;
        }

        $path = $event->getRequest()->getPathInfo();
        if (str_starts_with($path, '/api')
            || str_starts_with($path, '/login')
            || str_starts_with($path, '/health')) {
            return;
        }

        $event->setResponse(new RedirectResponse(
            $this->urls->generate('app_login')
        ));
    }
}
