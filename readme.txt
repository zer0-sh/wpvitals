=== WPVitals ===
Contributors: zer0-sh
Tags: performance, monitoring, uptime, site health, health check
Requires at least: 6.5
Tested up to: 6.8
Requires PHP: 7.4
Stable tag: 0.1.1
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Ultra lightweight WordPress plugin to monitor your website's performance, health, and security.

== Description ==

WPVitals is an open-source, ultra lightweight plugin that evaluates the overall state of your WordPress installation on a single screen: performance, core and extension updates, security vulnerabilities, and site-health fundamentals. It answers one question: *is my WordPress updated, supported, secure and well configured?*

* **PHP checks** — version and support status, memory limit, display_errors, required extensions, and PECL assumptions.
* **WordPress core** — installed version vs latest available.
* **Updates** — plugins and themes with pending updates.
* **Security** — HTTPS, XML-RPC, file editing (DISALLOW_FILE_EDIT), debug mode and security headers.
* **Site health** — cron and loopback checks.

Everything runs on your own server. **No external calls, no accounts, no API keys, no telemetry.** Results are cached with WordPress transients (12 hours by default) so it has negligible overhead. The plugin is fully translated into English and Spanish (any es_* locale falls back to es_ES).

== Installation ==

1. Extract the ZIP you downloaded and upload the `wpvitals` folder into `/wp-content/plugins/`.
2. Activate the plugin through the *Plugins* screen in WordPress.
3. Go to **wpvitals** in the admin sidebar and click **Scan now**.
4. Recommended: also open *Tools → Site Health* to see the WPVitals results integrated with WordPress core health checks.

Requirements: WordPress 6.5+ and PHP 7.4+.

== Frequently Asked Questions ==

= Does WPVitals collect data from my site? =

No. All checks run locally on your server and no data leaves your installation. No accounts, no API keys, no telemetry.

= Does it slow down my site? =

Negligible. Results are cached in transients (12 hours by default) and the front end is not affected.

= Is WPVitals available in my language? =

The plugin is translated into English and Spanish. If your WordPress uses any Spanish regional locale (es_ES, es_MX, es_CO…), it will automatically use the Spanish translations.

== Changelog ==

= 0.1.0 =
* First public release.
* Initial set of health, security and performance checks.
* Internationalization ready (English + Spanish).
* Site health integration.
