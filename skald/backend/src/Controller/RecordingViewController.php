<?php

namespace App\Controller;

use App\Repository\RecordingRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * UI web de consultation : liste, lecture audio, suppression.
 * Protégé par la session (security.yaml : ^/app = ROLE_USER).
 */
class RecordingViewController extends AbstractController
{
    public function __construct(
        #[Autowire('%kernel.project_dir%')]
        private readonly string $projectDir,
        private readonly EntityManagerInterface $em,
        private readonly LoggerInterface $logger,
    ) {
    }

    #[Route('/app', name: 'app_recordings', methods: ['GET'])]
    public function list(RecordingRepository $recordings): Response
    {
        return $this->render('recording/list.html.twig', [
            'recordings' => $recordings->findBy([], ['uploadedAt' => 'DESC']),
        ]);
    }

    #[Route('/app/audio/{id}', name: 'app_audio', methods: ['GET'])]
    public function audio(string $id, RecordingRepository $recordings): Response
    {
        $recording = $recordings->find($id);
        if ($recording === null) {
            throw $this->createNotFoundException('Enregistrement introuvable.');
        }

        $path = $this->projectDir . '/' . $recording->getStoragePath();
        if (!is_file($path)) {
            throw $this->createNotFoundException('Fichier audio absent.');
        }

        $response = new BinaryFileResponse($path);
        $response->headers->set('Content-Type', $recording->getMimeType());
        $response->setAutoLastModified();

        return $response;
    }

    /**
     * Étape 1 : reçoit la sélection (ids[]) et affiche la confirmation.
     * Action irréversible → on ne supprime rien ici.
     */
    #[Route('/app/recordings/delete', name: 'app_recordings_delete', methods: ['POST'])]
    public function deletePrepare(Request $request, RecordingRepository $recordings): Response
    {
        $ids = (array) $request->request->all('ids');
        $items = $ids === [] ? [] : $recordings->findBy(['id' => $ids]);

        if ($items === []) {
            $this->addFlash('error', 'Aucun enregistrement sélectionné.');

            return $this->redirectToRoute('app_recordings');
        }

        return $this->render('recording/delete_confirm.html.twig', [
            'recordings' => $items,
        ]);
    }

    /**
     * Étape 2 : confirmation + CSRF → suppression entité ET fichier.
     */
    #[Route('/app/recordings/delete/confirm', name: 'app_recordings_delete_confirm', methods: ['POST'])]
    public function deleteConfirm(Request $request, RecordingRepository $recordings): Response
    {
        if (!$this->isCsrfTokenValid('delete_recordings', (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'Jeton de sécurité invalide. Réessayez.');

            return $this->redirectToRoute('app_recordings');
        }

        $ids = (array) $request->request->all('ids');
        $items = $ids === [] ? [] : $recordings->findBy(['id' => $ids]);

        $count = 0;
        foreach ($items as $recording) {
            $path = $this->projectDir . '/' . $recording->getStoragePath();
            if (is_file($path) && !@unlink($path)) {
                $this->logger->warning('Suppression fichier impossible', ['path' => $path]);
            }
            $this->em->remove($recording);
            ++$count;
        }
        $this->em->flush();

        $this->addFlash('info', sprintf('%d enregistrement(s) supprimé(s).', $count));

        return $this->redirectToRoute('app_recordings');
    }
}
