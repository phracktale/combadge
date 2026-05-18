# Spec — Landing publique Skald (vitrine + doc + login popin)

> Doc avant code (CLAUDE.md §2.2). Pas de TDD. Front → OPQUAST / RGAA.
> Décisions validées : Tailwind via **symfonycasts/tailwind-bundle**,
> doc/wiki = **rendu du markdown du repo** (league/commonmark), routes
> **`/` public + app authentifiée sous `/app`**.

## 1. Objectif

Site vitrine public présentant Skald : hero, présentation, scroll
**parallaxe** déroulant les **phases du projet**, section **doc/wiki dev**,
lien **GitHub**, et **connexion en popin** (modale) depuis le menu. Servi par
le backend Symfony existant (Twig), domaine `skald.phracktale.com`.

## 2. Restructuration des routes

| Route | Accès | Rôle |
|---|---|---|
| `/` | public | Landing (hero, phases parallaxe, CTA) |
| `/docs` | public | Index doc/wiki développeur |
| `/docs/{page}` | public | Page markdown rendue (liste blanche de fichiers) |
| `/login`, `/login/check` | public | Magic-link (page + cible popin) |
| `/app` | ROLE_USER | Liste enregistrements (ex-`/`) |
| `/app/audio/{id}` | ROLE_USER | Streaming audio (ex-`/audio/{id}`) |
| `/api/*`, `/health` | public | Inchangé (Bearer / sonde) |

`security.yaml access_control` : `^/app` = ROLE_USER ; `^/(\|docs\|login)`,
`^/api`, `^/health` = PUBLIC_ACCESS. `LoginEntryPoint` redirige vers `/login`.
Après login → redirection `/app`. La modale poste sur `/login` (flux
magic-link inchangé) ; `/login` reste une page de repli (sans JS).

## 3. Doc/wiki (rendu markdown)

- `DocController` : liste blanche de documents du repo rendus en HTML via
  `league/commonmark` (sécurité : pas de chemin arbitraire, pas d'inclusion
  hors liste).
- Documents exposés : `docs/philosophy.md`, `docs/architecture.md`,
  `docs/rfc-template.md`, `docs/credits.md`, `skald/docs/*.md` (specs).
- Rendu : CommonMark avec extension GFM tables ; HTML échappé (pas de HTML
  brut injecté). Sommaire = liste blanche en dur (clé → titre → chemin).

## 4. Tailwind (tailwind-bundle)

- `symfonycasts/tailwind-bundle` (binaire standalone, **pas de Node**),
  intégré AssetMapper. `assets/styles/app.css` avec directives Tailwind.
- Build image Docker : `php bin/console tailwind:build --minify` puis
  `php bin/console asset-map:compile` (étape ajoutée au Dockerfile).
- Le `<link>` CSS via `asset()` AssetMapper (plus d'`importmap('app')` JS —
  on garde l'UI sans JS de framework).

## 5. Animations & parallaxe (RGAA)

- Parallaxe : CSS (`background-attachment: fixed` / `transform` lié au
  scroll via une poignée de lignes de JS vanilla, sans dépendance).
- Animations « discrètes » : transitions/`@keyframes` CSS sobres
  (apparition au scroll via `IntersectionObserver`, vanilla).
- **`@media (prefers-reduced-motion: reduce)`** : neutralise parallaxe et
  animations (exigence RGAA — pas d'animation imposée).
- Sans JS : le contenu reste entièrement lisible (dégradation gracieuse).

## 6. Contenu — phases du projet (source : contexte.md §9, README)

Sections scrollées :
1. **Le projet** : Skald, BYO-AI, open source sans abonnement.
2. **Phase 0** — bring-up firmware (hello world validé).
3. **Phase 1** — capture audio device → backend d'ingestion + UI.
4. **Phase 2+** — diarisation, traduction, recherche sémantique (roadmap).
5. **Architecture** — device → backend → pipeline IA (schéma).
6. CTA : doc dev, GitHub, connexion.

GitHub : `https://github.com/phracktale/combadge`.

## 7. Login popin (modale accessible)

- Bouton « Connexion » dans le menu → ouvre une `<dialog>` (ou div
  `role="dialog" aria-modal="true"`) contenant le formulaire email.
- Accessibilité : focus déplacé dans la modale à l'ouverture, **piège de
  focus**, fermeture **Échap** + bouton, restitution du focus au
  déclencheur, `aria-labelledby`. Fonctionne au clavier.
- JS vanilla minimal (ouverture/fermeture/focus). Repli : lien vers la page
  `/login` si JS désactivé.

## 8. Plan d'implémentation (sans test)

1. `composer require symfonycasts/tailwind-bundle league/commonmark`.
2. `assets/styles/app.css` + config Tailwind + base.html.twig (lien CSS).
3. Déplacer `RecordingViewController` sous `/app` ; `security.yaml`.
4. `LandingController` (`/`) + templates landing (hero, sections parallaxe,
   modale login) + JS vanilla accessible.
5. `DocController` (`/docs`, `/docs/{page}`) + rendu CommonMark + templates.
6. Dockerfile : étape build Tailwind + asset-map:compile.
7. Vérif : `cache:clear`, `lint:container`, `debug:router`, build Docker
   local (Tailwind compile), pages rendues. E2E déploiement = Thor ensuite.

## 9. Inconnues / risques

- Build Tailwind dans l'image FrankenPHP (binaire standalone téléchargé au
  build : accès réseau pendant `docker build` — à confirmer, B-build).
- `asset-map:compile` + chemin des assets en prod (APP_ENV=prod).
- Parallaxe `background-attachment: fixed` : perfs mobiles → fallback.

## 10. Sources

- `_DOCS/contexte.md` §9 (roadmap), `README.md`, `docs/*`.
- symfonycasts/tailwind-bundle, league/commonmark (docs officielles).
- RGAA 4.1 (animations, modale, prefers-reduced-motion).
