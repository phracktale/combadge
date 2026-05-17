# Philosophie du projet

## Mission

**Combadge** est une plateforme open source de devices portables IA. Elle
propose une alternative aux wearables IA propriétaires (Plaud Note, Omi,
Limitless, Meta Ray-Ban) en supprimant trois verrous :

1. **L'abonnement obligatoire** pour exploiter ses propres données.
2. **Le cloud propriétaire imposé.**
3. **Le modèle IA imposé.**

Vous payez un device une fois ; vous restez propriétaire de vos données et
libre de choisir où et comment elles sont traitées.

## Principe directeur : Bring Your Own AI

Le traitement (transcription, traduction, résumé) passe par une interface de
fournisseur abstraite. L'utilisateur·rice choisit son moteur :

- **Ollama** en local (confidentialité totale, aucune donnée ne sort de la
  machine) ;
- **Claude**, **Mistral**, **GPT**, **Gemini** ou tout endpoint compatible
  OpenAI, selon les besoins et la sensibilité des données.

Aucun fournisseur n'est imposé par défaut au niveau de la plateforme.

## Origine du nom

« Combadge » évoque le communicateur en forme d'insigne de *Star Trek : The
Next Generation* — porté, discret, activé au toucher. Le projet n'a **aucun
lien** avec Paramount ou CBS ; l'inspiration est purement esthétique.

Le premier projet, **Skald**, est nommé d'après les poètes norrois
transmetteurs de mémoire orale. L'objet ne sert pas à surveiller, mais à se
souvenir et à comprendre.

## Éthique de captation (non négociable)

Un device qui capte du son — et à terme de l'image — engage la responsabilité
légale et morale de la personne qui le porte. Ces contraintes sont intégrées
dès le firmware, non débrayables :

- **LED rouge dédiée « REC »** visible dès que l'enregistrement est actif, non
  désactivable par firmware.
- **Mode « off-the-record »** déclenché par tap long, suspendant la capture.
- **Chiffrement local** des fichiers (AES-256 via clé dérivée).
- **Empreintes vocales = données biométriques** (RGPD art. 9) : stockage local
  uniquement, jamais transmises sans opt-in explicite.

Le projet refusera toute contribution introduisant un mode d'enregistrement
clandestin.

### Cadre juridique de référence

- Code pénal français, art. 226-1 — captation de paroles privées sans
  consentement.
- Règlement (UE) 2016/679 (RGPD), art. 6, 7, 9 — licéité, consentement,
  données biométriques.
- Position de la CNIL (2019, mise à jour 2023) — empreintes vocales = données
  biométriques au sens de l'art. 9.
- Code de la propriété intellectuelle, art. L121-1 — droit moral inaliénable
  de l'auteur (motive le refus de l'Unlicense, juridiquement défaillante en
  droit français).

## Licences

- **Code** (firmware, backend, pipeline, mobile) : MIT
- **Schémas matériels** (KiCad, STL) : CERN-OHL-S v2
- **Documentation** : CC BY-SA 4.0

## Lisibilité comme exigence

Le code doit pouvoir servir de support de formation : pas d'astuces obscures,
pas de one-liners cryptiques. La documentation précède le code — une
fonctionnalité non documentée n'existe pas.
