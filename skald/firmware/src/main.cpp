// Firmware Skald v0.1 — capture + envoi audio (approche 3, sans microSD).
//
// Au boot : capture RECORD_SECONDS d'audio du micro PDM intégré (carte Sense)
// dans un buffer PSRAM, construit un WAV 16 kHz / 16-bit mono, puis l'envoie
// au backend via POST multipart sur /api/recordings (Bearer token).
//
// Spec : skald/docs/firmware-audio-capture.md. Pas de test (décision
// mainteneur). Vérif : compilation (B1) ; flash + E2E (B2–B7) par le mainteneur.
//
// Choix : API I2S héritée (driver/i2s.h) pour compatibilité core arduino-esp32
// par défaut de PlatformIO (cf. spec §5). TLS : setInsecure() en v0.1 (dette
// documentée — pas de validation de chaîne).

#include <Arduino.h>
#include <WiFi.h>
#include <WiFiClientSecure.h>
#include <driver/i2s.h>

#include "secrets.h"  // WIFI_SSID/PASSWORD, SKALD_API_URL, SKALD_API_TOKEN — non commité

// --- Paramètres audio (spec validée) ---
static const uint32_t SAMPLE_RATE = 16000;        // Hz
static const uint16_t BITS_PER_SAMPLE = 16;
static const uint32_t RECORD_SECONDS = 30;
static const uint32_t DATA_BYTES =
    SAMPLE_RATE * (BITS_PER_SAMPLE / 8) * RECORD_SECONDS;  // ≈ 960 000
static const uint32_t WAV_HEADER_BYTES = 44;

// --- Brochage micro PDM de la carte XIAO ESP32-S3 Sense ---
// Valeurs de l'exemple officiel Seeed (CLK=GPIO42, DATA=GPIO41).
// À RECONFIRMER sur le wiki Seeed avant flash (même rigueur que la LED du
// hello world — pas d'invention).
static const int PDM_CLK_PIN = 42;
static const int PDM_DATA_PIN = 41;
static const i2s_port_t I2S_PORT = I2S_NUM_0;

static const unsigned long WIFI_TIMEOUT_MS = 15000;

// Écrit l'en-tête WAV PCM (44 octets) en tête du buffer. Logique pure.
static void fillWavHeader(uint8_t* buf, uint32_t dataBytes) {
  const uint32_t byteRate = SAMPLE_RATE * (BITS_PER_SAMPLE / 8);
  const uint32_t riffSize = 36 + dataBytes;
  auto put32 = [](uint8_t* p, uint32_t v) {
    p[0] = v; p[1] = v >> 8; p[2] = v >> 16; p[3] = v >> 24;
  };
  auto put16 = [](uint8_t* p, uint16_t v) { p[0] = v; p[1] = v >> 8; };

  memcpy(buf, "RIFF", 4);          put32(buf + 4, riffSize);
  memcpy(buf + 8, "WAVE", 4);
  memcpy(buf + 12, "fmt ", 4);     put32(buf + 16, 16);
  put16(buf + 20, 1);              // PCM
  put16(buf + 22, 1);              // mono
  put32(buf + 24, SAMPLE_RATE);
  put32(buf + 28, byteRate);
  put16(buf + 32, BITS_PER_SAMPLE / 8);   // block align
  put16(buf + 34, BITS_PER_SAMPLE);
  memcpy(buf + 36, "data", 4);     put32(buf + 40, dataBytes);
}

// Connexion Wi-Fi station, non bloquante avec timeout. Vrai si connecté.
static bool connectWifi() {
  if (strlen(WIFI_SSID) == 0) {
    Serial.println(F("[WiFi] secrets.h non renseigné."));
    return false;
  }
  Serial.print(F("[WiFi] Connexion à "));
  Serial.println(WIFI_SSID);
  WiFi.mode(WIFI_STA);
  WiFi.begin(WIFI_SSID, WIFI_PASSWORD);
  unsigned long start = millis();
  while (WiFi.status() != WL_CONNECTED && millis() - start < WIFI_TIMEOUT_MS) {
    delay(100);
  }
  if (WiFi.status() == WL_CONNECTED) {
    Serial.print(F("[WiFi] Connecté. IP : "));
    Serial.println(WiFi.localIP());
    return true;
  }
  Serial.println(F("[WiFi] Échec de connexion (timeout)."));
  return false;
}

// Initialise le micro PDM (I2S RX) et remplit buf[offset..] avec dataBytes.
static bool captureToPsram(uint8_t* buf, uint32_t offset, uint32_t dataBytes) {
  i2s_config_t cfg = {};
  cfg.mode = (i2s_mode_t)(I2S_MODE_MASTER | I2S_MODE_RX | I2S_MODE_PDM);
  cfg.sample_rate = SAMPLE_RATE;
  cfg.bits_per_sample = I2S_BITS_PER_SAMPLE_16BIT;
  cfg.channel_format = I2S_CHANNEL_FMT_ONLY_LEFT;
  cfg.communication_format = I2S_COMM_FORMAT_STAND_I2S;
  cfg.intr_alloc_flags = ESP_INTR_FLAG_LEVEL1;
  cfg.dma_buf_count = 8;
  cfg.dma_buf_len = 1024;
  cfg.use_apll = false;

  i2s_pin_config_t pins = {};
  pins.bck_io_num = I2S_PIN_NO_CHANGE;
  pins.ws_io_num = PDM_CLK_PIN;
  pins.data_out_num = I2S_PIN_NO_CHANGE;
  pins.data_in_num = PDM_DATA_PIN;

  if (i2s_driver_install(I2S_PORT, &cfg, 0, nullptr) != ESP_OK) {
    Serial.println(F("[I2S] échec i2s_driver_install."));
    return false;
  }
  if (i2s_set_pin(I2S_PORT, &pins) != ESP_OK) {
    Serial.println(F("[I2S] échec i2s_set_pin."));
    return false;
  }

  Serial.printf("[Capture] %lu s à %lu Hz...\n",
                (unsigned long)RECORD_SECONDS, (unsigned long)SAMPLE_RATE);
  uint32_t got = 0;
  while (got < dataBytes) {
    size_t bytesRead = 0;
    uint32_t toRead = min((uint32_t)4096, dataBytes - got);
    if (i2s_read(I2S_PORT, buf + offset + got, toRead, &bytesRead,
                 pdMS_TO_TICKS(1000)) != ESP_OK) {
      Serial.println(F("[I2S] échec i2s_read."));
      i2s_driver_uninstall(I2S_PORT);
      return false;
    }
    got += bytesRead;
  }
  i2s_driver_uninstall(I2S_PORT);
  Serial.println(F("[Capture] terminée."));
  return true;
}

// Découpe SKALD_API_URL (https://host/path) en host + path.
static bool parseUrl(const char* url, String& host, String& path) {
  String u(url);
  if (!u.startsWith("https://")) return false;
  u = u.substring(8);
  int slash = u.indexOf('/');
  if (slash < 0) { host = u; path = "/"; return true; }
  host = u.substring(0, slash);
  path = u.substring(slash);
  return true;
}

// POST multipart du WAV, corps streamé depuis la PSRAM (pas de copie).
static bool postRecording(const uint8_t* wav, uint32_t totalBytes) {
  String host, path;
  if (!parseUrl(SKALD_API_URL, host, path)) {
    Serial.println(F("[POST] SKALD_API_URL invalide (https:// attendu)."));
    return false;
  }

  const String boundary = "----skaldFwBoundary";
  const String preamble =
      "--" + boundary + "\r\n"
      "Content-Disposition: form-data; name=\"file\"; filename=\"rec.wav\"\r\n"
      "Content-Type: audio/wav\r\n\r\n";
  const String epilogue = "\r\n--" + boundary + "--\r\n";
  const uint32_t contentLength =
      preamble.length() + totalBytes + epilogue.length();

  WiFiClientSecure client;
  client.setInsecure();  // v0.1 : pas de validation de chaîne (dette, spec §5).
  Serial.print(F("[POST] Connexion TLS à "));
  Serial.println(host);
  if (!client.connect(host.c_str(), 443)) {
    Serial.println(F("[POST] échec connexion TLS."));
    return false;
  }

  client.printf("POST %s HTTP/1.1\r\n", path.c_str());
  client.printf("Host: %s\r\n", host.c_str());
  client.printf("Authorization: Bearer %s\r\n", SKALD_API_TOKEN);
  client.printf("Content-Type: multipart/form-data; boundary=%s\r\n",
                boundary.c_str());
  client.printf("Content-Length: %lu\r\n", (unsigned long)contentLength);
  client.print(F("Connection: close\r\n\r\n"));

  client.print(preamble);
  for (uint32_t sent = 0; sent < totalBytes;) {
    uint32_t chunk = min((uint32_t)4096, totalBytes - sent);
    client.write(wav + sent, chunk);
    sent += chunk;
  }
  client.print(epilogue);

  // Lit la ligne de statut HTTP.
  unsigned long start = millis();
  while (client.available() == 0 && millis() - start < 15000) delay(50);
  String status = client.readStringUntil('\n');
  client.stop();
  Serial.print(F("[POST] Réponse : "));
  Serial.println(status);
  return status.indexOf(" 201") > 0 || status.indexOf(" 200") > 0;
}

void setup() {
  Serial.begin(115200);
  unsigned long t0 = millis();
  while (!Serial && millis() - t0 < 2000) delay(10);
  Serial.println();
  Serial.println(F("=== Skald firmware v0.1 — capture + envoi ==="));

  const uint32_t totalBytes = WAV_HEADER_BYTES + DATA_BYTES;
  uint8_t* wav = (uint8_t*)ps_malloc(totalBytes);
  if (wav == nullptr) {
    Serial.printf("[PSRAM] échec allocation %lu o. Arrêt.\n",
                  (unsigned long)totalBytes);
    return;
  }
  Serial.printf("[PSRAM] %lu o alloués.\n", (unsigned long)totalBytes);

  if (!captureToPsram(wav, WAV_HEADER_BYTES, DATA_BYTES)) {
    free(wav);
    return;
  }
  fillWavHeader(wav, DATA_BYTES);

  if (!connectWifi()) {
    free(wav);
    return;
  }

  bool ok = postRecording(wav, totalBytes);
  free(wav);
  Serial.println(ok ? F("[OK] Enregistrement envoyé.")
                     : F("[ERREUR] Envoi échoué."));
}

void loop() {
  // Clip unique au boot (v0.1). Le contrôle (touch/LED) viendra dans
  // feat-rec-control.
  delay(1000);
}
