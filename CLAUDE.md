# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

This is a **GLPi plugin** called "Quality Audit" (`qualityaudit`) that automatically evaluates the quality of ITSM ticket solutions using AI (OpenAI, Anthropic Claude, or Google Gemini). It scores solutions on 5 criteria (spelling/grammar, completeness, effective resolution, clarity/tone, technical adequacy) and either approves or refuses them based on a configurable threshold.

- **GLPi compatibility:** 10.0.0 - 10.0.99
- **PHP:** 7.4+
- **Required PHP extensions:** curl, json
- **Language:** Primary UI and AI prompts are in Brazilian Portuguese (pt_BR)

## Architecture

This follows the standard GLPi plugin structure. There is no build system, package manager, or test framework configured.

### Entry Points
- `setup.php` — Plugin registration, version info, hooks (`item_add`, `item_update` on `ITILSolution`), and prerequisite checks
- `hook.php` — Install/uninstall (DB schema creation/drop), API key encryption/decryption helpers

### Core Classes (`inc/`)
- `PluginQualityauditAudit` (`audit.class.php`) — Main logic: intercepts solutions, builds AI prompts, calls AI APIs (OpenAI/Claude/Gemini) with retry+exponential backoff, saves audit results, triggers notifications
- `PluginQualityauditConfig` (`config.class.php`) — Entity-aware configuration with inheritance (child entities inherit from parents). Config lookup walks the entity hierarchy upward
- `PluginQualityauditMenu` (`menu.class.php`) — Menu registration under Tools
- `PluginQualityauditNotification` (`notification.class.php`) — Email notifications to technicians on refusal, admin summary reports. Uses GLPI's `Mailer` class
- `PluginQualityauditReport` (`report.class.php`) — PDF (via TCPDF) and HTML report generation with filtering

### Front Controllers (`front/`)
- `config.form.php` — Configuration page
- `dashboard.php` — Dashboard with stats
- `audit.php` — Audit list with pagination
- `reports.php` — Report generation
- `welcome.php` — First-run onboarding
- `test.php` — API connection test
- `validate_solution.php` — AJAX endpoint for real-time validation

### Client-Side
- `js/solution_validator.js` — Intercepts solution form submission, calls `validate_solution.php` via AJAX, shows score/feedback/suggestions before allowing save. Includes failsafe (allows save if API is unavailable)
- `css/responsive.css` — Responsive styles

### Database Tables (created in `hook.php`)
- `glpi_plugin_qualityaudit_configs` — Per-entity configuration (API provider, key, model, thresholds)
- `glpi_plugin_qualityaudit_audits` — Audit results (score, status, analysis, criteria scores, raw API response)
- `glpi_plugin_qualityaudit_improvements` — History of suggested improvements and whether technician accepted them

### Key Design Patterns
- **Entity inheritance:** Config resolution walks from child entity upward through ancestors, falling back to root entity (ID 0)
- **Multi-provider AI:** `callAI()` dispatches to `callOpenAI()`, `callClaude()`, or `callGemini()` based on config. Each provider has its own request format
- **AI response format:** All providers return JSON with fields: `nota` (0-100), `analise`, `status` (APROVADO/RECUSADO), `sugestao_melhoria`, `criterios`
- **Failsafe:** Client-side validator allows saving if the API call fails, preventing the plugin from blocking ticket workflow
- **API key encryption:** Uses GLPI's `GLPIKey` when available, falls back to base64 with `B64:` prefix

## Installation for Development

```bash
# Place in GLPi plugins directory
cp -r . /var/www/html/glpi/plugins/qualityaudit

# Fix permissions
chmod -R 755 /var/www/html/glpi/plugins/qualityaudit
chown -R www-data:www-data /var/www/html/glpi/plugins/qualityaudit

# Clear GLPi cache after changes
rm -rf /var/www/html/glpi/files/_cache/*
```

Then install/activate via GLPi admin: **Configurar > Plugins > Quality Audit**.

## Release Scripts

- `publish-to-github.sh` — Publishes to GitHub
- `push-and-release.sh` — Pushes and creates a GitHub release
