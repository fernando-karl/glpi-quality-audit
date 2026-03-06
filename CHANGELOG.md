# Changelog

All notable changes to the Quality Audit Plugin for GLPi will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

---

## [1.2.1] - 2026-02-10

### 🛡️ Hotfix: Robust Failsafe System

**Critical improvement to handle API failures gracefully.**

### Added

#### Multi-Layer Failsafe System
- ✅ **3-retry mechanism** with exponential backoff (2s, 4s, 8s intervals)
- ✅ **Basic heuristic validation** as fallback when AI unavailable (max score: 60/100)
- ✅ **Visual failsafe UI** with explicit user controls
- ✅ **Comprehensive error logging** to php-errors.log

#### Basic Validation Fallback (Layer 2)
When AI service fails, applies rule-based validation:
- **Length check** (0-30 points): Short/adequate/complete
- **Bad pattern detection** (-20 points): "resolvido", "ok", "done", etc.
- **Sentence structure** (+15 points): At least 2 sentences required
- **Technical terms** (+15 points): Detects action words (installed, configured, restarted, etc.)
- **Politeness** (+10 points): Detects greetings/closings

**Max failsafe score:** 60/100 (indicates non-AI validation)

#### User Controls
- **"🔄 Retry" button** - Attempt validation again
- **"💾 Save Anyway" button** - Bypass with explicit confirmation
- **Visual feedback** showing basic score + heuristic feedback
- **Never auto-submits** in failsafe mode (user maintains full control)

#### Failsafe Scenarios Covered
1. **API timeout** (>10s) → retry → basic validation → UI failsafe
2. **API HTTP errors** (500, 503) → immediate failsafe
3. **Network errors** → frontend failsafe
4. **Invalid/missing API key** → immediate bypass option
5. **PHP timeout** → frontend failsafe
6. **Invalid JSON response** → retry → failsafe
7. **CORS errors** → frontend failsafe
8. **Service unavailable** → retry → failsafe

**Total:** 8 failure scenarios handled

### Changed

- `front/validate_solution.php`:
  - Added `performBasicValidation()` function (60+ lines)
  - Failsafe returns: `{failsafe: true, bypass: true, basic_score, basic_feedback}`
  - Logs all failsafe events: "AI service unavailable for ticket X"

- `js/solution_validator.js`:
  - Enhanced `showError()` to display basic validation results
  - Added visual failsafe UI (yellow warning box)
  - Shows basic score (0-60) and heuristic feedback
  - Two action buttons: Retry and Save Anyway
  - Added `qualityAuditRetryValidation()` global function
  - Added `qualityAuditBypassAndSave()` global function with confirmation

### Documentation

- Added `FAILSAFE.md` (506 lines, 12 KB)
  - Complete failsafe guide
  - 4 protection layers explained
  - 5 detailed scenarios with examples
  - Basic validation algorithm documentation
  - Configuration options
  - Log analysis guide
  - Metrics and KPIs
  - User guide (what to do in failsafe mode)
  - Security details
  - Troubleshooting section
  - Roadmap (v1.3, v2.0)

### Security

- Failsafe **does NOT bypass**:
  - Authentication (Session::checkLoginUser still required)
  - Entity access validation
  - CSRF protection
  - Minimum length validation (10 characters)

- Failsafe **allows**:
  - Saving without AI quality score (with explicit user confirmation)
  - Using basic heuristic validation as fallback

### Philosophy

> "Quality validation is important, but must never block critical helpdesk operations."

**Guarantees:**
- ✅ Users can always save (as last resort)
- ✅ Basic validation always available
- ✅ Full user control over process
- ✅ Transparency via logging

### Metrics

- **Files modified:** 2
- **Files added:** 1
- **Lines of code:** 613 added
- **Documentation:** 12 KB (FAILSAFE.md)
- **Commit:** `186ee75`

### Example

**Failsafe UI:**
```
⚠️ Safety Mode Activated (Failsafe)

AI service temporarily unavailable.

📊 Basic Validation (Heuristic):
Estimated score: 35/60 (max in failsafe mode)

Feedback:
• Solution is short. Consider adding details
• Avoid generic responses like "resolved"
• Consider adding specific actions

✅ You can save anyway

[🔄 Try Again] [💾 Save Anyway]
```

### Testing

To test failsafe mode:
1. Temporarily set invalid API key in config
2. Try to save a ticket solution
3. Should see failsafe UI with basic validation
4. Test both buttons (Retry and Save Anyway)

---

## [1.2.0] - 2026-02-10

### 🔥 Major Feature: Real-Time Solution Validation

**BREAKING CHANGE:** Plugin now validates solutions BEFORE saving, not after.

### Added

#### Real-Time Validation System
- ✅ **Pre-save validation** - Blocks ticket solution submission if quality score < threshold
- ✅ **Visual feedback interface** - Real-time score display (0-100) with color-coded progress bar
- ✅ **AI-powered suggestions** - Intelligent text rewriting for rejected solutions
- ✅ **Interactive loop** - Analysts can edit and re-validate until approval
- ✅ **Manual validation button** - Test solution quality before submitting
- ✅ **Criteria breakdown** - Shows individual scores (spelling, completeness, clarity, resolution)
- ✅ **"Use suggestion" button** - One-click application of AI-rewritten text

#### Backend
- New AJAX endpoint: `front/validate_solution.php` (122 lines)
  - Validates authentication and entity access
  - Calls AI with retry logic (max 2 attempts for real-time)
  - Returns JSON: `{valid, score, threshold, status, analysis, suggestion, criteria}`
  - Failsafe mode: allows bypass if API unavailable

#### Frontend
- New JavaScript validator: `js/solution_validator.js` (485 lines)
  - Intercepts form submission
  - AJAX validation before save
  - Dynamic UI feedback with animations
  - Score bar with color transitions (red/yellow/green)
  - Suggestion display with apply button

#### Features
- **Retry logic with exponential backoff** - 2 retries at 2s, 4s intervals
- **Threshold-aware prompts** - AI receives dynamic threshold from entity config
- **Failsafe mechanisms** - Allows save on: API down, config missing, timeout
- **Entity-aware validation** - Uses correct config per ticket entity

### Changed

- `inc/audit.class.php`:
  - `buildPrompt()` now accepts `$threshold` parameter (dynamic scoring)
  - `auditSolution()` now calls `callAIWithRetry()` instead of `callAI()`
  - Added `callAIWithRetry()` method with exponential backoff (3 retries)
  
- `setup.php`:
  - Registered JavaScript file: `$PLUGIN_HOOKS['add_javascript']`

### Documentation

- Added `REALTIME_VALIDATION.md` (429 lines)
  - Complete user guide for analysts
  - Configuration instructions
  - Best practices for achieving high scores
  - Troubleshooting guide
  - FAQ with 5 questions
  - Use cases: training, high-volume, premium support

- Updated `README.md`:
  - New section: "🔒 Validação Preventiva em Tempo Real"
  - Updated roadmap (v1.2 marked as current)
  - Added link to REALTIME_VALIDATION.md

### Technical Details

**Performance:**
- Validation time: ~3-10 seconds (2 retries max)
- Exponential backoff: 2s → 4s
- Minimum text length: 10 characters

**Security:**
- Session-based authentication (`Session::checkLoginUser()`)
- Entity access validation (`Session::haveAccessToEntity()`)
- Input sanitization (trim, length check, type casting)

**Compatibility:**
- GLPi 10.0.0 - 10.0.99
- PHP 7.4+
- Modern browsers (JavaScript ES6+)

### Metrics

- **Files:** 3 new, 3 modified
- **Lines of code:** 1,036 added
- **Documentation:** 11 KB (REALTIME_VALIDATION.md)
- **Commit:** `2bdfb98`

### Use Cases

1. **Training new technicians** - Learn from AI suggestions in real-time
2. **High-volume support** - Minimum quality gate (threshold 70)
3. **Premium support** - Excellence only (threshold 90)

---

## [1.1.0] - 2026-02-09

### Added

#### Entity-Based Configuration
- ✅ **Per-entity configuration** - Each organizational entity can have its own:
  - API Provider (OpenAI, Claude, Gemini)
  - API Key (isolated per entity)
  - AI Model selection
  - Approval threshold
  - Audit settings (auto-audit, re-audit, ticket types)

- ✅ **Configuration inheritance** - Child entities inherit parent configs
- ✅ **Recursive flag** - Control whether child entities inherit settings
- ✅ **Visual inheritance tree** - See config hierarchy in admin UI

#### Features
- Entity selector in configuration form (dropdown)
- Inheritance indicator (green = specific config, blue = inherited)
- "Delete and use inherited config" button for child entities
- Automatic config lookup based on ticket's entity

#### Database Changes
- `glpi_plugin_qualityaudit_configs`:
  - Added `entities_id` (INT, default 0)
  - Added `is_recursive` (TINYINT, default 1)
  - Added indexes: `entities_id`, `is_recursive`

- `glpi_plugin_qualityaudit_audits`:
  - Added `entities_id` (INT, default 0)
  - Added index: `entities_id`

#### API Changes
- `PluginQualityauditConfig::getConfig($entities_id)` - Entity-aware config retrieval
- `PluginQualityauditConfig::getConfigForItem($item)` - Get config from ticket entity
- `PluginQualityauditConfig::updateConfig($data)` - Handles entity-specific updates
- `PluginQualityauditConfig::deleteConfig($entities_id)` - Remove entity override
- `PluginQualityauditConfig::getAllConfigs()` - List all entity configs
- `PluginQualityauditConfig::showInheritanceTree()` - Visual hierarchy display

### Changed

- `inc/audit.class.php`:
  - `auditSolution()` now gets config by ticket's entity
  - `saveAudit()` stores `entities_id` in audit record
  - `getTicketData()` includes `entities_id`

- `front/config.form.php`:
  - Entity selector with on-change handler
  - Handles `view_entity`, `update_config`, `delete_config` actions

### Documentation

- Added `ENTITY_CONFIG.md` (391 lines)
  - Complete guide to entity-based configuration
  - Configuration lookup process explanation
  - Setup guide (3 steps)
  - Use cases: multi-tenant SaaS, departmental QA, model testing
  - Database schema documentation
  - API documentation
  - Best practices

- Updated `README.md`:
  - Added "🏢 Configuração por Entidade" section
  - Updated configuration table with Entity and Recursive options

### Migration

- Existing configs automatically migrated to `entities_id = 0` (root entity)
- `is_recursive = 1` by default (backward compatible)

### Use Cases

1. **Multi-tenant SaaS** - Isolated API keys per client
2. **Departmental QA** - Stricter thresholds for critical teams
3. **Model testing** - Test new AI models in isolated entities

### Metrics

- **Files:** 6 modified, 1 new
- **Lines of code:** 701 added
- **Documentation:** 10 KB (ENTITY_CONFIG.md)
- **Commit:** `4944abd`

---

## [1.0.0] - 2026-02-09

### Initial Release

#### Core Features

- ✅ **AI-powered quality audit** for ticket solutions
- ✅ **Multi-provider support**:
  - OpenAI (GPT-4, GPT-4o, GPT-4o-mini)
  - Anthropic (Claude 3.5 Sonnet, Claude 3 Opus)
  - Google (Gemini 1.5 Pro, Gemini 1.5 Flash)

- ✅ **4 evaluation criteria** (100 points total):
  - 🔤 Spelling & Grammar (25 pts)
  - 📝 Completeness (35 pts)
  - 💬 Clarity & Tone (20 pts)
  - 🔧 Technical Resolution (20 pts)

- ✅ **Automatic auditing** when solutions are closed
- ✅ **Dashboard** with statistics:
  - Total audits, approved, rejected, average score
  - Top 5 technicians ranking
  - Recent audits list (last 10)

#### Configuration

- Global configuration via admin panel
- Configurable approval threshold (default: 80)
- Auto-audit on solution close (configurable)
- Re-audit on solution update (optional)
- Ticket types filter (Ticket, Change, Problem)
- Notification on refusal (configurable)

#### Database Schema

Three tables:
1. `glpi_plugin_qualityaudit_configs` - Configuration settings
2. `glpi_plugin_qualityaudit_audits` - Audit records
3. `glpi_plugin_qualityaudit_improvements` - Improvement suggestions

#### Menu Integration

- Menu under "Tools" → "Quality Audit"
- Three pages:
  - Dashboard (statistics and rankings)
  - Audits (list of audits)
  - Configuration (admin settings)

#### API Integration

- OpenAI API (chat/completions endpoint)
- Anthropic API (messages endpoint)
- Google Gemini API (generateContent endpoint)

All APIs use JSON response format with structured output.

#### Files

- `setup.php` (127 lines) - Plugin initialization
- `hook.php` (116 lines) - Database schema and hooks
- `inc/audit.class.php` (313 lines) - Core audit logic
- `inc/config.class.php` (394 lines) - Configuration management
- `inc/menu.class.php` (41 lines) - Menu definition
- `front/dashboard.php` (175 lines) - Dashboard page
- `front/config.form.php` (79 lines) - Configuration form

#### Documentation

- `README.md` (9 KB) - Complete user guide
  - Features, requirements, installation
  - Configuration options
  - Recommended models
  - Usage guide
  - Evaluation criteria
  - Troubleshooting
  - Cost estimates
  - Roadmap

- `INSTALL.md` (2.4 KB) - Installation guide

### Requirements

- **GLPi:** 10.0.0 - 10.0.99
- **PHP:** 7.4+
- **Extensions:** curl, json
- **API Key:** OpenAI, Anthropic, or Google Gemini

### Compatibility

- Compatible with GLPi's native authentication
- Works with standard GLPi entities
- Respects GLPi permissions (`ticket` right for viewing)

### Metrics

- **Files:** 12 total
- **Lines of code:** 1,245
- **Documentation:** 11.4 KB
- **Commit:** `ac58799`

---

## Version History Summary

| Version | Date | Key Feature | Impact |
|---------|------|-------------|--------|
| **1.2.0** | 2026-02-10 | Real-time validation with blocking | 🔥 Revolutionary - prevents bad solutions |
| **1.1.0** | 2026-02-09 | Entity-based configuration | 🏢 Enterprise-ready multi-tenant |
| **1.0.0** | 2026-02-09 | Initial release | 🚀 Core AI audit system |

---

## Upgrade Guide

### From v1.1 to v1.2

**Database:** No migration needed (backward compatible)

**Configuration:** No changes required

**Behavior Change:**
- v1.1: Audits AFTER saving (post-mortem)
- v1.2: Validates BEFORE saving (preventive)

**Steps:**
1. `git pull origin main`
2. Clear cache: `rm -rf /var/www/html/glpi/files/_cache/*`
3. Test: Open ticket → Solution → Try to save "resolvido"
4. Expected: Blocked with feedback ✅

**Rollback (if needed):**
```bash
git checkout 4944abd  # v1.1
# Or disable JavaScript validation in setup.php
```

### From v1.0 to v1.1

**Database:** Automatic migration via `hook.php`

**Configuration:** Existing config migrated to root entity

**Steps:**
1. `git pull origin main`
2. Deactivate plugin (GLPi admin)
3. Reactivate plugin (runs migration)
4. Verify: Check "Configuration" page, select entities

**Manual Migration (if auto fails):**
```sql
ALTER TABLE glpi_plugin_qualityaudit_configs 
  ADD COLUMN entities_id INT(11) NOT NULL DEFAULT 0,
  ADD COLUMN is_recursive TINYINT(1) NOT NULL DEFAULT 1,
  ADD INDEX (entities_id),
  ADD INDEX (is_recursive);

ALTER TABLE glpi_plugin_qualityaudit_audits
  ADD COLUMN entities_id INT(11) NOT NULL DEFAULT 0,
  ADD INDEX (entities_id);
```

---

## Known Issues

### v1.2.0

- **Issue:** JavaScript validation only works in standard GLPi form
  - **Workaround:** Custom forms may need manual integration
  - **Planned fix:** v1.3 (hook-based validation)

- **Issue:** Long AI response times (>10s) may timeout
  - **Workaround:** Reduce max_retries or increase PHP timeout
  - **Planned fix:** v1.3 (async validation with WebSocket)

### v1.1.0

- **Issue:** Inheritance tree doesn't update dynamically
  - **Workaround:** Refresh page after config changes
  - **Planned fix:** v1.3 (AJAX refresh)

---

## Contributing

See [CONTRIBUTING.md](CONTRIBUTING.md) for development guidelines.

---

## License

MIT License - See [LICENSE](LICENSE) for details.

---

## Credits

**Developed by:** Fernando Karl / Rehoboam AI  
**Repository:** https://github.com/rehoboam-karl/glpi-quality-audit  
**Issues:** https://github.com/rehoboam-karl/glpi-quality-audit/issues

---

**Last Updated:** 2026-02-10  
**Latest Version:** 1.2.0
