# Spec — Backend d'ingestion audio (Skald v0.1)

> Documentation écrite avant le code (CLAUDE.md §2.2). Pas de TDD (décision
> mainteneur 2026-05-17), vérification manuelle par `curl`.
>
> Approche retenue (validée) : **B — stack cible, compatible Homelab**.
> Symfony 7 + API Platform 4 + Doctrine + PostgreSQL 16 (pgvector),
> conteneurisé FrankenPHP, déployable sur le homelab Phracktale.

## 1. Objectif

Exposer une API REST qui reçoit les enregistrements audio du device Skald,
stocke le fichier et ses métadonnées, et sert de socle au pipeline de
traitement (transcription, etc., phases ultérieures).

Périmètre v0.1 : **ingestion uniquement** (réception + persistance). Pas de
traitement IA, pas d'app mobile, pas d'authentification (voir §6).

## 2. Architecture (rappel cible + intégration Homelab)

```
Device Skald ──HTTPS──> Heimdall (192.168.1.195, Nginx + Let's Encrypt)
                              │  api.skald.phracktale.com
                              ▼
                        Thor (192.168.1.36)
                        homelab_skald_app   (FrankenPHP/Symfony, Caddy TLS interne)
                        homelab_skald_db    (pgvector/pgvector:pg16, LAN only)
```

Conventions Homelab respectées (cf. `HOMELAB/_SERVICES/CLAUDE.md`,
`port-routing-table.md`, pattern FrankenPHP) :

| Élément | Valeur |
|---|---|
| Domaine public | `api.skald.phracktale.com` |
| Dossier service | `HOMELAB/_SERVICES/api.skald.phracktale.com/` |
| Conteneur app | `homelab_skald_app` |
| Conteneur DB | `homelab_skald_db` |
| Volume DB | `homelab_skald_db_data` |
| Réseau | `homelab_skald_network` |
| Port HTTP (Thor) | **18090** |
| Port HTTPS (Thor) | **18453** (= 18090 + 363, convention paire) |
| Port Postgres (Thor) | **18092** — bind `192.168.1.36` (LAN only) |
| TLS interne | Caddy/FrankenPHP (`CADDY_TLS=internal` en dev, vide en prod) |
| Secrets | `.env.example` commité ; `.env` généré sur Thor par `bootstrap.sh` |
| `restart` | `unless-stopped` |

Ports choisis libres dans la plage 18xxx (vérifié dans
`port-routing-table.md` : 18080-18082, 18173, 18200, 18300-18303, 18443,
18445 déjà pris ; 18090/18453/18092 libres).

## 3. Modèle de données

Entité Doctrine `Recording` (table `recording`) :

| Champ | Type | Note |
|---|---|---|
| `id` | UUID | clé primaire |
| `originalFilename` | string | nom fourni par le client |
| `storagePath` | string | chemin relatif du fichier stocké |
| `mimeType` | string | ex. `audio/wav`, `audio/ogg` |
| `sizeBytes` | int | taille du fichier |
| `deviceId` | string nullable | identifiant device (optionnel v0.1) |
| `recordedAt` | datetime nullable | horodatage capture (optionnel) |
| `uploadedAt` | datetime | rempli serveur |
| `status` | enum | `received` (v0.1) ; valeurs futures : `processing`, `done` |

`pgvector` est installé dès maintenant (extension `vector`) mais aucune
colonne d'embedding en v0.1 — préparé pour la recherche sémantique (Phase 2).

## 4. Contrat d'API

Ressource API Platform `Recording`, endpoint d'upload :

- **`POST /api/recordings`** — `multipart/form-data`
  - `file` (requis) : le fichier audio
  - `deviceId` (optionnel) : string
  - `recordedAt` (optionnel) : ISO 8601
  - Réponses :
    - `201 Created` + JSON de la ressource (`id`, `originalFilename`,
      `mimeType`, `sizeBytes`, `uploadedAt`, `status`)
    - `400` si `file` absent
    - `415` si type MIME non audio
- **`GET /api/recordings`** — liste paginée (lecture, vérif manuelle)
- **`GET /api/recordings/{id}`** — détail
- **`GET /api/docs`** — documentation OpenAPI/Swagger (API Platform)
- **`GET /health`** — `{"status":"ok"}` (sonde Heimdall/healthcheck Docker)

Stockage fichier : volume monté, chemin
`var/uploads/<uuid>.<ext>` (le dossier `var/` est déjà gitignoré côté skald).
Limites PHP à relever pour l'audio long :
`upload_max_filesize` / `post_max_size` (documenté dans le Dockerfile/php.ini).

## 5. Implémentation (sans test)

1. Scaffolding Symfony 7 (`symfony new skald/backend --webapp` ou squelette
   API + composer require api-platform).
2. `composer require api-platform/core doctrine` + config `DATABASE_URL`
   PostgreSQL.
3. Entité `Recording` + migration Doctrine ; activation extension `vector`.
4. Endpoint d'upload : un *state processor* API Platform (ou contrôleur
   dédié) qui valide le fichier, le stocke, persiste l'entité.
5. Endpoint `/health`.
6. Dockerfile FrankenPHP + `compose.yaml` (app + db pgvector) + `.env.example`.
7. Artefacts Homelab dans `HOMELAB/_SERVICES/api.skald.phracktale.com/`
   (compose, nginx vhost, scripts bootstrap/deploy, CLAUDE.md de service).
8. Mise à jour `HOMELAB/.../port-routing-table.md` (entrée Skald).
9. Vérif manuelle locale : `docker compose up`, `curl` upload + `GET`.

## 6. Sécurité v0.1 (décisions à acter)

- **Pas d'authentification** sur l'API en v0.1 (dev/local). À durcir avant
  toute exposition publique réelle (token device, ou mTLS). Documenté ici
  comme dette explicite, alignée sur `SECURITY.md`.
- Postgres : binding `192.168.1.36` (LAN only), jamais exposé Internet.
- Secrets (`POSTGRES_PASSWORD`, `APP_SECRET`) générés sur Thor par
  `bootstrap.sh` (`openssl rand`), jamais commités.
- Le fichier `.env` réel vit uniquement sur Thor.

## 7. Répartition deux dépôts

| Dépôt | Contenu |
|---|---|
| `combadge` (skald) | code Symfony (`skald/backend/`), cette spec |
| `HOMELAB` | `_SERVICES/api.skald.phracktale.com/` (compose, nginx, scripts, CLAUDE.md), maj `port-routing-table.md` |

Toute modification du dépôt HOMELAB est une action structurante sur un
autre projet : validation explicite du mainteneur avant d'écrire dedans.

## 8. Sources

- `HOMELAB/_SERVICES/CLAUDE.md` (conventions services)
- `HOMELAB/.../docs/infra/port-routing-table.md` (allocation ports)
- `HOMELAB/.../docs/infra/homelab-minimal-php-symfony-frankenphp-tutorial.md`
- `HOMELAB/_SERVICES/api.buvette.phracktale.com/` (service de référence)
- `_DOCS/contexte.md` §5 (stack cible : Symfony 7, API Platform, pgvector)
