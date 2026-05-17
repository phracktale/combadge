# Combadge

> Plateforme open source de devices portables IA, sans abonnement, sans cloud propriétaire.
> *Bring Your Own AI.*

[![License: MIT](https://img.shields.io/badge/License-MIT-blue.svg)](LICENSE)
[![Projects](https://img.shields.io/badge/projects-1%20active%20%2F%20%E2%88%9E%20planned-orange.svg)](#projets)
[![Status](https://img.shields.io/badge/status-early%20stage-yellow.svg)](#feuille-de-route)

---

## Pourquoi Combadge

Les wearables IA grand public (Plaud Note, Limitless Pendant, Omi, Meta Ray-Ban) partagent un même défaut : ils enferment l'utilisateur dans un abonnement, un modèle imposé, un cloud étranger. Vous achetez un objet, vous payez ensuite pour exploiter vos propres données, sur des serveurs qui ne sont pas les vôtres.

**Combadge** propose une autre voie : une **plateforme de devices portables** où chaque projet partage les mêmes principes :

- Matériel basé sur des composants standards et documentés (ESP32-S3, nRF52, etc.)
- Firmware libre, sobre, lisible
- Pipeline de traitement local ou hybride
- **Aucun fournisseur d'IA imposé** : Ollama, Claude, Mistral, GPT, Gemini — vous choisissez
- Aucune télémétrie, aucun abonnement
- Conçu en conformité RGPD et avec une exigence forte d'éthique de captation

Le nom **combadge** est un clin d'œil au communicateur en forme d'insigne de *Star Trek : The Next Generation* — un objet porté, discret, activé au toucher, qui sert la mémoire et l'échange. La filiation est esthétique et symbolique ; le projet n'a aucun lien avec Paramount ou CBS.

---

## Concept

Combadge n'est pas un produit unique mais un **écosystème de projets indépendants** qui partagent :

- Une **philosophie commune** (souveraineté, simplicité, frugalité)
- Un **socle technique commun** (XIAO ESP32-S3, ESP-IDF, Symfony, Python, Flutter)
- Une **architecture commune** (device portable + app mobile + backend + pipeline IA)
- Des **briques réutilisables** (provisioning Wi-Fi, sync BLE, machine à états, etc.)

Chaque projet est un **dossier de premier niveau** dans ce dépôt, avec son propre README, sa propre BOM matérielle, son propre cycle de vie. Vous pouvez utiliser un projet isolément sans avoir à comprendre toute la plateforme.

---

## Projets

| N° | Nom | Description | Statut |
|---|---|---|---|
| 01 | [**skald**](skald/) | Enregistreur audio portable avec transcription, traduction et résumé via l'IA de votre choix | 🟡 Conception |
| 02 | *à venir* | Emplacement réservé | ⚪ Idée |
| 03 | *à venir* | Emplacement réservé | ⚪ Idée |

**Légende statut :** 🟢 Stable · 🟡 En développement · 🟠 Prototype · ⚪ Idée

### Skald (Projet N°01)

Nommé d'après les poètes norrois transmetteurs de mémoire orale, **Skald** est un enregistreur audio portable au format insigne, équivalent open source de Plaud Note ou Omi. Il capte, synchronise vers un backend Symfony, et délègue le traitement à un pipeline Python (Whisper + pyannote + LLM au choix) qui produit transcription, traduction, résumé, horodatage, géolocalisation et identification consentie des interlocuteurs.

→ Voir [`skald/README.md`](skald/) pour la spécification complète.

### Idées pour les projets suivants

Sans engagement de calendrier ni de réalisation, voici quelques pistes envisagées pour étendre la plateforme combadge :

- Traducteur quasi temps réel (Moshi STT + Piper TTS, retour dans une oreillette BLE Audio)
- Variante avec micro-caméra pour augmentation contextuelle (OCR, identification d'objets)
- Compteur d'environnement sonore et qualité d'air pour usage formation / scénographie
- Beacon de présence et balise géolocalisée
- Carnet d'idées vocales avec déclenchement par geste plutôt que par tap

Toute proposition de nouveau projet doit faire l'objet d'un **RFC** (voir [`docs/rfc-template.md`](docs/rfc-template.md)) avant d'obtenir un numéro et un dossier dédié.

---

## Structure du dépôt

```
combadge/
├── README.md                  ← ce fichier
├── LICENSE                    ← MIT
├── CODE_OF_CONDUCT.md
├── CONTRIBUTING.md
├── SECURITY.md
│
├── shared/                    ← briques techniques réutilisables
│   ├── firmware-lib/          ← bibliothèques C++ communes (touch, LED, BLE, OTA…)
│   ├── backend-bundle/        ← bundle Symfony partagé
│   ├── pipeline-core/         ← interface LLMProvider, abstraction STT, etc.
│   └── hardware-templates/    ← empreintes KiCad, modèles 3D paramétriques
│
├── skald/                     ← Projet N°01
│   ├── README.md
│   ├── firmware/
│   ├── mobile/
│   ├── backend/
│   ├── pipeline/
│   ├── hardware/
│   └── docs/
│
└── docs/                      ← documentation globale
    ├── philosophy.md
    ├── architecture.md
    ├── rfc-template.md
    └── credits.md
```

Cette structure permet d'ajouter un projet N°02 simplement en créant un nouveau dossier au même niveau que `skald/`, qui peut puiser dans `shared/` sans dupliquer le code.

---

## Socle technique commun

Tous les projets de combadge convergent vers un même socle, choisi pour sa lisibilité et sa pérennité :

| Couche | Technologie | Pourquoi |
|---|---|---|
| Microcontrôleur principal | XIAO ESP32-S3 (et variantes) | Compact, BLE 5.0 + Wi-Fi, écosystème ouvert |
| Firmware | C++ / PlatformIO / Arduino Core ESP32 | Productif, large communauté |
| App mobile | Flutter | Cross-platform, BLE mature |
| Backend | Symfony 7 / PHP 8.2 / EasyAdmin 4 | Robuste, lisible, écosystème français |
| Pipeline IA | Python 3.12 / FastAPI / Celery | Standard de fait pour le ML appliqué |
| Stockage | PostgreSQL 16 + pgvector | Relationnel + recherche sémantique |
| LLM | Provider-agnostic (Ollama, Claude, Mistral, GPT, Gemini) | Liberté de choix |

Le détail de chaque couche, les versions exactes et les justifications sont dans [`docs/architecture.md`](docs/architecture.md).

---

## Feuille de route

Combadge avance par jalons fonctionnels plutôt que par dates fermes — c'est un projet open source mené à temps partiel.

### Phase 0 : Fondations (en cours)
- [x] Définir l'identité de la plateforme et la première application
- [x] Choisir le socle technique commun
- [ ] Initialiser le dépôt et les conventions de contribution
- [ ] Premier firmware "hello world" sur XIAO ESP32-S3

### Phase 1 : Skald v0.1 (capture passive minimale)
- [ ] Firmware : capture audio sur microSD, sync BLE
- [ ] Backend Symfony : ingestion fichiers, métadonnées de base
- [ ] Pipeline : transcription Whisper + résumé LLM
- [ ] App mobile minimale : provisioning Wi-Fi et lancement de la sync

### Phase 2 : Skald v0.2
- [ ] Diarisation et identification consentie des interlocuteurs
- [ ] Détection automatique de langue et traduction
- [ ] Recherche full-text et sémantique dans l'archive

### Phase 3 : Projet N°02
- [ ] À définir après la stabilisation de Skald

---

## Éthique et conformité

Combadge capte du son et, dans les versions futures, potentiellement de l'image. Cela engage la responsabilité légale et morale de chaque utilisateur·rice. Tous les projets de la plateforme respectent :

- **LED rouge dédiée signalant l'enregistrement actif**, non débrayable par firmware.
- **Modèles de consentement** (FR / EN) dans [`docs/consent-templates/`](docs/consent-templates/) pour les contextes professionnels.
- **Mode "off-the-record"** déclenché par tap long, suspendant la capture pendant N minutes.
- **Chiffrement local** des fichiers sur la microSD (AES-256 via clé dérivée).
- **Données biométriques** stockées localement uniquement, jamais transmises à un service tiers sans opt-in explicite.

Cadre juridique de référence :
- Code pénal français, art. 226-1 (atteinte à la vie privée par captation non consentie)
- Règlement (UE) 2016/679 (RGPD), articles 6, 7, 9
- Position CNIL sur les empreintes vocales (2019, mise à jour 2023)

**Combadge ne doit pas être utilisé pour enregistrer des personnes sans leur consentement.** Toute pull request introduisant un mode d'enregistrement clandestin sera refusée, sur tous les projets de la plateforme.

---

## Contribuer

Les contributions sont bienvenues sur l'ensemble des projets et des briques partagées. Compétences recherchées :

- **Firmware** : C++, ESP-IDF, FreeRTOS, optimisation énergétique
- **Mobile** : Flutter, BLE, design d'app simple et accessible
- **Backend** : Symfony, API Platform, conception RESTful
- **Pipeline** : Python, ML appliqué, traitement audio
- **Hardware** : KiCad, conception de PCB compactes, intégration mécanique
- **Documentation** : rédaction technique en français et en anglais
- **UX / Accessibilité** : audit OPQUAST, conformité RGAA, design inclusif

Lire [`CONTRIBUTING.md`](CONTRIBUTING.md) avant toute pull request.

Pour proposer un **nouveau projet** dans la plateforme combadge, utiliser le modèle de RFC dans [`docs/rfc-template.md`](docs/rfc-template.md).

Code de conduite : [Contributor Covenant 2.1](CODE_OF_CONDUCT.md).

---

## Licence

L'ensemble du dépôt **combadge** — code, schémas matériels, documentation, modèles 3D — est publié sous **licence MIT**.

```
MIT License

Copyright (c) 2026 Thierry [Nom] et contributeurs

Permission is hereby granted, free of charge, to any person obtaining a copy
of this software and associated documentation files (the "Software"), to deal
in the Software without restriction, including without limitation the rights
to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
copies of the Software, and to permit persons to whom the Software is
furnished to do so, subject to the following conditions:

The above copyright notice and this permission notice shall be included in all
copies or substantial portions of the Software.

THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
SOFTWARE.
```

Le texte complet est dans [`LICENSE`](LICENSE).

---

## Remerciements

Ce projet s'inspire des travaux des communautés autour de **Omi (Based Hardware)**, **Brilliant Labs (Frame, Halo)**, **Seeed Studio**, **Espressif**, **OpenAI Whisper**, **pyannote.audio**, **Mozilla Common Voice**, et de l'écosystème open hardware au sens large.

---

## Contact

- Dépôt : [github.com/phracktale/combadge](https://github.com/phracktale/combadge)
- Discussions : [GitHub Discussions](https://github.com/phracktale/combadge/discussions)
- Bugs : [GitHub Issues](https://github.com/phracktale/combadge/issues)
- Sécurité : voir [`SECURITY.md`](SECURITY.md)

*« La mémoire est ce qui résiste au monde. »* — Patrick Modiano
