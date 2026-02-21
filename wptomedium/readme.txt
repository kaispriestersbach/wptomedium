=== WP-TPTM – Translate Posts to Medium ===
Contributors: kai
Tags: translation, medium, ai, clipboard, multilingual
Requires at least: 6.0
Tested up to: 6.7
Requires PHP: 8.1
Stable tag: 1.2.3
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Translate German WordPress posts to English using Claude and copy to clipboard for Medium.

== Description ==

WP-TPTM translates your German blog posts to English using the Anthropic Claude API. Review translations side-by-side, edit them, and copy as HTML or Markdown to paste into Medium or other platforms.

Features:

* AI-powered translation via Claude (Anthropic API)
* Side-by-side review with original and translation
* Restricted TinyMCE editor for Medium-compatible formatting
* Copy as HTML or Markdown to clipboard
* Human-in-the-loop: nothing is copied without your approval

== Installation ==

1. Upload the `wptomedium` folder to `/wp-content/plugins/`
2. Activate the plugin through the Plugins menu
3. Go to WPtoMedium > Settings and enter your Anthropic API key
4. Go to WPtoMedium > Articles to start translating

== Changelog ==

= 1.2.3 =
* Translation input now renders full WordPress content pipeline, including shortcodes and dynamic blocks
* Medium sanitization remains enforced after rendering and after AI response

= 1.2.2 =
* Fix for PHP 8.2 deprecation/header warnings in admin caused by hidden submenu registration
* Hidden review submenu now uses an empty parent slug instead of null

= 1.2.1 =
* Complete Anthropic SDK error handling coverage (API exception mapping incl. timeout/connection/service errors)
* Updated translated error strings (POT/PO/MO)
* Release build hardening: ZIP is recreated from scratch and excludes macOS metadata files

= 1.2.0 =
* Security hardening for translation input pipeline (no shortcode or dynamic block execution before AI request)
* API key field no longer prefilled in settings (masked display only)
* Generic user-facing API error messages; details logged only in WP_DEBUG
* Stricter AJAX post ID validation and Medium-safe HTML sanitization on manual saves

= 1.1.0 =
* Dynamic model list fetched from Anthropic API
* API key validation with instant feedback
* German translations (de_DE)
* Build script for release ZIPs

= 1.0.0 =
* Initial release
