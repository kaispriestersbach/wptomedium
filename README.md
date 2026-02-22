# WPtoMedium

WordPress-Plugin: Deutsche Blogartikel per KI ins Englische übersetzen, Side-by-Side reviewen und als HTML oder Markdown in die Zwischenablage kopieren.

**Human-in-the-Loop** — nichts wird ohne Freigabe kopiert.

## Features

- **KI-Übersetzung** — Via Anthropic API (Claude)
- **Side-by-Side Review** — Original (read-only) neben editierbarer Übersetzung
- **Medium-optimiert** — TinyMCE-Editor beschränkt auf Medium-kompatible Tags
- **Copy-to-Clipboard** — HTML oder Markdown, bereit zum Einfügen in Medium
- **Gutenberg-Pipeline** — Automatische Konvertierung von Blöcken zu Medium-HTML
- **Security-Hardening** — API-Key wird nicht im Formular vorbefüllt; gerenderter Content wird vor dem AI-Request auf Medium-kompatibles HTML begrenzt

## Warum kein direkter Medium-Publish?

Medium vergibt seit Januar 2025 keine Integration Tokens mehr. Der Output ist deshalb Copy-to-Clipboard.

## Installation

1. [Release-ZIP herunterladen](https://github.com/kaispriestersbach/wptomedium/releases) oder Repo klonen
2. `wptomedium/`-Ordner nach `/wp-content/plugins/` hochladen
3. Plugin im WordPress-Admin aktivieren
4. Unter **WPtoMedium > Settings** den Anthropic API Key eingeben
5. Unter **WPtoMedium > Artikel** loslegen

## Workflow

```
Artikel auswählen → "Übersetzen" klicken
  → KI übersetzt (Gutenberg → Medium-HTML → Englisch)
  → Side-by-Side Review: Original | Übersetzung bearbeiten
  → "Als HTML kopieren" oder "Als Markdown kopieren"
  → In Medium einfügen
```

## Sicherheit

- API-Key-Feld bleibt im UI leer (bestehender Key wird maskiert angezeigt)
- Fehlermeldungen im UI enthalten keine rohen Provider-Exception-Details
- Vor der Übersetzung wird final gerenderter Content (inkl. Shortcodes und dynamischen Blöcken) auf Medium-kompatible Tags sanitisiert

## Voraussetzungen

- WordPress 6.0+
- PHP 8.1+
- Anthropic API Key ([console.anthropic.com](https://console.anthropic.com/settings/keys))

Alle Dependencies sind im Plugin gebündelt — kein Composer oder Build-Step nötig.

## Entwicklung

```bash
cd wptomedium/
composer require anthropic-ai/sdk
```

Nach Änderungen an übersetzten Strings die i18n-Dateien aktualisieren:

```bash
# .pot generieren
docker run --rm -v "$(pwd)/wptomedium:/app" wordpress:cli i18n make-pot /app /app/languages/wptomedium.pot --domain=wptomedium

# .po pflegen, dann .mo kompilieren
docker run --rm -v "$(pwd)/wptomedium:/app" wordpress:cli i18n make-mo /app/languages/
```

## Changelog

- **1.2.4**
  - Fix: Settings-Buttons feuern auf manchen Live-Seiten wieder korrekt (Admin-JS wird robust nach Page-Slug geladen)
  - Fix: AJAX/XHR von der Settings-Seite wird wieder zuverlässig ausgelöst

## Lizenz

GPLv2 or later — siehe [LICENSE](LICENSE).
