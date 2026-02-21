# WPtoMedium

WordPress-Plugin: Deutsche Blogartikel via AI (Anthropic PHP SDK) ins Englische übersetzen, Side-by-Side reviewen/bearbeiten, als HTML oder Markdown in die Zwischenablage kopieren. Human-in-the-Loop — nichts wird ohne Freigabe kopiert.

## Schlüsselentscheidungen

- **Keine Medium API** — Medium vergibt seit 01/2025 keine Integration Tokens mehr. Output ist Copy-to-Clipboard.
- **Anthropic PHP SDK** — direkte Integration der Anthropic API (Claude). Gebündelt in vendor/.
- **Vendor-Bundling** — `vendor/` wird im Release-ZIP mitgeliefert. User braucht kein Composer. Autoloader mit `class_exists()`-Guard für WP 7.0-Kompatibilität.

## Build

```bash
cd wptomedium/
composer require anthropic-ai/sdk
```

`vendor/` ins Release-ZIP einschließen. Kein Build-Step für JS/CSS.

## Übersetzungen (i18n)

Nach Änderungen an übersetzten Strings (`__()`, `esc_html__()`) oder neuen Strings vor dem Commit die Übersetzungsdateien aktualisieren:

```bash
# 1. .pot neu generieren
docker run --rm -v "$(pwd)/wptomedium:/app" wordpress:cli i18n make-pot /app /app/languages/wptomedium.pot --domain=wptomedium --package-name="WPtoMedium"

# 2. .po aktualisieren (neue Strings eintragen, deutsche Übersetzung ergänzen)
#    → wptomedium/languages/wptomedium-de_DE.po manuell pflegen

# 3. .mo kompilieren
docker run --rm -v "$(pwd)/wptomedium:/app" wordpress:cli i18n make-mo /app/languages/
```

- JS-Strings werden via `wp_localize_script` in `wptomedium.php` übergeben — neue JS-Strings dort als `__( '...', 'wptomedium' )` eintragen und in `admin/js/wptomedium-admin.js` über `wptomediumData.*` verwenden.
- Locale: `de_DE` (WordPress Standard-Deutsch).
- Alle drei Dateien (`.pot`, `.po`, `.mo`) committen.

## Versionierung & Release

Bei jedem Commit Version in diesen Dateien synchron hochzählen:
- `wptomedium/wptomedium.php` — Header `Version:` UND `WPTOMEDIUM_VERSION`
- `wptomedium/readme.txt` — `Stable tag:` + Changelog-Eintrag

Release-ZIP erstellen:

```bash
bash build.sh
```

Erzeugt `wptomedium-X.Y.Z.zip` mit dem `wptomedium/`-Ordner (ohne `.git`, `docs/`, Build-Artefakte).

## Dateistruktur

```
wptomedium/
  wptomedium.php                    # Bootstrap, Autoloader, Hooks, Menü, Assets
  uninstall.php                     # Cleanup bei Deinstallation
  readme.txt                        # WordPress readme
  composer.json
  vendor/                           # Gebündelte Dependencies
  includes/
    class-wptomedium-settings.php   # Settings-Seite (AI-Config, dynamische Modell-Liste)
    class-wptomedium-translator.php # Übersetzung + Gutenberg→Medium-HTML + Markdown
    class-wptomedium-workflow.php   # AJAX-Handler, Artikel-Liste, Review-Seite
  admin/
    css/wptomedium-admin.css        # Side-by-Side Review Styles
    js/wptomedium-admin.js          # AJAX, Copy-to-Clipboard, Toast-UI
```

## Architektur

### Datenfluss

```
Post auswählen → "Übersetzen" (AJAX)
  → Gutenberg→Medium-HTML Pipeline (prepare_content)
  → AI-Übersetzung (Anthropic PHP SDK)
  → sanitize_medium_html (script/style entfernen + wp_kses)
  → Post Meta speichern
  → Review-Seite: Side-by-Side (Original read-only | Übersetzung editierbar via wp_editor)
  → Copy-to-Clipboard (HTML oder Markdown)
```

### Post Meta

| Meta Key | Werte | Zweck |
|---|---|---|
| `_wptomedium_translation` | HTML | Englische Übersetzung |
| `_wptomedium_translated_title` | string | Übersetzter Titel |
| `_wptomedium_status` | `pending` / `translated` / `copied` | Workflow-Status |

### AJAX-Endpunkte (nur `wp_ajax_`, kein `nopriv`)

- `wp_ajax_wptomedium_translate` — Übersetzung starten
- `wp_ajax_wptomedium_save` — Bearbeitete Übersetzung speichern
- `wp_ajax_wptomedium_copy_markdown` — HTML→Markdown serverseitig konvertieren
- `wp_ajax_wptomedium_mark_copied` — Status nach erfolgreichem Clipboard-Copy auf `copied` setzen
- `wp_ajax_wptomedium_validate_key` — API-Key validieren + Modell-Liste abrufen (ein Call)
- `wp_ajax_wptomedium_refresh_models` — Modell-Liste vom API neu laden

### Admin-Seiten (Menüpunkt "WPtoMedium")

1. **Settings** (`wptomedium-settings`) — AI Credentials, Model-Dropdown (dynamisch via API, gecacht als Transient 12h, Fallback auf 2 Standardmodelle)
2. **Artikel-Auswahl** (`wptomedium-articles`) — `WP_List_Table`, Status-Spalte, Row-Actions
3. **Review & Copy** (`wptomedium-review`) — Side-by-Side, TinyMCE mit eingeschränkter Toolbar (nur Medium-kompatible Tags)

### Translator-Klasse (`WPtoMedium_Translator`)

- `translate( $post_id )` — Hauptmethode
- `prepare_content( $post_id )` — Gutenberg→Medium-HTML Pipeline
- `build_prompt( $title, $content )` — AI-Prompt
- `parse_response( $response )` — Titel + Content extrahieren
- `sanitize_medium_html( $html )` — script/style entfernen + `wp_kses()` mit Medium-Tag-Set
- `render_content_for_translation( $post_content )` — Shortcodes strippen, Blöcke ohne dynamische Callbacks rendern
- `to_markdown( $html )` — HTML→Markdown

### Medium-kompatible Tags

Erlaubt: `h1`, `h2`, `p`, `a[href]`, `strong`, `b`, `em`, `i`, `blockquote`, `figure`, `figcaption`, `img[src,alt]`, `ul`, `ol`, `li`, `pre`, `code`, `hr`, `br`
Nicht unterstützt: `h3`-`h6`, `table`, `div`, `span`, CSS-Klassen, `iframe`

### Content-Pipeline (vor Übersetzung)

1. `strip_shortcodes()` auf Raw Content
2. `parse_blocks()` + nicht-dynamisches Rendern (`innerContent`/`innerHTML`) oder Fallback `wpautop()`
3. Block-Kommentare entfernen (`<!-- wp:* -->`)
4. `h3`-`h6` → `h2`
5. `table` → Text-Absätze
6. Galerien → einzelne `figure`-Elemente
7. CSS-Klassen und `style`-Attribute entfernen
8. `sanitize_medium_html()` als finaler Sanitizer

## Coding Standards

### PHP (WordPress)

- Tabs, keine Spaces
- `snake_case` Funktionen/Variablen, `Capitalized_Words` Klassen, `UPPER_CASE` Konstanten
- Yoda Conditions: `if ( true === $value )`
- Long Array Syntax: `array( 1, 2, 3 )`
- Spaces in Klammern: `if ( $foo ) {`, `func( $param )`
- `elseif` statt `else if`, `require_once` ohne Klammern
- PHPDoc für alle Funktionen, Strict Comparisons (`===`/`!==`)
- Jede Datei: `if ( ! defined( 'ABSPATH' ) ) { exit; }`
- Trailing Comma in mehrzeiligen Arrays

### JavaScript

- camelCase, Tabs, Single Quotes, Spaces in Klammern
- `const`/`let` statt `var`, Semikolons immer
- jQuery-Wrapper: `( function( $ ) { ... } )( jQuery );`

### CSS

- Tabs, Lowercase + Hyphens: `.wptomedium-review-panel`
- Properties: Display → Position → Box Model → Colors

### Plugin-Konventionen

- Text Domain: `wptomedium`, alle Strings Englisch mit `__()`/`esc_html__()`
- Nonces + `current_user_can( 'manage_options' )` bei allen AJAX-Requests
- Input: `sanitize_text_field()`, `absint()`, `WPtoMedium_Translator::sanitize_medium_html()`
- Output: `esc_html()`, `esc_attr()`, `esc_url()`
- Mit `WP_DEBUG` aktiv entwickeln

## Voraussetzungen

- WordPress 6.x+, PHP 8.1+
- Keine separaten Plugins nötig (Dependencies gebündelt)

## Implementierungsreihenfolge

1. Plugin-Skeleton (`wptomedium.php`)
2. Settings-Seite
3. Translator-Klasse
4. Artikel-Liste (`WP_List_Table`)
5. Review-Seite (Side-by-Side, `wp_editor`)
6. AJAX-Workflow
7. CSS/JS (Admin-Styles, Copy-to-Clipboard, Toast)
8. readme.txt
