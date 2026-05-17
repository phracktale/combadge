# skald/firmware

Code embarqué du device portable (Seeed XIAO ESP32-S3 Sense) : capture audio
PDM, touch capacitif, LED REC, BLE, sync Wi-Fi, deep sleep.

Stack : C++ / PlatformIO / Arduino Core ESP32 / FreeRTOS. Tests : Unity.

Réutilise `shared/firmware-lib/`. Première brique prévue : hello world
(blink LED REC + Serial + Wi-Fi) — voir `skald/docs/`.

> Dossier en attente d'implémentation. Voir `docs/architecture.md`.
