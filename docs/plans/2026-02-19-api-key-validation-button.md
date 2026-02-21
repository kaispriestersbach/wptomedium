# API Key Validation Button — Implementation Plan

> **For Claude:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task.

**Goal:** Ein "Validate API Key"-Button auf der Settings-Seite, der per AJAX einen minimalen Anthropic-API-Call macht und Erfolg/Fehler direkt anzeigt.

**Architecture:** Neuer AJAX-Endpoint `wptomedium_validate_key` in `WPtoMedium_Settings`, der mit dem Anthropic PHP SDK einen minimalen `messages->create()`-Call mit `max_tokens: 1` macht. Button + Feedback-Bereich im Settings-HTML, JS-Handler im bestehenden Admin-Script.

**Tech Stack:** PHP (Anthropic PHP SDK), jQuery (bestehendes Admin-JS), WordPress Settings API + AJAX

---

### Task 1: AJAX-Endpoint für Key-Validierung

**Files:**
- Modify: `wptomedium/includes/class-wptomedium-settings.php:28-47` (neue Methode + Hook)

**Step 1: Statische Methode `ajax_validate_key` hinzufügen**

Am Ende der Klasse (vor der schließenden `}`) einfügen:

```php
/**
 * AJAX handler to validate the Anthropic API key.
 */
public static function ajax_validate_key() {
	check_ajax_referer( 'wptomedium_nonce', 'nonce' );

	if ( ! current_user_can( 'manage_options' ) ) {
		wp_send_json_error( __( 'Unauthorized.', 'wptomedium' ) );
	}

	$api_key = isset( $_POST['api_key'] ) ? sanitize_text_field( wp_unslash( $_POST['api_key'] ) ) : '';
	if ( empty( $api_key ) ) {
		wp_send_json_error( __( 'Please enter an API key.', 'wptomedium' ) );
	}

	if ( ! class_exists( 'Anthropic\\Client' ) ) {
		wp_send_json_error( __( 'Anthropic SDK not available.', 'wptomedium' ) );
	}

	try {
		$client  = new \Anthropic\Client( apiKey: $api_key );
		$message = $client->messages->create(
			model: 'claude-haiku-4-5-20251001',
			maxTokens: 1,
			messages: array(
				array(
					'role'    => 'user',
					'content' => 'Hi',
				),
			),
		);
		wp_send_json_success( __( 'API key is valid!', 'wptomedium' ) );
	} catch ( \Anthropic\Core\Exceptions\AuthenticationException $e ) {
		wp_send_json_error( __( 'Invalid API key.', 'wptomedium' ) );
	} catch ( \Anthropic\Core\Exceptions\RateLimitException $e ) {
		wp_send_json_error( __( 'Rate limit exceeded. Key may be valid — try again later.', 'wptomedium' ) );
	} catch ( \Exception $e ) {
		wp_send_json_error( __( 'Connection error: ', 'wptomedium' ) . $e->getMessage() );
	}
}
```

**Step 2: AJAX-Hook registrieren**

In `wptomedium/wptomedium.php` nach Zeile 109 (`WPtoMedium_Workflow::register_ajax_handlers();`) einfügen:

```php
add_action( 'wp_ajax_wptomedium_validate_key', array( 'WPtoMedium_Settings', 'ajax_validate_key' ) );
```

**Step 3: Testen, dass der Endpoint existiert**

In WordPress einloggen, Browser-DevTools öffnen, im Network-Tab prüfen:

```
POST /wp-admin/admin-ajax.php
action=wptomedium_validate_key&nonce=<nonce>&api_key=invalid-key
```

Erwartung: JSON-Response `{"success":false,"data":"Invalid API key."}`

**Step 4: Commit**

```bash
git add wptomedium/includes/class-wptomedium-settings.php wptomedium/wptomedium.php
git commit -m "feat: add AJAX endpoint for API key validation"
```

---

### Task 2: Button und Feedback-HTML in Settings-Seite

**Files:**
- Modify: `wptomedium/includes/class-wptomedium-settings.php:83-117` (`render_api_key_field`)

**Step 1: Button + Feedback-Span nach dem Input-Feld einfügen**

Die Methode `render_api_key_field()` erweitern. Nach dem `<input type="password" .../>` und vor dem `<?php if ( ! empty( $masked ) ) : ?>` Block einfügen:

```php
<button type="button" class="button wptomedium-validate-key">
	<?php esc_html_e( 'Validate Key', 'wptomedium' ); ?>
</button>
<span class="wptomedium-validate-result" style="display:none; margin-left:10px;"></span>
```

**Step 2: Prüfen, dass der Button auf der Settings-Seite sichtbar ist**

WordPress-Admin → WPtoMedium → Settings aufrufen. Button "Validate Key" neben dem API-Key-Feld sichtbar.

**Step 3: Commit**

```bash
git add wptomedium/includes/class-wptomedium-settings.php
git commit -m "feat: add validate key button HTML to settings page"
```

---

### Task 3: JavaScript Click-Handler

**Files:**
- Modify: `wptomedium/admin/js/wptomedium-admin.js` (neuer Handler vor der schließenden `} )( jQuery );`)
- Modify: `wptomedium/wptomedium.php:88-97` (neue lokalisierte Strings)

**Step 1: Lokalisierte Strings hinzufügen**

In `wptomedium.php` im `wp_localize_script`-Array (Zeile 88-97) folgende Keys ergänzen:

```php
'validating'      => __( 'Validating...', 'wptomedium' ),
'validateKey'     => __( 'Validate Key', 'wptomedium' ),
```

**Step 2: Click-Handler in admin JS hinzufügen**

Vor `} )( jQuery );` am Ende von `wptomedium-admin.js` einfügen:

```javascript
// Validate API Key.
$( document ).on( 'click', '.wptomedium-validate-key', function() {
	var $btn    = $( this );
	var $result = $( '.wptomedium-validate-result' );
	var apiKey  = $( 'input[name="wptomedium_api_key"]' ).val();

	$btn.prop( 'disabled', true ).text( wptomediumData.validating );
	$result.hide();

	$.post( wptomediumData.ajaxUrl, {
		action:  'wptomedium_validate_key',
		nonce:   wptomediumData.nonce,
		api_key: apiKey,
	} )
	.done( function( response ) {
		if ( response.success ) {
			$result.text( response.data ).css( 'color', '#00a32a' ).fadeIn();
		} else {
			$result.text( response.data ).css( 'color', '#d63638' ).fadeIn();
		}
	} )
	.fail( function() {
		$result.text( wptomediumData.requestFailed ).css( 'color', '#d63638' ).fadeIn();
	} )
	.always( function() {
		$btn.prop( 'disabled', false ).text( wptomediumData.validateKey );
	} );
} );
```

**Step 3: Manuell testen**

1. Settings aufrufen, gültigen API Key eingeben, "Validate Key" klicken → grünes "API key is valid!"
2. Ungültigen Key eingeben → rotes "Invalid API key."
3. Leeres Feld → rotes "Please enter an API key."

**Step 4: Commit**

```bash
git add wptomedium/admin/js/wptomedium-admin.js wptomedium/wptomedium.php
git commit -m "feat: add JS handler for API key validation button"
```

---

### Task 4: CSS für Button-Alignment

**Files:**
- Modify: `wptomedium/admin/css/wptomedium-admin.css`

**Step 1: Styles hinzufügen**

Am Ende der Datei einfügen:

```css
/* API Key Validation */
.wptomedium-validate-key {
	vertical-align: middle;
	margin-left: 8px !important;
}

.wptomedium-validate-result {
	vertical-align: middle;
	font-weight: 600;
}
```

**Step 2: Visuell prüfen**

Button und Ergebnis-Text sind vertikal mit dem Input-Feld ausgerichtet.

**Step 3: Commit**

```bash
git add wptomedium/admin/css/wptomedium-admin.css
git commit -m "style: align API key validation button and result text"
```

---

### Task 5: Übersetzungen aktualisieren

**Files:**
- Modify: `wptomedium/languages/wptomedium.pot`
- Modify: `wptomedium/languages/wptomedium-de_DE.po`
- Modify: `wptomedium/languages/wptomedium-de_DE.mo`

**Step 1: .pot neu generieren**

```bash
docker run --rm -v "$(pwd)/wptomedium:/app" wordpress:cli i18n make-pot /app /app/languages/wptomedium.pot --domain=wptomedium --package-name="WPtoMedium"
```

**Step 2: Neue Strings in .po übersetzen**

In `wptomedium-de_DE.po` die neuen Strings ergänzen:

| Original | Deutsch |
|---|---|
| `Validate Key` | `API-Key prüfen` |
| `Validating...` | `Wird geprüft...` |
| `API key is valid!` | `API-Key ist gültig!` |
| `Invalid API key.` | `Ungültiger API-Key.` |
| `Please enter an API key.` | `Bitte einen API-Key eingeben.` |
| `Rate limit exceeded. Key may be valid — try again later.` | `Rate-Limit erreicht. Key möglicherweise gültig — bitte später erneut versuchen.` |
| `Connection error: ` | `Verbindungsfehler: ` |

**Step 3: .mo kompilieren**

```bash
docker run --rm -v "$(pwd)/wptomedium:/app" wordpress:cli i18n make-mo /app/languages/
```

**Step 4: Commit**

```bash
git add wptomedium/languages/
git commit -m "i18n: add German translations for API key validation"
```
