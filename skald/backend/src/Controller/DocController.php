<?php

namespace App\Controller;

use League\CommonMark\GithubFlavoredMarkdownConverter;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Doc/wiki développeur : rend le markdown du dépôt (source unique, DRY).
 *
 * Liste blanche stricte slug → fichier : aucun chemin arbitraire, aucune
 * inclusion hors liste. HTML brut du markdown non autorisé (CommonMark
 * échappe par défaut) → pas d'injection.
 */
class DocController extends AbstractController
{
    /** slug => [titre, chemin relatif sous dev-docs/] */
    private const PAGES = [
        'philosophy' => ['Philosophie', 'philosophy.md'],
        'architecture' => ['Architecture', 'architecture.md'],
        'rfc-template' => ['Gabarit RFC', 'rfc-template.md'],
        'credits' => ['Crédits', 'credits.md'],
        'firmware-hello-world' => ['Firmware — hello world', 'skald/firmware-hello-world.md'],
        'firmware-audio-capture' => ['Firmware — capture audio', 'skald/firmware-audio-capture.md'],
        'backend-ingestion' => ['Backend — ingestion', 'skald/backend-ingestion.md'],
        'web-ui' => ['UI web', 'skald/web-ui.md'],
        'landing-front' => ['Landing', 'skald/landing-front.md'],
    ];

    public function __construct(
        #[Autowire('%kernel.project_dir%')]
        private readonly string $projectDir,
    ) {
    }

    #[Route('/docs', name: 'app_docs', methods: ['GET'])]
    public function index(): Response
    {
        $index = [];
        foreach (self::PAGES as $slug => [$title]) {
            $index[$slug] = $title;
        }

        return $this->render('doc/index.html.twig', ['pages' => $index]);
    }

    #[Route('/docs/{slug}', name: 'app_doc_page', methods: ['GET'])]
    public function page(string $slug): Response
    {
        if (!isset(self::PAGES[$slug])) {
            throw $this->createNotFoundException('Document inconnu.');
        }
        [$title, $rel] = self::PAGES[$slug];

        $path = $this->projectDir . '/dev-docs/' . $rel;
        if (!is_file($path)) {
            throw $this->createNotFoundException('Document indisponible.');
        }

        $converter = new GithubFlavoredMarkdownConverter([
            'html_input' => 'escape',
            'allow_unsafe_links' => false,
        ]);
        $html = $converter->convert(file_get_contents($path))->getContent();

        return $this->render('doc/page.html.twig', [
            'title' => $title,
            'html' => $html,
            'pages' => array_map(fn ($p) => $p[0], self::PAGES),
            'current' => $slug,
        ]);
    }
}
