=== Anthropic AI Provider ===
Contributors: wordpressdotorg
Tags: ai, anthropic, claude, artificial-intelligence
Requires at least: 6.9
Tested up to: 6.9
Stable tag: 1.0.0
Requires PHP: 7.4
License: GPL-2.0-or-later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Anthropic (Claude) provider for the PHP AI Client SDK.

== Description ==

This plugin provides Anthropic integration for the PHP AI Client SDK. It enables WordPress sites to use Anthropic's Claude models for text generation and other AI capabilities.

**Features:**

* Text generation with Claude models
* Function calling support
* Extended thinking support
* Automatic provider registration

Available models are dynamically discovered from the Anthropic API, including Claude models for text generation with multimodal input support.

**Requirements:**

* PHP 7.4 or higher
* PHP AI Client plugin must be installed and activated
* Anthropic API key

== Installation ==

1. Ensure the PHP AI Client plugin is installed and activated
2. Upload the plugin files to `/wp-content/plugins/anthropic-ai-provider/`
3. Activate the plugin through the 'Plugins' menu in WordPress
4. Configure your Anthropic API key via the `ANTHROPIC_API_KEY` environment variable or constant

== Frequently Asked Questions ==

= How do I get an Anthropic API key? =

Visit the [Anthropic Console](https://console.anthropic.com/) to create an account and generate an API key.

= Does this plugin work without the PHP AI Client? =

No, this plugin requires the PHP AI Client plugin to be installed and activated. It provides the Anthropic-specific implementation that the PHP AI Client uses.

== Changelog ==

= 1.0.0 =
* Initial release
* Support for Claude text generation models
* Function calling support
* Extended thinking support

== Upgrade Notice ==

= 1.0.0 =
Initial release.
