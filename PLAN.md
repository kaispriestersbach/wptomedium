# WPtoMedium — WordPress-Plugin Plan

## Kontext

Deutsche WordPress-Blogartikel sollen einzeln ausgewählt, via AI ins Englische übersetzt, in einem Side-by-Side-Review gegengelesen/bearbeitet und dann als Markdown oder HTML in die Zwischenablage kopiert werden, um sie manuell auf Medium (oder anderen Plattformen) einzufügen. Human-in-the-Loop: Nichts wird ohne Freigabe kopiert/publiziert.

**Architekturentscheidungen:**
- Übersetzung via **WP AI Client SDK** (provider-agnostisch: Claude, GPT, Gemini etc.)
- **Keine Medium API** — Medium vergibt seit 01/2025 keine neuen Integration Tokens mehr
- Output: **Copy-to-Clipboard** als Markdown und HTML
- Dependencies via **Vendor-Bundling** (Shared-Hosting-kompatibel, kein Composer nötig für den User)

## Voraussetzungen

- **WordPress** 6.x+
- **PHP** 7.4+
- **Keine separaten Plugins nötig** — alle Dependencies werden mitgeliefert

### Vendor-Bundling (Shared-Hosting-kompatibel)

`wp-ai-client` ist eine Composer-Library. Für Shared-Hosting bündeln wir alle Dependencies im Plugin:

**Build-Prozess (Entwickler-seitig, einmalig vor Release):**
```bash
cd wptomedium/
composer require wordpress/wp-ai-client wordpress/anthropic-ai-provider
```

Das `vendor/`-Verzeichnis wird im Release-ZIP mitgeliefert.

**Autoloader-Strategie (zukunftssicher für WP 7.0):**
```php
if ( ! class_exists( 'WordPress\\AI_Client\\AI_Client' ) ) {
    require_once WPTOMEDIUM_PLUGIN_DIR . 'vendor/autoload.php';
}
add_action( 'init', array( 'WordPress\\AI_Client\\AI_Client', 'init' ) );
```

## Dateistruktur

```
wptomedium/
  wptomedium.php                    # Plugin-Bootstrap, Autoloader, Hooks, Menü, Assets
  uninstall.php                     # Cleanup bei Deinstallation
  readme.txt                        # Standard WordPress readme
  composer.json                     # Dependencies
  composer.lock
  vendor/                           # Gebündelte Dependencies (im Release-ZIP)
  includes/
    class-wptomedium-settings.php   # Settings-Seite (AI-Model-Preference)
    class-wptomedium-translator.php # Übersetzung + Gutenberg→Medium Konvertierung
    class-wptomedium-workflow.php   # AJAX-Handler, Artikel-Liste, Review-Seite
  admin/
    css/wptomedium-admin.css        # Side-by-Side Review Styles
    js/wptomedium-admin.js          # AJAX-Calls, Copy-to-Clipboard, UI
```

**Entfallen** (kein Medium API-Client mehr nötig): ~~`class-wptomedium-medium.php`~~

## WordPress Coding Standards (aus Guidelines)

### PHP (`php_coding_standards`)
- **Tabs** für Einrückung, keine Spaces
- **snake_case** für Funktionen/Variablen: `some_function_name()`
- **Capitalized_Words** für Klassen: `WPtoMedium_Settings`
- **UPPER_CASE** für Konstanten: `WPTOMEDIUM_VERSION`
- **Yoda Conditions**: `if ( true === $value )`
- **Long Array Syntax**: `array( 1, 2, 3 )` statt `[ 1, 2, 3 ]`
- **Spaces** in Klammern: `if ( $foo ) {`, `my_function( $param )`
- **Keine Shorthand PHP Tags**: immer `<?php`
- **Sichtbarkeit** explizit: `public`, `private`, `protected`
- **`elseif`** statt `else if`
- **PHPDoc-Blocks** für alle Funktionen
- **`require_once`** ohne Klammern
- Jede PHP-Datei: `if ( ! defined( 'ABSPATH' ) ) { exit; }`
- Trailing Comma in mehrzeiligen Arrays
- Strict Comparisons: `===` und `!==`

### JavaScript (`javascript_coding_standards`)
- **camelCase** für Variablen/Funktionen
- **Tabs**, **Single Quotes**, **Spaces in Klammern**
- **`const`/`let`** statt `var`
- jQuery-Wrapper: `( function( $ ) { ... } )( jQuery );`
- Semikolons immer setzen

### CSS (`css_coding_standards`)
- **Tabs**, **Lowercase** + Hyphens: `.wptomedium-review-panel`
- Properties logisch gruppieren: Display → Position → Box Model → Colors

### Plugin Guidelines
- Main file = `wptomedium/wptomedium.php`, Text Domain = `wptomedium`
- Alle Strings in **Englisch** mit `__()` / `esc_html__()`
- Settings **Action Link** in Plugin-Zeile
- `if ( ! defined( 'ABSPATH' ) ) { exit; }` in jeder PHP-Datei
- Nonces + Capability Checks, `WP_DEBUG` aktiv entwickeln

## Datenspeicherung — Post Meta (keine Custom Tables)

| Meta Key | Typ | Zweck |
|---|---|---|
| `_wptomedium_translation` | string (HTML) | Englische Übersetzung |
| `_wptomedium_translated_title` | string | Übersetzter Titel |
| `_wptomedium_status` | `pending` / `translated` / `copied` | Workflow-Status |

## Admin-Seiten (3 Seiten unter Menüpunkt "WPtoMedium")

### 1. Settings (`wptomedium-settings`)
- Hinweis + Link zu **Settings > AI Credentials** für API-Key-Konfiguration
- Optional: bevorzugtes AI-Model wählen (oder "automatisch" = SDK entscheidet)
- **Settings Action Link** in der Plugin-Zeile

### 2. Artikel-Auswahl (`wptomedium-articles`)
- `WP_List_Table` mit allen veröffentlichten Posts
- Spalten: Titel, Datum, Status (nicht übersetzt / übersetzt / kopiert)
- Aktionen pro Zeile: "Übersetzen", "Review"

### 3. Review & Copy (`wptomedium-review&post_id=X`)
- **Side-by-Side**: Links Original (read-only HTML-Vorschau), rechts Übersetzung (editierbar)
- **Editor**: TinyMCE via `wp_editor()` mit **eingeschränkter Toolbar** — nur Medium-kompatible Formatierungen:
  - Bold, Italic, Link, Blockquote, H2, UL/OL, Code, HR
  - Keine H3-H6, keine Tabellen, keine Farben, keine Schriftgrößen
- Titel-Feld (editierbar)
- Buttons:
  - "Übersetzung speichern" — AJAX, speichert bearbeitete Version in Post Meta
  - "Neu übersetzen" — ruft AI erneut auf
  - **"Als HTML kopieren"** — kopiert formatierten HTML-Content in die Zwischenablage
  - **"Als Markdown kopieren"** — konvertiert HTML→Markdown und kopiert
- Visuelles Feedback: "Kopiert!" Toast-Nachricht nach erfolgreichem Copy

## Workflow

```
[WP-Post auswählen] → "Übersetzen" klicken
        ↓
[AJAX → Gutenberg→Medium-HTML Konvertierung → WP AI Client SDK → AI Provider]
        ↓
[Übersetzung in Post Meta speichern (status: translated)]
        ↓
[Review-Seite: Side-by-Side]
   ├─ Bearbeiten & "Speichern"
   ├─ "Neu übersetzen" → AI erneut aufrufen
   ├─ "Als HTML kopieren" → Zwischenablage
   └─ "Als Markdown kopieren" → HTML→MD Konvertierung → Zwischenablage
        ↓
[User fügt Content manuell in Medium (oder andere Plattform) ein]
```

## Technische Details

### WP AI Client SDK Integration

Feature-Detection vor Nutzung:
```php
$prompt = AI_Client::prompt( $translation_prompt )
    ->using_temperature( 0.3 );

if ( $prompt->is_supported_for_text_generation() ) {
    $translated = $prompt->generate_text();
} else {
    // Admin-Notice: Kein AI-Provider konfiguriert
}
```

### Übersetzung (`class-wptomedium-translator.php`)

Klasse `WPtoMedium_Translator` mit:
- `public function translate( $post_id )` — Hauptmethode (holt Post, konvertiert, übersetzt)
- `public function prepare_content( $post_id )` — Gutenberg → Medium-HTML Konvertierung
- `private function build_prompt( $title, $content )` — Prompt zusammenbauen
- `private function parse_response( $response )` — Titel + Content aus Antwort extrahieren
- `private function sanitize_for_medium( $html )` — `wp_kses()` mit Medium-Tag-Set
- `public function to_markdown( $html )` — HTML → Markdown Konvertierung

**Workflow**: Gutenberg-HTML → `prepare_content()` → AI-Übersetzung → `sanitize_for_medium()` → Post Meta

### Medium-akzeptierte HTML-Tags (Quelle: offizielle API-Docs)

Dokumentiert: `<h1>`, `<h2>`, `<p>`, `<blockquote>`, `<figure>`, `<img>`, `<figcaption>`, `<b>`/`<strong>`, `<i>`/`<em>`, `<a href>`, `<hr>`
Undokumentiert aber funktional: `<ul>`, `<ol>`, `<li>`, `<pre>`, `<code>`
**Nicht unterstützt**: `<h3>`-`<h6>`, `<table>`, `<div>`, `<span>`, CSS-Klassen, `<iframe>`

### Content-Konvertierung: Gutenberg → Medium-HTML

**Pipeline** (in `class-wptomedium-translator.php`, VOR der Übersetzung):

1. `apply_filters( 'the_content', $post->post_content )` — Gutenberg rendert zu HTML
2. Gutenberg-Block-Kommentare entfernen (`<!-- wp:* -->`)
3. `<h3>`-`<h6>` → `<h2>` konvertieren
4. Alle CSS-Klassen und `style`-Attribute entfernen
5. `<table>` → in formatierte Text-Absätze konvertieren
6. Galerie-Blöcke → einzelne `<figure><img><figcaption></figure>` Elemente
7. `wp_kses()` mit Medium-kompatiblem Tag-Set als finaler Sanitizer

**Medium-kompatibles `wp_kses` Tag-Set:**
```php
$allowed_tags = array(
    'h1'         => array(),
    'h2'         => array(),
    'p'          => array(),
    'a'          => array( 'href' => array() ),
    'strong'     => array(),
    'b'          => array(),
    'em'         => array(),
    'i'          => array(),
    'blockquote' => array(),
    'figure'     => array(),
    'figcaption' => array(),
    'img'        => array( 'src' => array(), 'alt' => array() ),
    'ul'         => array(),
    'ol'         => array(),
    'li'         => array(),
    'pre'        => array(),
    'code'       => array(),
    'hr'         => array(),
    'br'         => array(),
);
```

### HTML → Markdown Konvertierung

Für den "Als Markdown kopieren"-Button brauchen wir eine HTML→MD Konvertierung.
Einfache PHP-basierte Konvertierung in `to_markdown()`:
- `<h1>` → `# `, `<h2>` → `## `
- `<strong>`/`<b>` → `**...**`
- `<em>`/`<i>` → `*...*`
- `<a href="url">text</a>` → `[text](url)`
- `<img src="url" alt="alt">` → `![alt](url)`
- `<blockquote>` → `> `
- `<ul><li>` → `- `, `<ol><li>` → `1. `
- `<pre><code>` → ` ``` `
- `<hr>` → `---`
- `<p>` → Absatz mit Leerzeile

Alternativ: Composer-Library `league/html-to-markdown` mitbündeln.

### Copy-to-Clipboard (JavaScript)

Im `wptomedium-admin.js`:
```javascript
// HTML kopieren (mit Formatierung)
document.querySelector( '.wptomedium-copy-html' ).addEventListener( 'click', function() {
    const content = document.querySelector( '.wptomedium-translation-content' ).innerHTML;
    navigator.clipboard.writeText( content ).then( function() {
        // Toast: "HTML kopiert!"
    } );
} );

// Markdown kopieren (via AJAX, Server-seitige Konvertierung)
document.querySelector( '.wptomedium-copy-markdown' ).addEventListener( 'click', function() {
    // AJAX call to get Markdown version, then copy
} );
```

### Bilder
- Bild-URLs aus WordPress bleiben in der Übersetzung erhalten
- Beim Einfügen in Medium: Medium lädt Bilder von den WP-URLs
- `<figure><img src="..."><figcaption>...</figcaption></figure>` Format
- Bilder müssen öffentlich erreichbar sein (Standard bei veröffentlichten Posts)

### Sicherheit
- Nonce-Prüfung bei allen AJAX-Requests (`wp_verify_nonce()`)
- `current_user_can( 'manage_options' )` — nur Admins
- AI-Credentials werden von WP AI Client SDK verwaltet
- Nur `wp_ajax_`-Hooks (kein `nopriv`)
- Input: `sanitize_text_field()`, `wp_kses_post()`, `absint()`
- Output: `esc_html()`, `esc_attr()`, `esc_url()`

## Implementierungsreihenfolge

1. **Plugin-Skeleton**: `wptomedium.php` mit ABSPATH-Guard, Konstanten, Autoloader, Menü, Asset-Enqueueing, Settings Action Link
2. **Settings-Seite**: Link zu AI Credentials, Model-Preference (WordPress Settings API)
3. **Translator-Klasse**: Gutenberg→Medium-HTML Pipeline, AI-Übersetzung via `AI_Client::prompt()`, Markdown-Konvertierung
4. **Artikel-Liste**: `WP_List_Table`-Subklasse mit Status-Spalte und Row-Actions
5. **Review-Seite**: Side-by-Side Template, `wp_editor()` mit eingeschränkter Toolbar
6. **AJAX-Workflow**: `wp_ajax_wptomedium_translate`, `_save`, `_copy_markdown` Handler
7. **CSS/JS**: Admin-Styles (Side-by-Side Grid), Copy-to-Clipboard, Toast-Feedback
8. **readme.txt**: Standard WP Plugin readme

## Verifizierung

1. Plugin-ZIP hochladen und aktivieren (vendor/ enthalten, kein Composer nötig)
2. API-Key unter Settings > AI Credentials eintragen
3. Menüpunkt "WPtoMedium" erscheint, Settings Action Link sichtbar
4. Artikel-Liste: Veröffentlichte Posts mit Status "nicht übersetzt"
5. "Übersetzen" klicken → AJAX-Spinner → Weiterleitung zur Review-Seite
6. Side-by-Side: Original links (read-only), Übersetzung rechts (TinyMCE, eingeschränkte Toolbar)
7. Übersetzung bearbeiten → "Speichern" → Änderungen persistiert
8. "Als HTML kopieren" → Content in Zwischenablage → Toast "Kopiert!"
9. "Als Markdown kopieren" → Markdown in Zwischenablage → Toast "Kopiert!"
10. Kopierten Content in Medium einfügen → Formatierung korrekt übernommen
11. `WP_DEBUG` aktiv: keine PHP-Warnings/Notices
