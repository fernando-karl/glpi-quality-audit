# 🏢 Entity-Based Configuration Guide

## Overview

The Quality Audit plugin supports **per-entity configuration**, allowing different organizational units to have their own audit settings, API keys, and quality thresholds.

---

## 📋 Key Features

### ✅ Entity-Specific Settings
- Each entity can have its own:
  - API Provider (OpenAI, Claude, or Gemini)
  - API Key (encrypted)
  - AI Model selection
  - Auto-audit settings
  - Approval threshold (score)
  - Ticket types to audit

### ✅ Configuration Inheritance
- **Child entities automatically inherit** parent configuration
- Override inheritance by creating specific config for a child entity
- **Recursive flag** controls whether child entities inherit settings

### ✅ Visual Inheritance Tree
- See which entities have specific configs
- Identify where configurations are inherited from
- Understand the complete hierarchy

---

## 🎯 How It Works

### Configuration Lookup Process

When a ticket solution is audited, the plugin:

1. **Identifies the ticket's entity** (e.g., "IT Department")
2. **Searches for configuration** in this order:
   - Exact match for ticket's entity
   - Parent entity (if parent has `is_recursive = 1`)
   - Grandparent entity (if recursive)
   - Root entity (fallback)
3. **Uses the first match found**

### Example Hierarchy

```
Root Entity (0)
├── Company A (ID: 1)
│   ├── IT Department (ID: 3)
│   └── HR Department (ID: 4)
└── Company B (ID: 2)
    └── Support Team (ID: 5)
```

**Configuration scenarios:**

| Entity | Has Config | Recursive | Result |
|--------|------------|-----------|--------|
| Root (0) | ✅ Yes | ✅ Yes | All entities use this unless overridden |
| Company A | ✅ Yes | ✅ Yes | IT/HR inherit from Company A |
| IT Department | ❌ No | - | Inherits from Company A |
| HR Department | ✅ Yes | ❌ No | Uses own config |
| Company B | ❌ No | - | Inherits from Root |
| Support Team | ✅ Yes | ✅ Yes | Uses own config |

---

## 🚀 Setup Guide

### Step 1: Configure Root Entity

1. Go to **Tools → Quality Audit → Configuration**
2. Select **Entity:** Root Entity (default)
3. Configure:
   - API Provider: OpenAI
   - API Key: `sk-...`
   - Model: `gpt-4o-mini`
   - Threshold: 80
4. Set **Recursive:** Yes
5. Save

✅ **All entities now have a base configuration**

### Step 2: Override for Specific Entity

1. Go to **Tools → Quality Audit → Configuration**
2. Select **Entity:** Company A (dropdown)
3. Configure different settings:
   - API Provider: Claude
   - API Key: `sk-ant-...`
   - Model: `claude-3-5-sonnet-20241022`
   - Threshold: 90 (stricter)
4. Set **Recursive:** Yes
5. Save

✅ **Company A and its children now use Claude with stricter threshold**

### Step 3: Non-Recursive Config

1. Select **Entity:** IT Department
2. Configure:
   - API Provider: Gemini
   - API Key: `AI...`
   - Threshold: 70 (more lenient)
3. Set **Recursive:** No
4. Save

✅ **Only IT Department uses this config** (HR still inherits from Company A)

---

## 📊 Viewing Configurations

### Current Entity Info

The config form shows:
- **Current entity** in dropdown
- **Inheritance status:**
  - 🟢 "Specific configuration for this entity" (has own config)
  - 🔵 "Inherited from: [Entity Name]" (using parent config)

### Inheritance Tree

Below the form, admins can see:

```
┌────────────────────────────────────────────────────┐
│ Configuration Inheritance Tree                     │
├───────────────┬────────────┬───────────┬───────────┤
│ Entity        │ Has Config │ Recursive │ Provider  │
├───────────────┼────────────┼───────────┼───────────┤
│ Root Entity   │ ✓ Yes      │ Yes       │ openai    │
│   Company A   │ ✓ Yes      │ Yes       │ claude    │
│     IT Dept   │ ✓ Yes      │ No        │ gemini    │
│     HR Dept   │ ✗ Inherited│ -         │ -         │
│   Company B   │ ✗ Inherited│ -         │ -         │
└───────────────┴────────────┴───────────┴───────────┘
```

---

## 🛠️ Use Cases

### Use Case 1: Different API Keys per Company

**Scenario:** Company A and Company B are separate clients using the same GLPi instance.

**Solution:**
- Root Entity: Default OpenAI key (fallback)
- Company A Entity: Company A's OpenAI key, Recursive = Yes
- Company B Entity: Company B's Claude key, Recursive = Yes

**Result:** Each company's tickets are audited using their own API credentials.

---

### Use Case 2: Stricter QA for Critical Departments

**Scenario:** Support Team needs stricter quality control (threshold 90) than regular departments (threshold 80).

**Solution:**
- Root Entity: Threshold = 80, Recursive = Yes
- Support Team Entity: Threshold = 90, Recursive = No

**Result:** Support tickets require 90+ score, others require 80+.

---

### Use Case 3: Testing New AI Model in One Department

**Scenario:** Want to test `gemini-1.5-flash` in IT Department before rolling out company-wide.

**Solution:**
- Root Entity: Model = `gpt-4o-mini` (stable)
- IT Department: Model = `gemini-1.5-flash` (testing), Recursive = No

**Result:** IT tickets use Gemini, all others use GPT-4o-mini.

---

## 🔐 Security

### API Key Isolation

- **Each entity's API key is encrypted** in the database
- Entities cannot see each other's API keys
- Only `config` right holders can view/edit

### Permission Model

| Right | Permission | Can Do |
|-------|------------|--------|
| `config` (UPDATE) | Admin | View/edit all entity configs |
| `ticket` (READ) | Technician | View audit results for their tickets |
| None | User | No access |

---

## 📝 Database Schema

### Table: `glpi_plugin_qualityaudit_configs`

```sql
CREATE TABLE `glpi_plugin_qualityaudit_configs` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `entities_id` INT(11) NOT NULL DEFAULT 0,
  `is_recursive` TINYINT(1) NOT NULL DEFAULT 1,
  `api_provider` VARCHAR(50) DEFAULT 'openai',
  `api_key` VARCHAR(255) DEFAULT NULL,
  `api_model` VARCHAR(100) DEFAULT 'gpt-4o-mini',
  `auto_audit` TINYINT(1) DEFAULT 1,
  `reaudit_on_update` TINYINT(1) DEFAULT 0,
  `approval_threshold` INT(11) DEFAULT 80,
  `audit_ticket_types` VARCHAR(255) DEFAULT 'Ticket,Change,Problem',
  `notification_on_refusal` TINYINT(1) DEFAULT 1,
  `date_mod` TIMESTAMP NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `entities_id` (`entities_id`),
  KEY `is_recursive` (`is_recursive`)
);
```

**Key fields:**
- `entities_id`: Entity this config belongs to (0 = root)
- `is_recursive`: Whether child entities inherit this config

---

## 🧪 Testing Entity Configuration

### Test Case 1: Verify Inheritance

1. Configure Root Entity with OpenAI
2. Create ticket in child entity without specific config
3. Close ticket with solution
4. Check audit log → should use OpenAI (inherited)

### Test Case 2: Verify Override

1. Create specific config for child entity with Claude
2. Create ticket in that entity
3. Close ticket with solution
4. Check audit log → should use Claude (overridden)

### Test Case 3: Verify Non-Recursive

1. Configure entity with `is_recursive = 0`
2. Create ticket in grandchild entity
3. Close ticket with solution
4. Check audit log → should NOT use this config

---

## 🔧 Troubleshooting

### Issue: "API key not configured"

**Cause:** No config found for the ticket's entity.

**Solution:**
1. Check entity hierarchy
2. Ensure root entity has config
3. Verify `is_recursive` is enabled on parent configs

### Issue: Wrong API provider being used

**Cause:** Inheritance picking up wrong config.

**Solution:**
1. View inheritance tree
2. Check which parent has `is_recursive = 1`
3. Create specific config for entity if needed

### Issue: Cannot delete config

**Cause:** Trying to delete root entity config.

**Solution:** Root entity config cannot be deleted (fallback). Edit it instead.

---

## 📊 Best Practices

### ✅ DO:
- **Configure root entity first** (fallback for all)
- **Use recursive configs** for departments with multiple sub-teams
- **Test new models** in isolated entities before rolling out
- **Document custom configs** per entity (internal wiki)

### ❌ DON'T:
- **Don't leave root entity unconfigured** (audits will fail)
- **Don't use different API providers** for sub-entities unless needed (costs)
- **Don't set `is_recursive = 0`** unless intentional isolation
- **Don't share API keys** across different companies in multi-tenant setups

---

## 🚀 Migration from v1.0 (Global Config)

If upgrading from v1.0 (single global config):

### Automatic Migration

On plugin update, the install hook will:
1. Add `entities_id` and `is_recursive` columns
2. Set existing config to `entities_id = 0` (root)
3. Set `is_recursive = 1` (all entities inherit)

### Manual Steps (Optional)

1. Review root entity config
2. Create entity-specific overrides if needed
3. Test auditing in each entity

---

## 📚 API Documentation

### Getting Configuration for Entity

```php
// Get config for specific entity
$config = PluginQualityauditConfig::getConfig($entities_id);

// Get config for current session entity
$config = PluginQualityauditConfig::getConfig();

// Get config for a ticket's entity
$ticket = new Ticket();
$ticket->getFromDB($ticket_id);
$config = PluginQualityauditConfig::getConfig($ticket->fields['entities_id']);
```

### Updating Configuration

```php
$data = [
   'entities_id' => 5,
   'is_recursive' => 1,
   'api_provider' => 'openai',
   'api_key' => 'sk-...',
   'approval_threshold' => 85
];

PluginQualityauditConfig::updateConfig($data);
```

### Deleting Configuration

```php
// Delete config for entity (reverts to inherited)
PluginQualityauditConfig::deleteConfig($entities_id);
```

---

## 💡 Advanced Scenarios

### Multi-Tenant SaaS Setup

**Scenario:** GLPi instance serves multiple independent companies.

**Architecture:**
```
Root Entity (0) - Admin fallback only
├── Client A (1)
│   ├── Client A - Dept 1 (3)
│   └── Client A - Dept 2 (4)
├── Client B (2)
│   └── Client B - Support (5)
└── Client C (6)
```

**Configuration:**
- Root Entity: Emergency fallback key (low usage limit)
- Client A: Client A's API key, `is_recursive = 1`
- Client B: Client B's API key, `is_recursive = 1`
- Client C: Client C's API key, `is_recursive = 1`

**Benefits:**
- Complete API key isolation
- Per-client billing (each uses own key)
- Centralized admin can monitor all

---

**Last Updated:** 2026-02-09  
**Version:** 1.1.0  
**Author:** Fernando Karl / Rehoboam AI
