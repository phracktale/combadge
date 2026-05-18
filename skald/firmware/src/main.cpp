// Firmware Skald v0.1 — capture déclenchée à la voix (VAD) + envoi.
//
// Écoute en continu : démarre l'enregistrement quand le niveau sonore
// dépasse un seuil, s'arrête après un silence prolongé, puis POST le
// segment WAV à /api/recordings. LED orange (GPIO21) clignote pendant
// l'enregistrement, éteinte au repos.
//
// Spec : skald/docs/firmware-vad.md. Pas de test (décision mainteneur).
// Vérif : compilation (V1) ; flash + terrain (V2+) par le mainteneur.
//
// Conformité : contexte.md §7 prévoit une LED ROUGE dédiée non débrayable.
// Ici LED orange intégrée = dette v0.1 (constante LED_PIN isolée).

#include <Arduino.h>
#include <WiFi.h>
#include <WiFiClientSecure.h>
#include <driver/i2s.h>

#include "secrets.h"  // WIFI_*, SKALD_API_URL, SKALD_API_TOKEN — non commité

// --- Audio ---
static const uint32_t SAMPLE_RATE = 16000;
static const uint16_t FRAME_SAMPLES = 320;                 // 20 ms
static const uint32_t FRAME_BYTES = FRAME_SAMPLES * 2;     // 16-bit mono
static const uint32_t WAV_HEADER_BYTES = 44;

// --- VAD (seuils ajustables — à calibrer sur le terrain) ---
static const int      RMS_THRESHOLD   = 1500;  // énergie trame déclenchante
static const int      START_FRAMES    = 5;     // ~100 ms au-dessus -> start
static const int      SILENCE_FRAMES  = 75;    // ~1500 ms sous le seuil -> stop
static const int      PREROLL_FRAMES  = 25;    // ~500 ms conservés avant start
static const uint32_t MAX_SEGMENT_S   = 60;    // borne PSRAM
static const uint32_t MIN_SEGMENT_MS  = 400;   // en deçà = faux positif ignoré

static const uint32_t PREROLL_BYTES = PREROLL_FRAMES * FRAME_BYTES;
static const uint32_t MAX_DATA_BYTES = MAX_SEGMENT_S * SAMPLE_RATE * 2;
static const uint32_t MIN_DATA_BYTES = (MIN_SEGMENT_MS * SAMPLE_RATE / 1000) * 2;

// --- Brochage (XIAO ESP32-S3 Sense ; à reconfirmer sur le wiki Seeed) ---
static const int PDM_CLK_PIN = 42;
static const int PDM_DATA_PIN = 41;
static const i2s_port_t I2S_PORT = I2S_NUM_0;
static const uint8_t LED_PIN = 21;             // LED intégrée orange, active bas
static const unsigned long BLINK_MS = 250;
static const unsigned long WIFI_TIMEOUT_MS = 15000;

// Buffers : pré-roll en RAM interne (petit), segment en PSRAM.
static uint8_t preroll[PREROLL_BYTES];
static uint32_t prerollFrames = 0;             // trames valides dans le ring
static uint32_t prerollHead = 0;               // index de la plus ancienne
static uint8_t* segment = nullptr;             // [WAV_HEADER + MAX_DATA] PSRAM

enum class State { Idle, Recording };
static State state = State::Idle;

// ───────────────────────── Helpers réutilisés ──────────────────────────────

static void fillWavHeader(uint8_t* buf, uint32_t dataBytes) {
  const uint32_t byteRate = SAMPLE_RATE * 2;
  const uint32_t riffSize = 36 + dataBytes;
  auto p32 = [](uint8_t* p, uint32_t v) { p[0]=v; p[1]=v>>8; p[2]=v>>16; p[3]=v>>24; };
  auto p16 = [](uint8_t* p, uint16_t v) { p[0]=v; p[1]=v>>8; };
  memcpy(buf, "RIFF", 4);      p32(buf+4, riffSize);
  memcpy(buf+8, "WAVE", 4);
  memcpy(buf+12, "fmt ", 4);   p32(buf+16, 16);
  p16(buf+20, 1); p16(buf+22, 1);
  p32(buf+24, SAMPLE_RATE);    p32(buf+28, byteRate);
  p16(buf+32, 2); p16(buf+34, 16);
  memcpy(buf+36, "data", 4);   p32(buf+40, dataBytes);
}

static bool connectWifi() {
  if (strlen(WIFI_SSID) == 0) return false;
  if (WiFi.status() == WL_CONNECTED) return true;
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
  Serial.println(F("[WiFi] Échec de connexion."));
  return false;
}

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

static bool postRecording(const uint8_t* wav, uint32_t totalBytes) {
  String host, path;
  if (!parseUrl(SKALD_API_URL, host, path)) {
    Serial.println(F("[POST] SKALD_API_URL invalide."));
    return false;
  }
  const String boundary = "----skaldFwBoundary";
  const String preamble =
      "--" + boundary + "\r\n"
      "Content-Disposition: form-data; name=\"file\"; filename=\"rec.wav\"\r\n"
      "Content-Type: audio/wav\r\n\r\n";
  const String epilogue = "\r\n--" + boundary + "--\r\n";
  const uint32_t contentLength = preamble.length() + totalBytes + epilogue.length();

  WiFiClientSecure client;
  client.setInsecure();  // v0.1 : pas de validation de chaîne (dette).
  if (!client.connect(host.c_str(), 443)) {
    Serial.println(F("[POST] échec connexion TLS."));
    return false;
  }
  client.printf("POST %s HTTP/1.1\r\n", path.c_str());
  client.printf("Host: %s\r\n", host.c_str());
  client.printf("Authorization: Bearer %s\r\n", SKALD_API_TOKEN);
  client.printf("Content-Type: multipart/form-data; boundary=%s\r\n", boundary.c_str());
  client.printf("Content-Length: %lu\r\n", (unsigned long)contentLength);
  client.print(F("Connection: close\r\n\r\n"));
  client.print(preamble);
  for (uint32_t sent = 0; sent < totalBytes;) {
    uint32_t chunk = min((uint32_t)4096, totalBytes - sent);
    client.write(wav + sent, chunk);
    sent += chunk;
  }
  client.print(epilogue);
  unsigned long start = millis();
  while (client.available() == 0 && millis() - start < 15000) delay(50);
  String status = client.readStringUntil('\n');
  client.stop();
  Serial.print(F("[POST] "));
  Serial.println(status);
  return status.indexOf(" 201") > 0 || status.indexOf(" 200") > 0;
}

// ───────────────────────────── VAD ─────────────────────────────────────────

// Énergie (RMS) d'une trame de n échantillons 16 bits. Logique pure.
static int frameRms(const int16_t* s, uint16_t n) {
  uint64_t acc = 0;
  for (uint16_t i = 0; i < n; i++) acc += (int32_t)s[i] * (int32_t)s[i];
  return (int)sqrt((double)(acc / n));
}

// LED active à l'état bas (allumée = LOW).
static void setLed(bool on) { digitalWrite(LED_PIN, on ? LOW : HIGH); }

static bool initI2s() {
  i2s_config_t cfg = {};
  cfg.mode = (i2s_mode_t)(I2S_MODE_MASTER | I2S_MODE_RX | I2S_MODE_PDM);
  cfg.sample_rate = SAMPLE_RATE;
  cfg.bits_per_sample = I2S_BITS_PER_SAMPLE_16BIT;
  cfg.channel_format = I2S_CHANNEL_FMT_ONLY_LEFT;
  cfg.communication_format = I2S_COMM_FORMAT_STAND_I2S;
  cfg.intr_alloc_flags = ESP_INTR_FLAG_LEVEL1;
  cfg.dma_buf_count = 8;
  cfg.dma_buf_len = 256;
  cfg.use_apll = false;
  i2s_pin_config_t pins = {};
  pins.bck_io_num = I2S_PIN_NO_CHANGE;
  pins.ws_io_num = PDM_CLK_PIN;
  pins.data_out_num = I2S_PIN_NO_CHANGE;
  pins.data_in_num = PDM_DATA_PIN;
  if (i2s_driver_install(I2S_PORT, &cfg, 0, nullptr) != ESP_OK) return false;
  return i2s_set_pin(I2S_PORT, &pins) == ESP_OK;
}

// Lit exactement une trame (bloquant court). Renvoie false si échec.
static bool readFrame(int16_t* frame) {
  uint32_t got = 0;
  while (got < FRAME_BYTES) {
    size_t r = 0;
    if (i2s_read(I2S_PORT, (uint8_t*)frame + got, FRAME_BYTES - got, &r,
                 pdMS_TO_TICKS(200)) != ESP_OK) {
      return false;
    }
    got += r;
  }
  return true;
}

static int aboveCount = 0;
static int silenceCount = 0;
static uint32_t dataLen = 0;             // octets de données dans le segment
static unsigned long lastBlink = 0;
static bool ledOn = false;

void setup() {
  pinMode(LED_PIN, OUTPUT);
  setLed(false);
  Serial.begin(115200);
  unsigned long t0 = millis();
  while (!Serial && millis() - t0 < 2000) delay(10);
  Serial.println();
  Serial.println(F("=== Skald firmware v0.1 — VAD + envoi ==="));

  segment = (uint8_t*)ps_malloc(WAV_HEADER_BYTES + MAX_DATA_BYTES);
  if (segment == nullptr) {
    Serial.println(F("[PSRAM] échec allocation. Arrêt."));
    return;
  }
  if (!initI2s()) {
    Serial.println(F("[I2S] échec init micro PDM. Arrêt."));
    return;
  }
  connectWifi();
  Serial.println(F("[VAD] Écoute..."));
}

void loop() {
  if (segment == nullptr) { delay(1000); return; }

  static int16_t frame[FRAME_SAMPLES];
  if (!readFrame(frame)) return;

  // Ring de pré-roll : on garde toujours les dernières PREROLL_FRAMES trames.
  uint32_t slot = ((prerollHead + prerollFrames) % PREROLL_FRAMES) * FRAME_BYTES;
  if (prerollFrames < (uint32_t)PREROLL_FRAMES) {
    prerollFrames++;
  } else {
    prerollHead = (prerollHead + 1) % PREROLL_FRAMES;
  }
  memcpy(preroll + slot, frame, FRAME_BYTES);

  int rms = frameRms(frame, FRAME_SAMPLES);

  if (state == State::Idle) {
    aboveCount = (rms > RMS_THRESHOLD) ? aboveCount + 1 : 0;
    if (aboveCount >= START_FRAMES) {
      // Démarrage : on préfixe le pré-roll au segment.
      dataLen = 0;
      for (uint32_t i = 0; i < prerollFrames; i++) {
        uint32_t idx = ((prerollHead + i) % PREROLL_FRAMES) * FRAME_BYTES;
        memcpy(segment + WAV_HEADER_BYTES + dataLen, preroll + idx, FRAME_BYTES);
        dataLen += FRAME_BYTES;
      }
      silenceCount = 0;
      state = State::Recording;
      lastBlink = millis();
      ledOn = true; setLed(true);
      Serial.println(F("[VAD] -> RECORDING"));
    }
    return;
  }

  // --- RECORDING ---
  if (dataLen + FRAME_BYTES <= MAX_DATA_BYTES) {
    memcpy(segment + WAV_HEADER_BYTES + dataLen, frame, FRAME_BYTES);
    dataLen += FRAME_BYTES;
  }
  if (millis() - lastBlink >= BLINK_MS) {
    lastBlink = millis();
    ledOn = !ledOn;
    setLed(ledOn);
  }
  silenceCount = (rms < RMS_THRESHOLD) ? silenceCount + 1 : 0;

  bool stop = silenceCount >= SILENCE_FRAMES
           || dataLen + FRAME_BYTES > MAX_DATA_BYTES;
  if (!stop) return;

  setLed(true);  // fixe pendant l'envoi
  Serial.printf("[VAD] -> FINALIZE (%lu o)\n", (unsigned long)dataLen);
  if (dataLen >= MIN_DATA_BYTES) {
    fillWavHeader(segment, dataLen);
    if (connectWifi()) {
      postRecording(segment, WAV_HEADER_BYTES + dataLen);
    } else {
      Serial.println(F("[VAD] Wi-Fi indisponible : segment perdu (v0.1)."));
    }
  } else {
    Serial.println(F("[VAD] Segment trop court, ignoré."));
  }
  // Retour à l'écoute.
  state = State::Idle;
  aboveCount = 0; silenceCount = 0; dataLen = 0;
  prerollFrames = 0; prerollHead = 0;
  ledOn = false; setLed(false);
  Serial.println(F("[VAD] Écoute..."));
}
