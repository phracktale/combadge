# Spec — Suppression d'enregistrements (unitaire + lot)

> Doc avant code (CLAUDE.md §2.2). Pas de TDD. Front → OPQUAST / RGAA.
> Décisions validées : **formulaire serveur** (POST + CSRF, sans JS de
> framework), **confirmation obligatoire** (action irréversible).

## 1. Objectif

Depuis l'UI privée `/app`, supprimer un enregistrement ou plusieurs
sélectionnés. Supprimer = **entité Doctrine + fichier** `var/uploads`.
Action **irréversible** → étape de confirmation obligatoire.

## 2. Périmètre

- Sélection multiple (cases à cocher) + suppression par lot.
- Suppression unitaire (par ligne).
- Page de **confirmation** listant précisément ce qui sera supprimé.
- Protégé session `ROLE_USER` (sous `/app`). Hors API device (Bearer).

Exclus : corbeille/restauration, suppression via API Platform (la ressource
reste lecture seule pour le device).

## 3. Routes

| Route | Méthode | Rôle |
|---|---|---|
| `app_recordings` `/app` | GET | Liste + cases à cocher + boutons |
| `app_recordings_delete` `/app/recordings/delete` | POST | Reçoit `ids[]`, affiche la page de confirmation |
| `app_recordings_delete_confirm` `/app/recordings/delete/confirm` | POST | Vérifie CSRF, supprime, redirige vers la liste |

Deux POST : la liste poste la sélection → page de confirmation (réaffiche les
`ids[]` + jeton CSRF) → confirmation poste → suppression.

## 4. Sécurité

- `^/app` déjà `ROLE_USER` (security.yaml). Méthodes POST uniquement.
- **CSRF** : jeton `delete_recordings` dans le formulaire de confirmation,
  vérifié serveur (`isCsrfTokenValid`).
- IDs validés : on ne supprime que des `Recording` existants ; un id
  inconnu est ignoré silencieusement (pas d'erreur 500).

## 5. Suppression effective

Pour chaque id : charger le `Recording`, supprimer le fichier
`%kernel.project_dir%/<storagePath>` s'il existe (échec d'unlink loggué, non
bloquant), `em->remove()`, puis un seul `flush()`. Compte rendu en flash
(« N enregistrement(s) supprimé(s) »).

## 6. UI / accessibilité (RGAA, OPQUAST)

- Liste : `<form>` englobant, une case `name="ids[]" value="{id}"` par ligne
  avec `<label>` lié (libellé = horodatage), bouton « Supprimer la
  sélection ». Bouton de suppression unitaire par ligne (form dédié, 1 id).
- « Tout sélectionner » : case maîtresse + petit script vanilla
  (amélioration progressive ; sans JS on coche manuellement).
- Page de confirmation : liste explicite (nom, date) des éléments,
  `ids[]` en champs cachés + jeton CSRF, boutons « Confirmer la
  suppression » et « Annuler » (retour liste). Focus sur le titre.
- Messages d'état via la zone flash (déjà accessible, `role="status"`).
- Aucune information par la couleur seule ; intitulés explicites.

## 7. Plan d'implémentation (sans test)

1. `RecordingViewController` : `deletePrepare()` (POST → confirmation),
   `deleteConfirm()` (POST + CSRF → suppression → redirect).
2. Templates : maj `recording/list.html.twig` (cases + boutons),
   `recording/delete_confirm.html.twig`.
3. Vérif locale E2E déterministe (login seedé → upload/seed d'un
   Recording → suppression unitaire et lot → contrôle entité + fichier).

## 8. Sources

- `_DOCS/contexte.md` §5 (lisibilité, RGAA/OPQUAST front).
- Symfony : CSRF (`csrf_token`/`isCsrfTokenValid`), `BinaryFileResponse`.
