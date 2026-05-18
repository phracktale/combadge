<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Vitrine publique de Skald (landing). Accessible sans authentification.
 */
class LandingController extends AbstractController
{
    public const GITHUB_URL = 'https://github.com/phracktale/combadge';

    #[Route('/', name: 'app_home', methods: ['GET'])]
    public function home(): Response
    {
        // Étapes présentées au scroll (source : roadmap projet).
        $phases = [
            [
                'tag' => 'Phase 0',
                'title' => 'Bring-up firmware',
                'text' => 'Carte XIAO ESP32-S3 : chaîne de build, flash, '
                    . 'Serial, Wi-Fi validés (hello world).',
            ],
            [
                'tag' => 'Phase 1',
                'title' => 'Capture & ingestion',
                'text' => 'Le device capte l’audio et l’envoie à un backend '
                    . 'd’ingestion (Symfony / API Platform / PostgreSQL).',
            ],
            [
                'tag' => 'Phase 1bis',
                'title' => 'Consultation web',
                'text' => 'UI privée pour écouter les enregistrements '
                    . 'horodatés, connexion sans mot de passe (lien email).',
            ],
            [
                'tag' => 'Phase 2+',
                'title' => 'Pipeline IA',
                'text' => 'Transcription, diarisation, traduction et '
                    . 'recherche sémantique — votre IA, vos données.',
            ],
        ];

        return $this->render('landing/home.html.twig', [
            'phases' => $phases,
            'github' => self::GITHUB_URL,
        ]);
    }
}
