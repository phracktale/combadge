# Architecture cible

Ce document formalise l'architecture arbitrée dans les notes de projet. Toute
décision structurante ultérieure doit être reportée ici.

## Vue d'ensemble

```
[Device portable]              [Phone]              [Backend Symfony]
  XIAO ESP32-S3                 Flutter              + Pipeline Python
  - Mic PDM                     - BLE provisioning   - Ingestion audio
  - Touch capacitif             - WiFi config        - Whisper / pyannote
  - LED REC                     - Sync trigger       - Traduction NLLB
  - BLE 5.0                     - Browse archive     - Résumé LLM
  - Wi-Fi 2.4 GHz                                    - PostgreSQL+pgvector
  - microSD                                          - EasyAdmin
        │                            │                       │
        ├──── BLE 5.0 ───────────────┤                       │
        │                            │                       │
        └──── Wi-Fi sync ────────────┴──── HTTPS REST ───────┤
                                                              │
                                                       [Fournisseur IA]
                                                       Ollama (local)
                                                       Claude / Mistral / GPT
```

## Composants

| Composant | Rôle | Stack |
|---|---|---|
| `skald/firmware/` | Code embarqué du device | C++ / PlatformIO / Arduino Core ESP32 |
| `skald/mobile/` | App compagnon (provisioning, sync) | Flutter |
| `skald/backend/` | API REST, archive, admin | Symfony 7 / PHP 8.2 / EasyAdmin 4 / API Platform 4 |
| `skald/pipeline/` | Workers de traitement audio | Python 3.12 / FastAPI / Celery |
| `skald/hardware/` | Schémas PCB, STL, BOM | KiCad / Fusion 360 |
| `shared/` | Briques réutilisables entre projets | mixte |

## Décisions techniques structurantes

### Matériel

- **Carte principale** : Seeed XIAO ESP32-S3 Sense (ESP32-S3R8, 8 Mo PSRAM,
  8 Mo Flash, BLE 5.0 + Wi-Fi 2,4 GHz, micro PDM, microSD, USB-C).
- **LED REC** : SMD 0805 + résistance 470 Ω sur GPIO dédié (option B),
  rouge, pour conformité éthique.
- **Touch** : capacitif natif ESP32 (réveil deep sleep supporté).
- **Batterie** : powerbank USB-C en prototype, LiPo 502025 200 mAh avec BMS
  en phase wearable.

### Firmware

- **Toolchain** : PlatformIO sur VS Code.
- **Framework** : Arduino Core ESP32 d'abord ; bascule ESP-IDF si contrainte.
- **RTOS** : FreeRTOS (BLE + audio + sleep).
- **Mise à jour** : premier flash USB-C, puis OTA Wi-Fi.
- **Provisioning Wi-Fi** : credentials en dur (test) → WiFiManager (prod) →
  provisioning BLE depuis l'app (final).

### Backend

- Symfony 7 / PHP 8.2, EasyAdmin 4, API Platform 4.
- Doctrine ORM / PostgreSQL 16 + pgvector (recherche sémantique).
- Mercure pour le push device → web (phase ultérieure).

### Pipeline IA (Python 3.12)

- **STT** : `faster-whisper` par défaut, Moshi STT (Kyutai) en option FR natif.
- **Diarisation** : `pyannote.audio 3.x`.
- **Identification interlocuteurs** : ECAPA-TDNN / Resemblyzer, après
  enrôlement consenti.
- **Traduction** : NLLB-200 ou LLM contextualisé.
- **Résumé** : LLM via interface `LLMProvider` abstraite.
- **Alignement mot-à-mot** : WhisperX.
- **Embeddings** : `sentence-transformers`.

### Communication device ↔ backend

Stratégie incrémentale par complexité croissante :

| Approche | Latence | Bidirectionnel | Phase |
|---|---|---|---|
| HTTP REST | secondes | non (pull) | **v0.1 (retenu)** |
| Mercure | <1 s | oui (push HTTP/2) | v0.2+ |
| MQTT | ~50 ms | oui | si temps réel requis |
| WebSocket | <100 ms | oui | alternative |

## Conventions inter-projets

Tout nouveau projet (N°02, N°03…) :

1. fait l'objet d'un **RFC** dans `docs/rfc/NN-nom.md` avant d'obtenir un
   dossier (voir [rfc-template.md](rfc-template.md)) ;
2. réutilise au maximum les briques de `shared/` ;
3. suit la même structure interne
   (`firmware/`, `mobile/`, `backend/`, `pipeline/`, `hardware/`, `docs/`) ;
4. a son propre README avec BOM, démarrage rapide et roadmap.
