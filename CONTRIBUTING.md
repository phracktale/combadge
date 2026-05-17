# Contribuer à Combadge / Skald

Merci de l'intérêt porté au projet. Ce document décrit le mode opératoire
attendu pour toute contribution. Il est volontairement strict : le code doit
pouvoir servir de support pédagogique.

Avant toute contribution, lire le [code de conduite](CODE_OF_CONDUCT.md).

---

## 1. Principes non négociables

- **Documentation avant code.** Une fonctionnalité non documentée n'existe pas.
  La spec dans `docs/` (ou `skald/docs/`) précède l'implémentation.
- **TDD strict.** Cycle Red → Green → Refactor. Pas de code applicatif sans
  test associé.
- **Code lisible avant tout.** Pas d'astuces obscures ni de one-liners
  cryptiques.
- **Français correct** (accents inclus) dans la documentation et les échanges.
- **Sourcer les affirmations factuelles** (au moins deux sources sérieuses).
- **Accessibilité** : conformité OPQUAST et RGAA sur tout ce qui touche au
  front (backend Symfony, app mobile, web UI).
- **Éthique de captation** : aucune pull request introduisant un mode
  d'enregistrement clandestin ne sera acceptée (voir la section Éthique du
  [README](README.md)).

---

## 2. Workflow Git

### Branches

- Branche d'intégration : **`develop`**. La branche **`main`** est protégée :
  aucun commit direct.
- Toute contribution se fait sur une branche dédiée partant de `develop`.
- Convention de nommage : `<type>-<courte-description>` où `<type>` ∈
  { `feat`, `fix`, `docs`, `refactor`, `test`, `chore` }.
  Exemple : `feat-blink-firmware`.

### Cycle d'une contribution

1. Ouvrir (ou commenter) un ticket décrivant le besoin.
2. Créer la branche : `git switch -c feat-ma-fonctionnalite develop`.
3. Documenter la fonctionnalité dans `docs/` ou `skald/docs/`.
4. Écrire les tests (Red), puis l'implémentation minimale (Green), puis
   refactorer.
5. Ouvrir une pull request **vers `develop`** (jamais vers `main`).
6. Le mainteneur revoit et merge lui-même. Aucun merge automatique.

### Messages de commit

Format : `<type>: résumé court à l'impératif`

Exemple : `feat: capture audio PDM sur microSD`

---

## 3. Tests par stack

| Composant | Outil de test |
|---|---|
| `skald/firmware/` | Unity ou GoogleTest (ESP-IDF / PlatformIO) |
| `skald/backend/` | PHPUnit (Symfony) |
| `skald/pipeline/` | pytest (Python 3.12) |
| `skald/mobile/` | flutter_test |

Tests d'intégration et bout-en-bout exigés aux jonctions critiques :
synchronisation BLE, ingestion backend, pipeline complet.

---

## 4. Structure du dépôt

| Dossier | Contenu | Versionné |
|---|---|---|
| `docs/` | Documentation publique (dev + applicative) | ✅ |
| `shared/` | Briques réutilisables entre projets | ✅ |
| `skald/` | Code du projet N°01 | ✅ |
| `skald/<stack>/tests/` | Tests unitaires et intégration | ✅ |

Tout nouveau projet (N°02, N°03…) fait l'objet d'un RFC dans `docs/rfc/`
avant d'obtenir un dossier, et réutilise au maximum les briques de `shared/`.

---

## 5. Licences

En contribuant, vous acceptez que vos contributions soient publiées sous :

- **Code** (firmware, backend, pipeline, mobile) : MIT
- **Schémas matériels** (KiCad, STL) : CERN-OHL-S v2
- **Documentation** : CC BY-SA 4.0

---

## 6. Sécurité

Ne **jamais** signaler une faille de sécurité via un ticket public. Suivre la
procédure décrite dans [SECURITY.md](SECURITY.md).
