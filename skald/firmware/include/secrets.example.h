// Gabarit des secrets Wi-Fi.
//
// Copier ce fichier en secrets.h dans le même dossier, puis renseigner les
// vraies valeurs. secrets.h est exclu du dépôt (voir .gitignore) : ne jamais
// y mettre de credentials commités.
//
//   cp include/secrets.example.h include/secrets.h

#pragma once

#define WIFI_SSID     "VOTRE_SSID"
#define WIFI_PASSWORD "VOTRE_MOT_DE_PASSE"

// Backend d'ingestion Skald : URL complète de l'endpoint d'upload (HTTPS) et
// Bearer token protégeant l'API. Récupérer le token sur Thor :
//   ssh thor 'grep ^SKALD_API_TOKEN /opt/skald/.env'
#define SKALD_API_URL   "https://api.skald.phracktale.com/api/recordings"
#define SKALD_API_TOKEN "VOTRE_TOKEN"
