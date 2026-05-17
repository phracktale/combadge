# skald/pipeline

Workers de traitement audio : transcription (STT), diarisation, traduction,
résumé. Fournisseur LLM au choix via interface abstraite.

Stack : Python 3.12 / FastAPI / Celery. STT : faster-whisper (défaut),
Moshi STT (option). Diarisation : pyannote.audio. Traduction : NLLB-200.
Tests : pytest.

Réutilise `shared/pipeline-core/` (interface `LLMProvider`).

> Dossier en attente d'implémentation. Voir `docs/architecture.md`.
