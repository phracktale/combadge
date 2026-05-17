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

## 4. Conception orientée testabilité

Le matériel n'est pas testable en CI. On isole la **logique pure** dans des
unités testables hors carte, le `main` ne faisant que câbler GPIO/Wi-Fi
réels dessus.

Unités pures à tester avec Unity (`pio test`) :

- **`BlinkScheduler`** : à partir d'un temps courant (ms), décide l'état
  attendu de la LED (booléen). Pas d'accès GPIO. Testable : période, bornes,
  débordement de `millis()`.
- **`WifiConfig`** : valide/parse SSID et mot de passe (longueurs, non vide,
  caractères). Pas d'accès radio. Testable : entrées valides/invalides.

Le matériel (toggle GPIO, `WiFi.begin`) est une couche fine non testée
unitairement, vérifiée par les critères manuels A2–A6.

## 5. Gestion des secrets Wi-Fi

Phase test = credentials en dur **jamais commités** (`contexte.md` §5).

- Fichier `skald/firmware/include/secrets.h` (SSID/mot de passe), **exclu**
  via `.gitignore`.
- Fichier d'exemple `skald/firmware/include/secrets.example.h` commité, sans
  valeur réelle.
- Ajout `.gitignore` requis à l'étape 6 :
  `skald/firmware/include/secrets.h`.

## 6. Décision ouverte — quelle LED ?

`contexte.md` §4 retient l'option B (LED rouge dédiée « REC » + résistance
470 Ω sur un GPIO), mais cette LED n'est pas encore soudée en phase prototype.

- **Option 1 (prototype)** : utiliser la LED utilisateur intégrée de la carte
  pour le hello world. Selon le wiki Seeed XIAO ESP32-S3, elle est sur
  **GPIO21**, **active à l'état bas**. Zéro soudure.
- **Option 2 (cible)** : câbler dès maintenant la LED REC dédiée sur un GPIO
  libre et l'utiliser. Conforme à la cible, mais nécessite le composant et la
  soudure avant de pouvoir flasher utilement.

Recommandation : **option 1** pour débloquer le hello world, en isolant le
numéro de GPIO dans une constante unique pour bascule triviale vers la LED REC
ensuite. À arbitrer avant l'étape 6.

## 7. Plan de test (étape 6, TDD)

1. **Red** : test `BlinkScheduler` (état attendu selon le temps) → échoue.
2. **Green** : implémenter `BlinkScheduler` minimal.
3. **Red** : test `WifiConfig` (validation SSID/mot de passe) → échoue.
4. **Green** : implémenter `WifiConfig`.
5. **Refactor** : nettoyage sans casser les tests.
6. Câblage matériel dans `main` (LED réelle, Wi-Fi réel), vérif manuelle
   A2–A6.

Commande de rejeu des tests unitaires : `pio test -e native`.

## 8. Sources

- Wiki Seeed XIAO ESP32-S3 :
  <https://wiki.seeedstudio.com/xiao_esp32s3_getting_started/>
- Documentation PlatformIO : <https://docs.platformio.org/>
- Arduino Core ESP32 :
  <https://docs.espressif.com/projects/arduino-esp32/>

> Le numéro exact du GPIO de la LED intégrée et sa polarité seront reconfirmés
> sur le wiki Seeed au moment du câblage (étape 6), avant toute affirmation
> définitive dans le code.
