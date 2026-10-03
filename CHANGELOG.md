# Changelog

All notable changes to this project are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

## [2.0.0] - 2026-10-03

### Security

- Completed a manual security review of the admin controller, scanner, and virtual `/ai.txt` responder; no vulnerabilities found (nonces, capability checks, input sanitization, and output escaping were already correctly applied throughout).
- Ran the official WordPress Plugin Check (PCP) tool, including experimental and low-severity checks, against the plugin: zero errors or warnings.

### Added

- Development tooling: `composer.json` (PHPCS, WPCS, PHPCompatibilityWP, PHPStan, PHPUnit), `phpcs.xml.dist`, `phpstan.neon` + bootstrap, `phpunit.xml.dist`, and an initial `VersionConsistencyTest`.
- CI/CD: PHPCS (WordPress Coding Standards), PHPStan static analysis, PHPUnit, PHPCompatibilityWP (PHP 7.4+), Composer Audit, the official WordPress Plugin Check GitHub Action, CodeQL, and an automated WordPress.org release workflow.
- LICENSE, CONTRIBUTING.md, CODE_OF_CONDUCT.md, SECURITY.md, issue templates, PR template, CODEOWNERS, and dependabot configuration.
- `.editorconfig`, `.gitattributes`, `.gitignore`, `.distignore`.

### Changed

- Verified and documented compatibility with WordPress 7.1.2 (the plugin's live test target), Elementor 4.3.3, and WooCommerce 11.1.2 — no plugin code changes were required since Drishti GEO has no hooks into either plugin.
- Corrected the readme license identifier to the SPDX form `GPL-2.0-or-later`.
- Added full docblock coverage where missing, to satisfy `WordPress-Docs`.

## [1.0.0] - 2026-07-15

### Added

- Initial release.
- Multi-provider API support (OpenRouter, OpenAI, Gemini, Perplexity, Anthropic).
- Granular model selection per provider.
- Native robots.txt AI-bot blocker detection and one-click auto-fix.
- 9-Point GEO Deep Dive checklist for SEO optimization.
- Automated ai.txt generator.
