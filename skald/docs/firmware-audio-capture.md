# Spec — Firmware capture + envoi audio (Skald v0.1)

> Doc avant code (CLAUDE.md §2.2). Pas de TDD (décision mainteneur).
>
> **Approche 3 retenue** (pas de microSD — non disponible) : capture micro
> PDM → buffer **PSRAM** → **POST HTTP(S) multipart** vers le backend
> `POST /api/recordings`, avec `Authorization: Bearer`.
> Clip **30 s, 16 kHz / 16-bit mono** (~960 ko).
>
> **Test E2E différé** : le backend n'est pas déployé (Thor bloqué : DNS +
> merge auth, côté mainteneur). Seule la **compilation** est vérifiable ici.

## 1. Objectif

Premier firmware fonctionnel Skald sans stockage local : enregistrer un clip
audio et l'envoyer directement au backend d'ingestion par Wi-Fi.

## 2. Périmètre

### Inclus
- Init micro PDM (I2S RX), 16 kHz / 16-bit / mono.
- Buffer **PSRAM** d'un clip de durée fixe (`RECORD_SECONDS`, défaut **30**).
- Construction d'un WAV valide (en-tête RIFF 44 o + données).
- Connexion Wi-Fi (logique réutilisée du hello world).
- `POST /api/recordings` en `multipart/form-data`, champ `file`, en-tête
  `Authorization: Bearer <SKALD_API_TOKEN>`, corps streamé depuis la PSRAM
  (pas de seconde copie).
- Config réseau/API dans `secrets.h` (gitignoré) : `WIFI_SSID`,
  `WIFI_PASSWORD`, `SKALD_API_URL`, `SKALD_API_TOKEN`.

### Exclus (briques séparées / ultérieures)
- microSD, chiffrement local.
- Contrôle touch/LED REC, off-the-record → `feat-rec-control`.
- File d'attente / retry / capture continue / segmentation.
- Déclenchement BLE → `feat-ble-trigger`.

## 3. Critères d'acceptation

| # | Critère | Quand |
|---|---|---|
| B1 | Compile via `pio run` sans erreur | **maintenant** |
| B2 | Flash USB-C OK | différé (matériel) |
| B3 | Micro PDM init, sinon erreur série claire | différé |
| B4 | Buffer PSRAM 30 s alloué (sinon erreur claire) | différé |
| B5 | Wi-Fi connecté (sinon log + abandon propre) | différé |
| B6 | `POST` → **201** ; l'enregistrement apparaît via `GET /api/recordings` | différé (Thor déployé) |
| B7 | WAV récupéré côté backend lisible / voix audible | différé |

Taille données ≈ 16000 × 2 × 30 ≈ **960 ko** + 44 o d'en-tête.

## 4. Conception

`main.cpp` du hello world (bring-up Phase 0) est remplacé par la logique
capture+envoi (historique git conservé). Découpage lisible :

- `fillWavHeader(buf, dataBytes)` : écrit l'en-tête RIFF/WAVE 44 o en tête
  du buffer (logique pure).
- `connectWifi()` : reprise de la logique hello world (timeout, log).
- `captureToPsram(buf, dataBytes)` : lit le micro PDM jusqu'à remplir.
- `postRecording(buf, totalBytes)` : ouvre TLS, écrit l'en-tête HTTP +
  préambule multipart, streame le buffer, écrit l'épilogue ; lit le code.
- `setup()` : Serial → alloc PSRAM → capture → Wi-Fi → POST → log récap.
- `loop()` : vide (clip unique au boot).

`Content-Length` est calculé d'avance (préambule + WAV + épilogue) : le corps
est streamé depuis la PSRAM, **sans dupliquer** les ~960 ko.

## 5. Risques techniques (à lever au build / au matériel)

1. ~~arduino-esp32 ≥ 3.0 requis pour `ESP_I2S`.~~ **Résolu** : on utilise
   l'API I2S **héritée** (`driver/i2s.h`, PDM RX), compatible avec la core
   arduino-esp32 par défaut de PlatformIO — plus de dépendance pioarduino.
   **B1 vert** : compilation OK (RAM 13,8 % / Flash 24,9 %).
2. **Brochage Sense.** GPIO PDM CLK/DATA fixés matériellement par Seeed —
   constantes isolées, valeurs de l'exemple officiel Seeed, **à reconfirmer
   sur le wiki** avant flash (même rigueur que la LED du hello world).
3. **TLS ESP32.** Le backend public passe par Heimdall (HTTPS Let's Encrypt).
   v0.1 : `WiFiClientSecure::setInsecure()` (pas de validation de chaîne) —
   **dette explicite** à durcir (CA bundle) ultérieurement.
4. **PSRAM.** Allocation via `ps_malloc` / `heap_caps_malloc(MALLOC_CAP_SPIRAM)` ;
   échec → erreur série claire, pas de capture.

## 6. Plan d'implémentation (sans test)

1. `platformio.ini` : plateforme arduino-esp32 ≥ 3.0 (pioarduino).
2. `secrets.example.h` : + `SKALD_API_URL`, `SKALD_API_TOKEN`.
3. `main.cpp` : capture PSRAM + POST multipart streamé.
4. `pio run` (B1). B2–B7 différés (matériel + backend déployé) — vérif par
   le mainteneur quand Thor sera en ligne.

## 7. Sources

- Wiki Seeed XIAO ESP32-S3 Sense (micro PDM) :
  <https://wiki.seeedstudio.com/xiao_esp32s3_getting_started/>
- Arduino Core ESP32 (`ESP_I2S`, `WiFiClientSecure`) :
  <https://docs.espressif.com/projects/arduino-esp32/>
- Plateforme pioarduino (arduino-esp32 3.x sous PlatformIO) :
  <https://github.com/pioarduino/platform-espressif32>

> GPIO PDM exacts et version de core reconfirmés sur le wiki Seeed / doc
> Arduino au moment du build (B1–B3), pas d'affirmation définitive avant.
