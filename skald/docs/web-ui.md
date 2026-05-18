# Spec — UI web Skald : consultation + auth magic-link (v0.1)

> Doc avant code (CLAUDE.md §2.2). Pas de TDD (décision mainteneur).
> Front → exigences **OPQUAST / RGAA** (CLAUDE.md §5).
>
> Décisions validées : UI **Twig dans le backend Symfony**, auth **maison
> magic-link email**, **mono-utilisateur** (liste blanche d'emails).

## 1. Objectif

Une interface web pour **consulter et écouter** les enregistrements reçus du
device, accessible après **connexion sans mot de passe** (lien/token envoyé
par email). Domaine : **`skald.phracktale.com`** (distinct de
`api.skald.phracktale.com`, qui reste l'API device).

## 2. Périmètre

### Inclus
- Page **login** : saisie email → envoi d'un lien magique.
- Vérification du lien → session authentifiée.
- Page **liste** : enregistrements (`Recording`) triés par date, avec
  **horodatage lisible** (`uploadedAt`, `recordedAt` si présent), durée si
  connue, `deviceId`.
- **Lecture audio** dans la page (élément `<audio>` natif), via une route de
  streaming **protégée par la session**.
- Déconnexion.

### Exclus (v0.1)
- Inscription publique, multi-utilisateurs, rôles.
- Suppression/édition des enregistrements, recherche, pagination avancée
  (liste simple suffisante au début).
- Transcription/traduction (phases pipeline ultérieures).

## 3. Deux surfaces de sécurité distinctes (ne pas mélanger)

| Surface | Domaine | Auth |
|---|---|---|
| API ingestion device | `api.skald.phracktale.com` | Bearer token statique (`SKALD_API_TOKEN`) |
| UI web humaine | `skald.phracktale.com` | Session après magic-link email |

Le `BearerTokenSubscriber` protège `^/api`. L'UI vit hors `/api` (ex. `/`,
`/login`, `/recordings`, `/audio/{id}`) et est protégée par le firewall
Symfony (session). Les deux mécanismes restent indépendants.

## 4. Modèle de données

- **`User`** : `id`, `email` (unique). Pas de mot de passe.
- **`LoginToken`** : `id`, `user`, `tokenHash` (hash du token, jamais en
  clair en base), `expiresAt` (courte durée, ex. 15 min), `usedAt` (usage
  unique). Le token clair n'existe que dans l'email.

Liste blanche d'emails autorisés via paramètre d'env `SKALD_ALLOWED_EMAILS`
(le tien). Un email hors liste → pas d'envoi, message neutre (pas d'oracle
d'existence de compte).

## 5. Flux magic-link

1. `GET /login` : formulaire email (accessible, label explicite).
2. `POST /login` : si email ∈ liste blanche → créer `LoginToken` (token
   aléatoire fort, stocké **haché**), envoyer l'email avec
   `https://skald.phracktale.com/login/check?token=…`. Réponse **toujours
   neutre** (« si cet email est autorisé, un lien a été envoyé »).
3. `GET /login/check?token=…` : token valide, non expiré, non utilisé →
   marquer `usedAt`, ouvrir la session, rediriger vers la liste. Sinon page
   d'erreur claire.
4. Session Symfony classique ensuite ; `GET /logout`.

Token : `random_bytes(32)` → URL-safe ; comparaison en temps constant ;
stockage `hash('sha256', token)`.

## 6. Lecture audio

- `GET /` ou `/recordings` (protégé session) : liste Twig, triée
  `uploadedAt` desc, horodatage formaté Europe/Paris, un `<audio controls
  preload="none">` par ligne pointant `/audio/{id}`.
- `GET /audio/{id}` (protégé session) : renvoie le fichier depuis
  `var/uploads` (`BinaryFileResponse`, `Content-Type` du `Recording`,
  `Accept-Ranges` pour le seek). 404 si absent.

## 7. Accessibilité (RGAA / OPQUAST)

- HTML sémantique : `main`, `nav`, `table` ou liste de définitions avec
  en-têtes ; titres hiérarchisés.
- Lecteur `<audio>` natif (contrôles clavier natifs) ; chaque lecteur a un
  libellé (`aria-label` incluant l'horodatage).
- Formulaire login : `label` lié, messages d'erreur explicites, focus géré.
- Contrastes conformes, pas d'information par la couleur seule, langue `fr`.

## 8. Domaine & déploiement (Homelab)

- **Nouveau domaine `skald.phracktale.com`** : DNS A → `82.66.11.72`
  (**à créer par le mainteneur**) ; vhost Heimdall + certbot (même procédure
  que `api.skald`), proxy vers le **même conteneur** `homelab_skald_app`
  (port 18090). Un seul backend sert les deux domaines (Symfony route selon
  l'hôte / firewall selon le path).
- **Email** : Symfony Mailer via le **relais Postfix de Heimdall**
  (`MAILER_DSN=smtp://192.168.1.195:25` depuis Thor — relais LAN, pas de
  credentials OVH dans Skald). **À confirmer** : joignabilité Thor→Heimdall:25
  et expéditeur autorisé (`skald@phracktale.com`).
- Nouvelles variables `.env` (générées/posées par bootstrap, jamais
  commitées) : `SKALD_ALLOWED_EMAILS`, `MAILER_DSN`,
  `MAILER_FROM=skald@phracktale.com`, `APP_BASE_URL=https://skald.phracktale.com`.

## 9. Plan d'implémentation (sans test)

1. Entités `User`, `LoginToken` + migration.
2. Auth maison : controllers `/login`, `/login/check`, `/logout` ;
   `security.yaml` (firewall session sur le non-`/api`, `access_control`).
3. Mailer : service d'envoi du lien (template email texte + html sobre).
4. UI Twig : liste + lecteur, route `/audio/{id}` protégée.
5. Artefacts Homelab : vhost `skald.phracktale.com.conf`, maj
   `port-routing-table.md`, bootstrap (nouvelles vars + MAILER).
6. Vérif manuelle : login email → lien → liste → lecture d'un sample.

## 10. Inconnues à lever

- DNS `skald.phracktale.com` (mainteneur).
- DSN SMTP réel / relais Heimdall joignable depuis Thor (à tester).
- Expéditeur `skald@phracktale.com` accepté par le relais OVH.

## 11. Sources

- `_DOCS/contexte.md` §5 (stack Symfony), `SECURITY.md`.
- `HOMELAB/.../secrets-management.md` (relais SMTP Postfix Heimdall, OVH).
- RGAA 4.1 / OPQUAST (exigences front du projet).
