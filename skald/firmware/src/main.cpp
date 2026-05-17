// Firmware hello world de Skald.
//
// But : valider la chaîne de développement embarquée (toolchain, flash, logs,
// radio) sur le Seeed XIAO ESP32-S3 Sense. Aucune capture audio ici.
//
// Trois choses :
//   1. clignotement d'une LED à 1 Hz ;
//   2. log série lisible à 115200 bauds ;
//   3. connexion Wi-Fi station + log de l'IP obtenue.
//
// Spec : skald/docs/firmware-hello-world.md
// Pas de test (décision mainteneur 2026-05-17). Vérification manuelle A1–A6.

#include <Arduino.h>
#include <WiFi.h>

#include "secrets.h"  // WIFI_SSID, WIFI_PASSWORD — non commité (voir .gitignore)

// LED utilisateur intégrée du XIAO ESP32-S3 : GPIO21, active à l'état bas
// (allumée quand la broche est LOW). Constante unique pour basculer vers la
// LED REC dédiée plus tard sans toucher au reste du code.
static const uint8_t LED_PIN = 21;

// Période de clignotement : 500 ms allumée / 500 ms éteinte = 1 Hz.
static const unsigned long BLINK_HALF_PERIOD_MS = 500;

// Délai max d'attente de la connexion Wi-Fi avant de logguer un échec.
static const unsigned long WIFI_TIMEOUT_MS = 15000;

// Logique pure : à partir du temps courant, faut-il que la LED soit allumée ?
// Vrai pendant la première moitié de période, faux pendant la seconde.
static bool blinkState(unsigned long nowMs) {
  return (nowMs / BLINK_HALF_PERIOD_MS) % 2 == 0;
}

// Garde-fou : on n'appelle la radio que si les credentials sont renseignés.
static bool wifiConfigValid() {
  return strlen(WIFI_SSID) > 0 && strlen(WIFI_PASSWORD) > 0;
}

// Applique l'état logique à la broche (LED active à l'état bas).
static void applyLed(bool on) {
  digitalWrite(LED_PIN, on ? LOW : HIGH);
}

// Traduit le code numérique de WiFi.status() en message lisible.
static const char* wifiStatusText(wl_status_t status) {
  switch (status) {
    case WL_NO_SSID_AVAIL:
      return "SSID introuvable (hors de portee ou pas en 2.4 GHz)";
    case WL_CONNECT_FAILED:
      return "echec d'authentification (mot de passe / WPA ?)";
    case WL_CONNECTION_LOST:
      return "connexion perdue";
    case WL_DISCONNECTED:
      return "deconnecte";
    case WL_IDLE_STATUS:
      return "inactif";
    default:
      return "autre";
  }
}

// Diagnostic : scanne et logue les réseaux visibles. L'ESP32-S3 est
// mono-bande 2,4 GHz : seuls des réseaux 2,4 GHz peuvent apparaître ici.
static void scanNetworks() {
  Serial.println(F("[Scan] Reseaux 2.4 GHz visibles "
                    "(l'ESP32-S3 ne voit que le 2.4 GHz) :"));
  int found = WiFi.scanNetworks();
  if (found <= 0) {
    Serial.println(F("[Scan] Aucun reseau detecte."));
    return;
  }
  for (int i = 0; i < found; i++) {
    Serial.printf("  %2d) %-32s  RSSI %4d dBm  canal %2d  %s\n",
                  i + 1,
                  WiFi.SSID(i).c_str(),
                  WiFi.RSSI(i),
                  WiFi.channel(i),
                  WiFi.encryptionType(i) == WIFI_AUTH_OPEN ? "ouvert"
                                                           : "chiffre");
  }
  WiFi.scanDelete();
}

void setup() {
  pinMode(LED_PIN, OUTPUT);
  applyLed(false);

  Serial.begin(115200);
  // Laisse le temps à l'USB natif de s'énumérer, sans bloquer indéfiniment.
  unsigned long start = millis();
  while (!Serial && millis() - start < 2000) {
    delay(10);
  }

  Serial.println();
  Serial.println(F("=== Skald firmware hello world ==="));
  Serial.println(F("Carte : Seeed XIAO ESP32-S3 Sense"));

  if (!wifiConfigValid()) {
    Serial.println(F("[WiFi] secrets.h non renseigne : connexion ignoree."));
    return;
  }

  WiFi.mode(WIFI_STA);
  WiFi.disconnect();
  scanNetworks();

  Serial.print(F("[WiFi] SSID cible : "));
  Serial.println(WIFI_SSID);
  Serial.println(F("[WiFi] Connexion en cours..."));
  WiFi.begin(WIFI_SSID, WIFI_PASSWORD);

  // Attente non bloquante pour le reste : on continue à faire vivre la LED
  // pendant la tentative de connexion.
  start = millis();
  while (WiFi.status() != WL_CONNECTED &&
         millis() - start < WIFI_TIMEOUT_MS) {
    applyLed(blinkState(millis()));
    delay(10);
  }

  if (WiFi.status() == WL_CONNECTED) {
    Serial.print(F("[WiFi] Connecte. IP : "));
    Serial.println(WiFi.localIP());
  } else {
    Serial.printf("[WiFi] Echec (timeout). Code %d : %s. Le blink continue.\n",
                  WiFi.status(), wifiStatusText(WiFi.status()));
  }
}

void loop() {
  // Le clignotement doit vivre quoi qu'il arrive (y compris Wi-Fi en échec).
  applyLed(blinkState(millis()));
  delay(10);
}
