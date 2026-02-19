=== WP-TPTM – Translate Posts to Medium ===
Contributors: kai
Tags: translation, medium, ai, clipboard, multilingual
Requires at least: 6.0
Tested up to: 6.7
Requires PHP: 8.1
Stable tag: 1.1.0
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

= 1.1.0 =
* Dynamic model list fetched from Anthropic API
* API key validation with instant feedback
* German translations (de_DE)
* Build script for release ZIPs

= 1.0.0 =
* Initial release
