# Spec — Firmware déclenché à la voix (VAD) + LED REC

> Doc avant code (CLAUDE.md §2.2). Pas de TDD. Vérif : compilation (B1) ;
> flash + terrain (B2+) par le mainteneur.
>
> Décisions validées : VAD **seuil d'énergie + hangover + pré-roll** ;
> **LED orange intégrée (GPIO21)** clignote en enregistrement, éteinte sinon.

## 1. Objectif

Le device écoute en continu. Dès que le niveau sonore dépasse un seuil
(bruit/parole), il enregistre ; après un silence prolongé, il arrête, puis
**POST** le segment à `/api/recordings`. Pendant l'enregistrement, la **LED
orange clignote** ; hors enregistrement, **elle est éteinte**.

## 2. Conformité (sourcé `_DOCS/contexte.md` §7)

`contexte.md` §7 prévoit une **LED rouge dédiée « REC » non débrayable**
(RGPD / Code pénal art. 226-1). Ici on utilise la **LED orange intégrée**
(GPIO21, déjà câblée) : **dette de conformité explicite en v0.1**. GPIO isolé
dans une constante pour bascule vers la LED rouge dédiée ultérieurement.

## 3. Algorithme VAD (énergie + hangover + pré-roll)

- Lecture I2S PDM par **trames** (ex. 20 ms = 320 échantillons à 16 kHz).
- Énergie trame = RMS des échantillons 16 bits.
- **Démarrage** : `START_FRAMES` trames consécutives au-dessus de
  `RMS_THRESHOLD` (anti-déclenchement sur clic bref).
- **Pré-roll** : buffer circulaire de `PREROLL_MS` (~500 ms) conservé en
  continu ; au démarrage il est préfixé au segment (on ne coupe pas le début
  de parole).
- **Arrêt** : après `SILENCE_MS` (~1500 ms) consécutifs sous le seuil →
  finalisation du segment.
- **Bornes** : segment plafonné à `MAX_SEGMENT_S` (PSRAM bornée) ; segment
  minimal `MIN_SEGMENT_MS` sinon ignoré (faux positif).
- Seuils = constantes ajustables (calibrage terrain ; valeurs initiales
  prudentes, à affiner — noté comme inconnue).

## 4. Machine à états

```
IDLE  ──(START_FRAMES > seuil)──►  RECORDING
RECORDING ──(SILENCE_MS sous seuil OU MAX_SEGMENT_S)──► FINALIZE
FINALIZE  ──(WAV = preroll+segment, POST /api/recordings)──► IDLE
```

LED : `IDLE` → éteinte ; `RECORDING` → clignote (toggle toutes `BLINK_MS`) ;
`FINALIZE` (envoi) → fixe allumée (facultatif) puis éteinte.

## 5. Mémoire (PSRAM)

- Ring buffer pré-roll : `PREROLL_MS` de PCM 16 kHz/16-bit.
- Buffer segment : `MAX_SEGMENT_S` × 16000 × 2 octets + en-tête WAV 44 o.
- Allocation `ps_malloc` une fois ; échec → erreur série, pas de capture.
- Envoi : WAV streamé depuis la PSRAM (pas de copie), comme l'existant.

## 6. Conception (réutilise l'existant)

`main.cpp` (capture+envoi) est retravaillé :
- `fillWavHeader`, `connectWifi`, `postRecording`, `parseUrl` : **réutilisés**.
- `frameRms(buf,n)` : énergie d'une trame (logique pure).
- `setLed(state)` : applique l'état LED (active à l'état bas).
- `loop()` : machine à états VAD (lecture trame, RMS, transitions, LED).
- `setup()` : Serial, alloc PSRAM, init I2S PDM, Wi-Fi.

## 7. Critères d'acceptation

| # | Critère | Quand |
|---|---|---|
| V1 | Compile `pio run` sans erreur | maintenant |
| V2 | Flash OK | matériel (Thierry) |
| V3 | LED orange éteinte au repos, clignote en enregistrement | matériel |
| V4 | Parole → démarrage ; silence prolongé → arrêt | matériel/terrain |
| V5 | Segment POSTé (201), visible via GET /api/recordings | matériel |
| V6 | Début de parole non coupé (pré-roll) | terrain |

## 8. Inconnues / risques

- **Seuils** (`RMS_THRESHOLD`, `SILENCE_MS`, `START_FRAMES`) : dépendants du
  micro/ambiance, à calibrer sur le terrain. Valeurs initiales = point de
  départ, pas une garantie.
- Brochage PDM (GPIO 42/41) : exemple Seeed, à reconfirmer au flash.
- Connexion Wi-Fi maintenue pour les POST successifs (reconnexion si perte).
- Latence POST pendant qu'un nouveau son arrive : v0.1 = envoi bloquant
  entre deux écoutes (acceptable ; file/asynchrone = amélioration future).

## 9. Sources

- `_DOCS/contexte.md` §4 (LED), §7 (éthique/RGPD), §9 (roadmap VAD).
- Wiki Seeed XIAO ESP32-S3 Sense ; Arduino Core ESP32 (`driver/i2s.h`).
