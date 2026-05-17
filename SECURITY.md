# Politique de sécurité

Combadge / Skald capte de l'audio (et à terme de l'image) et manipule des
données potentiellement sensibles, dont des données biométriques (empreintes
vocales). La sécurité est traitée comme une exigence de premier ordre.

---

## Versions prises en charge

Le projet est en phase de développement initial (pré-v0.1). Aucune version
stable n'est encore publiée. Tant qu'aucune release n'est taguée, seules les
branches `main` et `develop` reçoivent des correctifs de sécurité.

| Version | Prise en charge |
|---|---|
| `develop` (non publiée) | ✅ |
| `main` (non publiée) | ✅ |

Ce tableau sera mis à jour à la première release.

---

## Signaler une vulnérabilité

**Ne jamais** signaler une faille de sécurité via un ticket public, une
discussion ou une pull request.

Procédure de divulgation responsable :

1. Envoyer un rapport à : **thierry@phracktale.com**
   (ou utiliser l'option *Report a vulnerability* de l'onglet *Security* du
   dépôt GitHub si elle est activée).
2. Inclure si possible :
   - une description de la vulnérabilité et de son impact ;
   - les étapes de reproduction ;
   - la version ou le commit concerné ;
   - une proposition de correctif si vous en avez une.
3. Délais indicatifs :
   - accusé de réception sous **72 heures** ;
   - première évaluation sous **7 jours** ;
   - divulgation coordonnée après correctif.

Merci de laisser un délai raisonnable pour le correctif avant toute
divulgation publique.

---

## Périmètre

Sont particulièrement concernés :

- le chiffrement local des fichiers sur microSD ;
- le provisioning Wi-Fi (BLE et portail captif) ;
- l'API d'ingestion backend (authentification, transport) ;
- le stockage et la non-transmission des données biométriques.

---

## Hors périmètre

- Les vulnérabilités des dépendances tierces déjà publiquement connues et
  suivies en amont (signaler plutôt au projet amont).
- Les attaques nécessitant un accès physique prolongé non autorisé au device,
  hors menaces déjà documentées dans la roadmap éthique.
