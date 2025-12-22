# Constituant - AI Assistant Context Guide

> **Quick Reference for Claude Code and AI Assistants**
> This document provides essential context about the Constituant project for AI-powered development assistance.

---

## 🎯 Project Overview

**Constituant** is a French civic engagement platform that enables citizens to explore, understand, and vote on legislative bills from both France and the European Union.

**Current Version:** 0.2.0
**Technology Stack:** PHP 8.1+, MySQL/MariaDB, Vanilla JavaScript, CSS3
**Design System:** DSFR-inspired (French Government Design System)

---

## 🏗️ Architecture at a Glance

```
/constituant/
├── public_html/           # Frontend & API
│   ├── index.php         # Main page with tabbed interface
│   ├── api/              # RESTful API endpoints
│   │   └── get-votes.php # Fetch bills (with theme & ai_summary)
│   ├── assets/
│   │   ├── js/app.js     # Client-side filtering & tabs (698 lines)
│   │   └── css/style.css # DSFR-inspired styling
│   ├── config/           # Configuration files
│   │   ├── *.example.php # Templates (safe to commit)
│   │   ├── api-keys.php  # Actual API keys (gitignored)
│   │   ├── database.php  # DB credentials (gitignored)
│   │   └── sources.php   # Bill import source config
│   └── includes/
│       └── mistral_ai.php # AI classification module
├── cron/                  # Automated import scripts
│   ├── fetch-bills.php   # Main import orchestrator
│   ├── reclassify-bills.php # Batch AI re-classification
│   ├── sources/          # Individual source importers
│   │   ├── lafabrique.php     # La Fabrique (CSV, 1530 bills)
│   │   ├── nosdeputes.php     # NosDéputés.fr (JSON API)
│   │   └── eu-parliament.php  # EU Parliament (disabled)
│   ├── lib/
│   │   └── fetcher-base.php # Shared utilities (logging, HTTP, DB)
│   └── test-*.php        # Diagnostic scripts
├── database/
│   └── migrations/       # SQL schema updates
├── logs/                 # Import & error logs (gitignored)
└── docs/                 # Additional documentation
```

---

## 🔑 Key Features (v0.2.0)

### 1. AI-Powered Classification
- **Mistral AI** integration (`mistral-small-latest`)
- Automatic theme classification into 15 categories
- Plain-language summaries in French
- API key stored in `public_html/config/api-keys.php` (gitignored)

### 2. Bill Import System
- **Cron-based automation** (runs twice daily: 6 AM, 6 PM)
- **Three sources:**
  - ✅ La Fabrique de la Loi (CSV, 1530 bills, France)
  - ✅ NosDéputés.fr (JSON API, ~200 bills, France)
  - ❌ EU Parliament (disabled - returns amendments, not bills)
- **Workflow:** External source → `pending_bills` → Admin review → `bills`

### 3. Frontend (DSFR-Inspired)
- **Tabbed interface:** "Lois en cours" (active) | "Votes passés" (past)
- **Theme filtering:** Horizontal slider with 15 legislative categories
- **France-first ordering:** French bills always displayed before EU bills
- **Client-side filtering:** No page reloads, instant updates
- **Responsive design:** Mobile-first with DSFR colors (#000091 primary)

### 4. Security
- **API keys separated:** Never committed to Git
- **Example templates:** `*.example.php` files in version control
- **PDO prepared statements:** SQL injection prevention
- **Rate limiting:** 10 votes per hour per IP

---

## 📊 Database Schema

### Core Tables

#### `bills` (Published Bills)
```sql
- id (PK)
- title, summary, full_text_url
- level (france/eu), chamber, theme
- ai_summary, ai_processed_at  -- Added in v0.2.0
- vote_datetime, status (upcoming/voting_now/completed)
- votes_for, votes_against, votes_total
- created_at, updated_at
```

#### `pending_bills` (Import Queue)
```sql
- id (PK)
- external_id, source (lafabrique/nosdeputes/eu-parliament)
- title, summary, full_text_url
- level, chamber, theme
- ai_summary, ai_processed_at  -- Added in v0.2.0
- vote_datetime, raw_data (JSON)
- status (pending/approved/rejected)
- fetched_at, reviewed_at
```

#### `votes` (User Votes)
```sql
- id (PK), bill_id (FK)
- user_id, vote (for/against)
- ip_address, user_agent
- voted_at
```

#### `import_logs` (Import Audit Trail)
```sql
- id (PK), source, status
- bills_fetched, bills_new, bills_updated
- errors (JSON), execution_time
- completed_at
```

---

## 🛠️ Common Development Tasks

### Running Imports
```bash
# Test individual sources
php cron/sources/lafabrique.php
php cron/sources/nosdeputes.php

# Full import (all enabled sources)
php cron/fetch-bills.php

# Re-classify existing bills with AI
php cron/reclassify-bills.php --limit=50

# Check logs
tail -100 logs/bill-imports.log
```

### Testing
```bash
# Diagnostic scripts
php cron/test-logging.php
php cron/test-lafabrique-csv.php
php cron/test-nosdeputes-api.php

# Database checks
mysql -u user -p rera8347_constituant -e "SELECT COUNT(*), source FROM pending_bills GROUP BY source;"
```

### Frontend Development
```javascript
// Main JavaScript: public_html/assets/js/app.js
// Key functions:
- loadBills()           // Fetch bills from API
- sortBillsByLevel()    // France-first ordering
- filterByTab()         // Active vs past votes
- filterByTheme()       // Theme-based filtering
- renderBills()         // DOM updates
```

---

## 🔧 Configuration Files

| File | Purpose | Committed? |
|------|---------|------------|
| `database.example.php` | Template for DB credentials | ✅ Yes |
| `database.php` | **Actual DB credentials** | ❌ No (.gitignore) |
| `api-keys.example.php` | Template for API keys | ✅ Yes |
| `api-keys.php` | **Actual Mistral API key** | ❌ No (.gitignore) |
| `config.example.php` | Site config template | ✅ Yes |
| `config.php` | Site settings | ❌ No (.gitignore) |
| `sources.php` | Bill sources config | ✅ Yes |

**⚠️ CRITICAL:** Never commit `api-keys.php` or `database.php` to version control!

---

## 🐛 Known Issues & Troubleshooting

### 1. Mistral AI HTTP 422 Errors
- **Symptom:** `{"detail":[{"type":"missing","loc":["body"],"msg":"Field required"}]}`
- **Impact:** Non-critical - bills default to "Sans catégorie"
- **Status:** Under investigation (see logs/bill-imports.log)

### 2. La Fabrique CSV Parsing
- **Issue:** CSV uses French column names with capital letters (`Titre` not `titre`)
- **Fix Applied:** Updated column mappings in `cron/sources/lafabrique.php:71`
- **Status:** ✅ Fixed in v0.2.0

### 3. NosDéputés API Structure Change
- **Issue:** API changed from `dossiers_legislatif` to nested `sections` array
- **Fix Applied:** Extract dossiers from `section` objects (lafabrique.php:47-56)
- **Status:** ✅ Fixed in v0.2.0

### 4. EU Parliament Source
- **Issue:** API returns low-level documents (amendments) not full bills
- **Workaround:** Source disabled in `public_html/config/sources.php:57`
- **Status:** ⏳ Need alternative endpoint

### 5. Log Path Issues
- **Issue:** Logs writing outside project directory (`../logs/`)
- **Fix Applied:** Changed to `logs/bill-imports.log` (sources.php:106)
- **Status:** ✅ Fixed in v0.2.0

---

## 📡 API Endpoints

### GET `/api/get-votes.php`
**Purpose:** Fetch bills for display

**Parameters:**
- `level` (optional): `france`, `eu`, or `all` (default)
- `status` (optional): `upcoming`, `voting_now`, `completed`, or `all` (default)
- `theme` (optional): Filter by legislative category

**Response:**
```json
{
  "success": true,
  "bills": [
    {
      "id": 123,
      "title": "Projet de loi...",
      "summary": "...",
      "theme": "Économie & Finances",
      "ai_summary": "Cette loi vise à...",
      "level": "france",
      "chamber": "Assemblée Nationale",
      "vote_datetime": "2024-03-15 14:00:00",
      "status": "upcoming",
      "votes_for": 245,
      "votes_against": 189,
      "votes_total": 434
    }
  ],
  "count": 1
}
```

---

## 🎨 DSFR Color Palette

```css
--primary-blue: #000091;     /* French government blue */
--primary-red: #E1000F;      /* French flag red */
--secondary-blue: #6A6AF4;   /* Lighter accent */
--success-green: #18753C;
--warning-yellow: #FFD700;
--error-red: #CE0500;
--neutral-grey: #666;
--light-grey: #F6F6F6;
```

---

## 🚀 Deployment Checklist

Before deploying to production:

- [ ] Copy `*.example.php` files to actual config files
- [ ] Update `database.php` with production credentials
- [ ] Add Mistral API key to `api-keys.php`
- [ ] Run database migrations (see `database/migrations/`)
- [ ] Set file permissions: `chmod 755 logs/`
- [ ] Configure cron jobs (see SETUP.md)
- [ ] Verify `.gitignore` excludes sensitive files
- [ ] Test all three bill import sources
- [ ] Check logs for errors: `tail -100 logs/bill-imports.log`

---

## 📚 Related Documentation

- **SETUP.md** - Complete installation guide with security instructions
- **README.md** - User-facing project documentation
- **IMPORT_FIXES.md** - Technical details of import source fixes
- **database/migrations/** - SQL schema updates

---

## 🧠 AI Classification Details

### Mistral AI Integration
- **File:** `public_html/includes/mistral_ai.php`
- **Model:** `mistral-small-latest`
- **Endpoint:** `https://api.mistral.ai/v1/chat/completions`
- **Timeout:** 30 seconds
- **Temperature:** 0.3 (low randomness for consistency)
- **Max Tokens:** 500

### 15 Legislative Categories
1. Économie & Finances
2. Travail & Emploi
3. Santé
4. Éducation
5. Justice
6. Sécurité & Défense
7. Environnement & Énergie
8. Transports & Infrastructures
9. Agriculture
10. Culture & Communication
11. Affaires sociales
12. Numérique
13. Affaires européennes
14. Institutions
15. Sans catégorie (fallback)

### Prompt Structure
```
Classify this French legislation into ONE category and provide a brief summary.

Categories: [15 categories]

Title: {bill title}
Description: {bill description}
Full Text: {first 3000 chars}

Return ONLY valid JSON: {"theme": "category name", "summary": "plain French explanation in 2-3 sentences"}
```

---

## 🔄 Typical Import Workflow

1. **Cron triggers:** `/cron/fetch-bills.php` (6 AM & 6 PM daily)
2. **Fetch from sources:** LaFabrique (CSV) + NosDéputés (JSON)
3. **Parse & validate:** Extract bill data, check required fields
4. **AI classification:** Call Mistral API for theme & summary
5. **Insert to `pending_bills`:** Status = 'pending'
6. **Admin review:** Manual approval in admin panel
7. **Publish to `bills`:** Approved bills become votable
8. **Frontend display:** API serves bills to client-side JS

---

## 💡 Quick Tips for AI Assistants

1. **Configuration Files:** Always check `*.example.php` before modifying config
2. **Database Changes:** Add migrations to `database/migrations/`, don't edit schema.sql directly
3. **Import Sources:** Each source has unique data structure - always run test scripts first
4. **Logging:** Use `logMessage()` from `cron/lib/fetcher-base.php` for consistent logging
5. **Security:** Never hardcode API keys or credentials - use config files
6. **Frontend:** App.js uses vanilla JS - no frameworks (keep it simple)
7. **Testing:** Run diagnostic scripts in `cron/test-*.php` before making changes

---

## 📞 Support & Resources

- **GitHub Issues:** https://github.com/djassoRaph/constituant/issues
- **Mistral AI Docs:** https://docs.mistral.ai/
- **NosDéputés API:** https://www.nosdeputes.fr/api
- **La Fabrique API:** https://www.lafabriquedelaloi.fr/api/

---

**Last Updated:** 2025-12-08
**Document Version:** 1.0.0
**For:** Claude Code, GitHub Copilot, and other AI development assistants
