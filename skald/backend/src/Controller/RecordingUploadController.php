<?php

namespace App\Controller;

use App\Entity\Recording;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Ingestion d'un enregistrement audio depuis le device Skald.
 *
 * POST /api/recordings (multipart/form-data)
 *   - file        (requis)   : le fichier audio
 *   - deviceId    (optionnel): identifiant du device
 *   - recordedAt  (optionnel): horodatage de capture, ISO 8601
 *
 * v0.1 : pas d'authentification (dette documentée dans skald/docs/backend-ingestion.md).
 */
class RecordingUploadController
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        #[Autowire('%kernel.project_dir%')]
        private readonly string $projectDir,
    ) {
    }

    #[Route('/api/recordings', name: 'recording_upload', methods: ['POST'])]
    public function __invoke(Request $request): JsonResponse
    {
        $file = $request->files->get('file');

        if ($file === null) {
            return new JsonResponse(
                ['error' => 'Champ "file" manquant (multipart/form-data).'],
                Response::HTTP_BAD_REQUEST,
            );
        }

        $mimeType = $file->getMimeType() ?? $file->getClientMimeType();
        if (!str_starts_with($mimeType, 'audio/')) {
            return new JsonResponse(
                ['error' => sprintf('Type non audio refusé : %s', $mimeType)],
                Response::HTTP_UNSUPPORTED_MEDIA_TYPE,
            );
        }

        $uploadsDir = $this->projectDir . '/var/uploads';
        if (!is_dir($uploadsDir) && !mkdir($uploadsDir, 0775, true) && !is_dir($uploadsDir)) {
            return new JsonResponse(
                ['error' => 'Stockage indisponible.'],
                Response::HTTP_INTERNAL_SERVER_ERROR,
            );
        }

        $recording = new Recording();
        $extension = $file->guessExtension() ?: 'bin';
        $storedName = $recording->getId() . '.' . $extension;
        $file->move($uploadsDir, $storedName);

        $recording->setOriginalFilename($file->getClientOriginalName() ?: $storedName);
        $recording->setStoragePath('var/uploads/' . $storedName);
        $recording->setMimeType($mimeType);
        $recording->setSizeBytes(filesize($uploadsDir . '/' . $storedName) ?: 0);
        $recording->setDeviceId($request->request->get('deviceId'));

        $recordedAtRaw = $request->request->get('recordedAt');
        if ($recordedAtRaw !== null && $recordedAtRaw !== '') {
            try {
                $recording->setRecordedAt(new \DateTimeImmutable($recordedAtRaw));
            } catch (\Exception) {
                return new JsonResponse(
                    ['error' => 'recordedAt invalide (ISO 8601 attendu).'],
                    Response::HTTP_BAD_REQUEST,
                );
            }
        }

        $this->em->persist($recording);
        $this->em->flush();

        return new JsonResponse([
            'id' => $recording->getId(),
            'originalFilename' => $recording->getOriginalFilename(),
            'mimeType' => $recording->getMimeType(),
            'sizeBytes' => $recording->getSizeBytes(),
            'uploadedAt' => $recording->getUploadedAt()->format(\DateTimeInterface::ATOM),
            'status' => $recording->getStatus(),
        ], Response::HTTP_CREATED);
    }
}
