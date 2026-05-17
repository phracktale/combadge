# Spec — Firmware hello world

> Première brique firmware de Skald (roadmap Phase 0). Documentation écrite
> avant le code (CLAUDE.md §2.2). Implémentée en TDD strict (étape 6).

## 1. Objectif

Valider la chaîne de développement embarquée de bout en bout sur le
Seeed XIAO ESP32-S3 Sense :

1. **Blink** : faire clignoter une LED à période fixe.
2. **Serial** : émettre un message lisible sur la console série USB.
3. **Wi-Fi** : se connecter à un point d'accès et logguer l'IP obtenue.

Ce n'est pas une fonctionnalité utilisateur : c'est la preuve que toolchain,
flash, logs et radio fonctionnent. Aucune capture audio ici.

## 2. Périmètre

### Inclus

- Toolchain PlatformIO + Arduino Core ESP32 opérationnelle.
- Clignotement LED à 1 Hz (500 ms allumée / 500 ms éteinte).
- Log série à 115200 bauds : bannière de démarrage, état Wi-Fi, IP.
- Connexion Wi-Fi station avec retry et log d'échec explicite.
- **Scan diagnostic** : liste des réseaux 2,4 GHz visibles (SSID, RSSI,
  canal, chiffrement) + code d'état Wi-Fi numérique en cas d'échec.
  Rappel : l'ESP32-S3 ne voit que le 2,4 GHz.

### Exclus (hors périmètre)

- Capture audio PDM, microSD, BLE, deep sleep, OTA.
- Provisioning Wi-Fi évolué (WiFiManager, BLE) — ici credentials en dur,
  **non commités** (voir §5).
- LED REC définitive soudée — voir décision ouverte §6.

## 3. Critères d'acceptation

| # | Critère | Vérification |
|---|---|---|
| A1 | Le firmware compile via `pio run` sans erreur | sortie PlatformIO |
| A2 | Le flash USB-C réussit (`pio run -t upload`) | sortie PlatformIO |
| A3 | La LED clignote à 1 Hz ±10 % | observation visuelle |
| A4 | La console série affiche la bannière et la version | `pio device monitor` |
| A5 | Le device se connecte au Wi-Fi et logge une IP valide | log série |
| A6 | En cas d'échec Wi-Fi, un message d'erreur clair est loggé, sans blocage du blink | test credentials erronés |

## 4. Conception

> Décision mainteneur (2026-05-17) : pas de TDD ni de tests unitaires sur ce
> projet, tous stacks confondus. Vérification par les critères manuels
> A1–A6. Cette section ne décrit donc qu'une séparation **pour la lisibilité**
> (CLAUDE.md §5, code support pédagogique), pas pour la testabilité.

Le code reste découpé en fonctions courtes et nommées :

- **`blinkState(now)`** : à partir du temps courant (ms), renvoie l'état
  attendu de la LED. Logique pure, sans accès GPIO.
- **`wifiConfigValid()`** : vérifie que SSID et mot de passe ne sont pas vides
  avant d'appeler la radio.

Le câblage matériel (toggle GPIO, `WiFi.begin`) reste une couche fine
appelant ces fonctions.

## 5. Gestion des secrets Wi-Fi

Phase test = credentials en dur **jamais commités** (`contexte.md` §5).

- Fichier `skald/firmware/include/secrets.h` (SSID/mot de passe), **exclu**
  via `.gitignore`.
- Fichier d'exemple `skald/firmware/include/secrets.example.h` commité, sans
  valeur réelle.
- Ajout `.gitignore` requis à l'étape 6 :
  `skald/firmware/include/secrets.h`.

## 6. Décision LED (tranchée)

**Retenu : option 1** — LED utilisateur intégrée de la carte. Selon le wiki
Seeed XIAO ESP32-S3, elle est sur **GPIO21**, **active à l'état bas**. Zéro
soudure. Le numéro de GPIO est isolé dans une constante unique
(`LED_PIN`) pour bascule triviale vers la LED REC dédiée plus tard.

L'option 2 (LED REC dédiée soudée dès maintenant) est écartée pour ne pas
bloquer le hello world sur de la soudure.

## 7. Plan d'implémentation (étape 6, sans test)

1. Scaffolding PlatformIO (`platformio.ini` ciblant le XIAO ESP32-S3).
2. `secrets.example.h` commité, `secrets.h` réel exclu du dépôt.
3. `main.cpp` : `blinkState`, `wifiConfigValid`, `scanNetworks`,
   `wifiStatusText`, `setup`/`loop` câblant GPIO21 et le Wi-Fi.
4. Vérification manuelle des critères A1–A6 sur carte réelle.

### Diagnostic Wi-Fi

Au démarrage, avant la tentative de connexion, le firmware scanne et logue
les réseaux 2,4 GHz visibles (l'ESP32-S3 est mono-bande 2,4 GHz). En cas
d'échec, il logue le code `WiFi.status()` traduit. Interprétation :

- SSID cible **absent** du scan → face 2,4 GHz non joignable (band steering,
  2,4 GHz désactivé sur la box, ou hors de portée).
- SSID cible **présent** mais code « échec auth » → mot de passe / WPA.

## 8. Sources

- Wiki Seeed XIAO ESP32-S3 :
  <https://wiki.seeedstudio.com/xiao_esp32s3_getting_started/>
- Documentation PlatformIO : <https://docs.platformio.org/>
- Arduino Core ESP32 :
  <https://docs.espressif.com/projects/arduino-esp32/>

> Le numéro exact du GPIO de la LED intégrée et sa polarité seront reconfirmés
> sur le wiki Seeed au moment du câblage (étape 6), avant toute affirmation
> définitive dans le code.
