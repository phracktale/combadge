<?php

namespace App\Controller;

use App\Repository\RecordingRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * UI web de consultation : liste des enregistrements + lecture audio.
 * Protégé par la session (security.yaml : tout hors /api, /login, /health
 * exige ROLE_USER).
 */
class RecordingViewController extends AbstractController
{
    public function __construct(
        #[Autowire('%kernel.project_dir%')]
        private readonly string $projectDir,
    ) {
    }

    #[Route('/', name: 'app_recordings', methods: ['GET'])]
    public function list(RecordingRepository $recordings): Response
    {
        return $this->render('recording/list.html.twig', [
            'recordings' => $recordings->findBy([], ['uploadedAt' => 'DESC']),
        ]);
    }

    #[Route('/audio/{id}', name: 'app_audio', methods: ['GET'])]
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

        // BinaryFileResponse gère nativement l'en-tête Range (seek du lecteur).
        $response = new BinaryFileResponse($path);
        $response->headers->set('Content-Type', $recording->getMimeType());
        $response->setAutoLastModified();

        return $response;
    }
}
