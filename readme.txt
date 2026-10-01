=== Drishti GEO - AI Visibility Tracking and Analytics ===
Contributors: techeshta, alkesh7, bhattu, seljabhalala
Tags: generative engine optimization, ai visibility, robots.txt, chatgpt, gemini
Requires at least: 5.8
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Tracks website and brand visibility across major AI engines using your choice of API provider, featuring a native robots.txt blocker alert.

== Description ==
Drishti GEO empowers website owners to monitor their brand visibility and sentiment across major AI conversational engines like ChatGPT, Gemini, Perplexity, Claude, and Apple Intelligence (via Proxy). Connect using OpenRouter, OpenAI, Gemini, Perplexity, or Anthropic APIs to get a comprehensive view of how AI represents your business. 

Features include:
* Multi-Provider API support (OpenRouter, OpenAI, Gemini, Perplexity, Anthropic).
* Native robots.txt AI-bot blocker detection.
* 9-Point GEO Deep Dive checklist for SEO optimization.
* Automated ai.txt generator to manage your AI crawling preferences.

== Installation ==
1. Upload the plugin files to the `/wp-content/plugins/drishti-geo` directory, or install the plugin through the WordPress plugins screen directly.
2. Activate the plugin through the 'Plugins' screen in WordPress.
3. Navigate to the Drishti GEO tab in the admin menu.
4. Enter your brand name, core keywords, and select an API Provider in the Configuration tab.
5. Provide the API key for your selected provider and hit Test Connection.
6. Click 'Run AI Scan' in the Command Center.

== Frequently Asked Questions ==
= Do I need an API key to use this plugin? =
Yes. You can select one of the supported providers (OpenAI, Gemini, Perplexity, Anthropic) or use an aggregator like OpenRouter to access multiple models with a single key. Alternatively, if another plugin has already configured a WordPress AI Client connection for OpenAI, Anthropic, or Google, Drishti GEO can reuse that connection instead of a separate key.

== External services ==
This plugin relies on external AI provider APIs to check whether AI engines mention your brand, and to power the "Test Connection" and "9-Point Deep Dive" features. Only one provider is contacted per request, based on the provider you select in the Configuration tab (or, if you enable "Use existing connection", the same underlying provider via an existing WordPress AI Client connection).

The data sent to whichever provider is active is limited to: the brand name and target keywords you enter in the Configuration tab (used to build the scan question sent to the AI model), and, for the connection test, the fixed word "Hello". No visitor data or personal information is sent. Requests are sent to the provider's API only when you click "Test Connection" or "Run AI Scan" in the admin dashboard, or automatically once every 24 hours if you enable the "Daily Auto-Scan" option.

* **OpenAI** — used for AI visibility scans and connection testing when OpenAI is the selected provider.
[Terms of use](https://openai.com/policies/terms-of-use), [Privacy policy](https://openai.com/policies/privacy-policy)

* **Google Gemini** — used for AI visibility scans and connection testing when Gemini is the selected provider.
[Gemini API additional terms](https://ai.google.dev/gemini-api/terms), [Google privacy policy](https://policies.google.com/privacy)

* **Anthropic (Claude)** — used for AI visibility scans and connection testing when Anthropic is the selected provider.
[Terms of service](https://www.anthropic.com/legal/consumer-terms), [Privacy policy](https://www.anthropic.com/legal/privacy)

* **Perplexity AI** — used for AI visibility scans and connection testing when Perplexity is the selected provider.
[Terms of service](https://perplexity.ai/hub/legal/terms-of-service), [Privacy policy](https://www.perplexity.ai/hub/legal/privacy-policy)

* **OpenRouter** — an aggregator used for AI visibility scans and connection testing when OpenRouter is the selected provider, routing the request to the OpenAI/Google/Anthropic model you pick from its catalog.
[Terms of service](https://openrouter.ai/terms), [Privacy policy](https://openrouter.ai/privacy)

== Changelog ==
= 1.0.0 =
* Initial release.
* Multi-provider API support (OpenRouter, OpenAI, Gemini, Perplexity, Anthropic).
* Granular model selection per provider.
* Native robots.txt AI-bot blocker detection and one-click auto-fix.
* 9-Point GEO Deep Dive checklist for SEO optimization.
* Automated ai.txt generator.
