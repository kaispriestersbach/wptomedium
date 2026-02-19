# WPtoMedium

WordPress-Plugin: Deutsche Blogartikel per KI ins Englische übersetzen, Side-by-Side reviewen und als HTML oder Markdown in die Zwischenablage kopieren.

**Human-in-the-Loop** — nichts wird ohne Freigabe kopiert.

## Features

- **KI-Übersetzung** — Provider-agnostisch via [WP AI Client SDK](https://developer.wordpress.org/ai/) (Claude, GPT, Gemini)
- **Side-by-Side Review** — Original (read-only) neben editierbarer Übersetzung
- **Medium-optimiert** — TinyMCE-Editor beschränkt auf Medium-kompatible Tags
- **Copy-to-Clipboard** — HTML oder Markdown, bereit zum Einfügen in Medium
- **Gutenberg-Pipeline** — Automatische Konvertierung von Blöcken zu Medium-HTML

## Warum kein direkter Medium-Publish?

Medium vergibt seit Januar 2025 keine Integration Tokens mehr. Der Output ist deshalb Copy-to-Clipboard.

## Installation

1. [Release-ZIP herunterladen](https://github.com/kaispriestersbach/wptomedium/releases) oder Repo klonen
2. `wptomedium/`-Ordner nach `/wp-content/plugins/` hochladen
3. Plugin im WordPress-Admin aktivieren
4. KI-Zugangsdaten unter **Einstellungen > AI Credentials** konfigurieren
5. Unter **WPtoMedium > Artikel** loslegen

## Workflow

```
Artikel auswählen → "Übersetzen" klicken
  → KI übersetzt (Gutenberg → Medium-HTML → Englisch)
  → Side-by-Side Review: Original | Übersetzung bearbeiten
  → "Als HTML kopieren" oder "Als Markdown kopieren"
  → In Medium einfügen
```

## Voraussetzungen

- WordPress 6.0+
- PHP 7.4+
- KI-API-Zugang (Claude, GPT oder Gemini) konfiguriert im WP AI Client SDK

Alle Dependencies sind im Plugin gebündelt — kein Composer oder Build-Step nötig.

## Entwicklung

```bash
cd wptomedium/
composer require wordpress/wp-ai-client wordpress/anthropic-ai-provider
```

Nach Änderungen an übersetzten Strings die i18n-Dateien aktualisieren:

```bash
# .pot generieren
docker run --rm -v "$(pwd)/wptomedium:/app" wordpress:cli i18n make-pot /app /app/languages/wptomedium.pot --domain=wptomedium

# .po pflegen, dann .mo kompilieren
docker run --rm -v "$(pwd)/wptomedium:/app" wordpress:cli i18n make-mo /app/languages/
```

## Lizenz

GPLv2 or later — siehe [LICENSE](LICENSE).
