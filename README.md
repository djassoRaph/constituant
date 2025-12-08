# 🏛️ Constituant

**Votre voix sur les lois du jour** - A modern civic engagement platform that allows French and EU citizens to vote anonymously on real legislation, with AI-powered classification and automatic bill imports.

![Version](https://img.shields.io/badge/version-0.2.0-blue.svg)
![PHP](https://img.shields.io/badge/PHP-8.0%2B-blue.svg)
![License](https://img.shields.io/badge/license-AGPL--3.0-green.svg)
![AI](https://img.shields.io/badge/AI-Mistral-orange.svg)

## 📋 Table of Contents

- [Overview](#overview)
- [Features](#features)
- [Tech Stack](#tech-stack)
- [Quick Start](#quick-start)
- [Installation](#installation)
- [Configuration](#configuration)
- [AI Classification](#ai-classification)
- [Bill Import System](#bill-import-system)
- [Project Structure](#project-structure)
- [API Documentation](#api-documentation)
- [Frontend](#frontend)
- [Admin Panel](#admin-panel)
- [Security](#security)
- [Troubleshooting](#troubleshooting)
- [Contributing](#contributing)
- [License](#license)

## 🎯 Overview

Constituant is a comprehensive civic engagement platform that enables French and EU citizens to participate in democracy by:

- Viewing current and past legislative bills from official sources
- Casting anonymous votes (For, Against, Abstain) on legislation
- Seeing real-time aggregate voting results
- Filtering bills by AI-classified themes (Santé, Justice, Économie, etc.)
- Accessing AI-generated plain-language summaries
- Tracking bills from France and EU institutions

**NEW in v0.2.0:**
- 🤖 **AI-Powered Classification** using Mistral AI
- 📥 **Automatic Bill Imports** from NosDéputés, La Fabrique de la Loi
- 🎨 **DSFR-Inspired Design** (French Government Design System)
- 📱 **Tabbed Interface** with Active/Past votes
- 🏷️ **Theme-Based Filtering** with horizontal slider
- 🇫🇷 **France-First Ordering** in bill displays

## ✨ Features

### Core Features (v0.2.0)

#### Voting & Engagement
- ✅ Real-time voting on current legislation
- ✅ Anonymous IP-based tracking (one vote per bill)
- ✅ Live aggregate results with progress bars
- ✅ Vote history preservation
- ✅ Dual legislative tracking (France + EU)

#### AI-Powered Classification 🤖
- ✅ **Automatic theme classification** into 15 categories:
  - Économie & Finances
  - Travail & Emploi
  - Santé
  - Éducation
  - Justice
  - Sécurité & Défense
  - Environnement & Énergie
  - Transports & Infrastructures
  - Agriculture
  - Culture & Communication
  - Affaires sociales
  - Numérique
  - Affaires européennes
  - Institutions
  - Sans catégorie
- ✅ **Plain-language summaries** via Mistral AI
- ✅ Background AI processing during import

#### Automatic Bill Imports 📥
- ✅ **NosDéputés.fr** - Assemblée Nationale dossiers
- ✅ **La Fabrique de la Loi** - 1,500+ legislative files
- ✅ **EU Parliament** (optional) - European legislation
- ✅ Scheduled cron imports (configurable)
- ✅ Duplicate detection and smart updates
- ✅ AI classification during import

#### Modern Frontend 🎨
- ✅ **Tabbed interface**: "Lois en cours" / "Votes passés"
- ✅ **Theme slider**: Horizontal scrollable filter pills
- ✅ **France-first ordering**: National bills before EU
- ✅ **DSFR colors**: French Government design system
- ✅ **Mobile-first responsive**: 320px to 4K
- ✅ **Theme badges**: Color-coded by legislative category
- ✅ **Vote ended state**: Disabled buttons for past votes
- ✅ **Empty states**: Friendly messages when no bills match

#### Technical Features
- ✅ Progressive enhancement (works without JS)
- ✅ Client-side filtering (instant, no page reload)
- ✅ Accessibility (WCAG 2.1 AA, ARIA labels)
- ✅ Rate limiting (10 votes/hour per IP)
- ✅ SEO optimized (semantic HTML, meta tags)
- ✅ Performance: <2s load time on 3G
- ✅ Secure API key management

## 🛠️ Tech Stack

**Frontend:**
- Vanilla HTML5, CSS3, JavaScript (ES6+)
- DSFR-inspired design system
- Mobile-first responsive design
- No frameworks (pure vanilla code)

**Backend:**
- PHP 8.0+
- MySQL 5.7+ / MariaDB 10.2+
- RESTful APIs
- PDO prepared statements

**AI & Automation:**
- **Mistral AI** (mistral-small-latest) for classification
- Cron-based bill import system
- Background AI processing

**Server:**
- Apache 2.4+ (O2switch shared hosting optimized)
- mod_rewrite for clean URLs
- gzip compression, browser caching

## 🚀 Quick Start

```bash
# 1. Clone repository
git clone https://github.com/djassoRaph/constituant.git
cd constituant

# 2. Copy config templates
cp public_html/config/database.example.php public_html/config/database.php
cp public_html/config/api-keys.example.php public_html/config/api-keys.php

# 3. Edit configs (add your DB credentials and Mistral API key)
nano public_html/config/database.php
nano public_html/config/api-keys.php

# 4. Create database and run migrations
mysql -u username -p database_name < database/schema.sql
mysql -u username -p database_name < database/migrations/add_ai_classification_columns.sql
mysql -u username -p database_name < database/migrations/add_ai_classification_to_bills.sql

# 5. Import bills
php cron/fetch-bills.php

# 6. Visit http://localhost/constituant/public_html/
```

**See [SETUP.md](SETUP.md) for detailed setup instructions.**

## 📦 Installation

### Prerequisites

- PHP 8.0+ with PDO, cURL, JSON extensions
- MySQL 5.7+ or MariaDB 10.2+
- Apache with mod_rewrite
- **Mistral AI API key** (get from https://console.mistral.ai/)
- Cron access (for automatic imports)

### Detailed Steps

1. **Upload Files**
   ```bash
   # Upload public_html/ contents to your web root
   # For O2switch: /home/YOUR_USERNAME/public_html/
   ```

2. **Create Database**
   ```sql
   CREATE DATABASE constituant CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
   CREATE USER 'constituant_user'@'localhost' IDENTIFIED BY 'strong_password';
   GRANT ALL PRIVILEGES ON constituant.* TO 'constituant_user'@'localhost';
   FLUSH PRIVILEGES;
   ```

3. **Import Schema**
   ```bash
   mysql -u constituant_user -p constituant < database/schema.sql
   mysql -u constituant_user -p constituant < database/migrations/add_ai_classification_columns.sql
   mysql -u constituant_user -p constituant < database/migrations/add_ai_classification_to_bills.sql
   ```

4. **Configure Database** (`public_html/config/database.php`)
   ```php
   define('DB_HOST', 'localhost');
   define('DB_NAME', 'constituant');
   define('DB_USER', 'constituant_user');
   define('DB_PASS', 'your_password');
   ```

5. **Configure API Keys** (`public_html/config/api-keys.php`)
   ```php
   define('MISTRAL_API_KEY', 'your-mistral-api-key-here');
   ```

6. **Set Admin Password** (`public_html/config/config.php`)
   ```php
   define('ADMIN_PASSWORD', 'your_secure_password');
   ```

7. **Set Permissions**
   ```bash
   chmod 755 public_html/
   chmod 755 logs/
   chmod 644 public_html/config/*.php
   ```

8. **Test Installation**
   - Visit: `https://yourdomain.com`
   - Admin: `https://yourdomain.com/admin/`
   - Test vote on a bill

## ⚙️ Configuration

### API Keys (`public_html/config/api-keys.php`)

**⚠️ IMPORTANT:** This file is excluded from Git (.gitignore)

```php
// Mistral AI API Key (required for AI classification)
define('MISTRAL_API_KEY', 'your-mistral-api-key-here');
define('MISTRAL_MODEL', 'mistral-small-latest');
```

Get your API key: https://console.mistral.ai/

### Bill Sources (`public_html/config/sources.php`)

Enable/disable import sources:

```php
'lafabrique' => [
    'enabled' => true,  // 1,530 French bills available
    'priority' => 2,
],
'nosdeputes' => [
    'enabled' => true,  // ~200 Assemblée bills
    'priority' => 1,
],
'eu-parliament' => [
    'enabled' => false, // Disabled by default
    'priority' => 3,
],
```

### Import Settings

```php
'max_bills_per_source' => 50,      // Limit per import run
'auto_approve' => false,            // Require manual approval
'fetch_days_back' => 90,            // How far back to fetch
'timezone' => 'Europe/Paris',
```

### HTTPS Setup

Uncomment in `.htaccess`:
```apache
RewriteCond %{HTTPS} off
RewriteRule ^(.*)$ https://%{HTTP_HOST}%{REQUEST_URI} [L,R=301]
```

## 🤖 AI Classification

### How It Works

1. **During Import**:
   - Bill fetched from source (title, summary, full text URL)
   - Full text downloaded (HTML stripped, truncated to 50KB)
   - Sent to Mistral AI with classification prompt
   - Returns `theme` + `ai_summary`
   - Stored in database with `ai_processed_at` timestamp

2. **Classification Prompt**:
   ```
   Classify this French legislation into ONE category:
   [15 categories]

   Title: [bill title]
   Description: [summary]
   Full Text: [first 3000 chars]

   Return JSON: {"theme": "category", "summary": "2-3 sentence explanation"}
   ```

3. **Fallback**: If AI fails, defaults to `'Sans catégorie'` and continues import

### Re-Classify Existing Bills

```bash
# Re-classify 10 bills without AI data
php cron/reclassify-bills.php --limit=10

# Force re-classify ALL bills
php cron/reclassify-bills.php --limit=100 --force
```

### API Costs

**Mistral AI Pricing** (as of Dec 2024):
- Model: `mistral-small-latest`
- ~$0.0002 per bill classification
- 1,000 bills ≈ $0.20

## 📥 Bill Import System

### Automatic Imports

**Cron Setup** (add to crontab):
```bash
# Import bills twice daily at 6 AM and 6 PM
0 6,18 * * * /usr/bin/php /path/to/constituant/cron/fetch-bills.php

# Re-classify bills daily at 3 AM
0 3 * * * /usr/bin/php /path/to/constituant/cron/reclassify-bills.php --limit=50
```

### Manual Import

```bash
# Import from all enabled sources
php cron/fetch-bills.php

# Import from specific source
php cron/sources/lafabrique.php
php cron/sources/nosdeputes.php
```

### Import Stats

Check logs:
```bash
tail -100 logs/bill-imports.log
```

Check database:
```sql
SELECT source, COUNT(*) as count FROM pending_bills GROUP BY source;
```

### Data Sources

| Source | Type | Bills | Status |
|--------|------|-------|--------|
| **La Fabrique de la Loi** | CSV API | 1,530 | ✅ Working |
| **NosDéputés.fr** | JSON API | ~200 | ✅ Working |
| **EU Parliament** | LD+JSON | N/A | ⚠️ Disabled |

**Note:** EU Parliament disabled due to API returning low-level documents (amendments) instead of full bills.

## 📁 Project Structure

```
constituant/
├── README.md                           # This file
├── SETUP.md                            # Detailed setup guide
├── IMPORT_FIXES.md                     # Import troubleshooting
├── .gitignore                          # Git exclusions
├── database/
│   ├── schema.sql                      # Complete DB schema
│   └── migrations/
│       ├── add_ai_classification_columns.sql
│       └── add_ai_classification_to_bills.sql
├── cron/
│   ├── fetch-bills.php                 # Main import orchestrator
│   ├── reclassify-bills.php            # Re-run AI classification
│   ├── lib/
│   │   └── fetcher-base.php            # Shared import utilities
│   ├── sources/
│   │   ├── lafabrique.php              # La Fabrique fetcher
│   │   ├── nosdeputes.php              # NosDéputés fetcher
│   │   └── eu-parliament.php           # EU Parliament fetcher
│   └── test-*.php                      # Diagnostic scripts
├── examples/
│   └── test_mistral_classification.php # AI test script
├── logs/
│   └── bill-imports.log                # Import logs
└── public_html/
    ├── index.php                       # Main frontend
    ├── .htaccess                       # Apache config
    ├── admin/
    │   ├── index.php                   # Admin panel
    │   └── pending-bills.php           # Review imports
    ├── api/
    │   ├── get-votes.php               # GET bills with votes
    │   ├── cast-vote.php               # POST vote
    │   ├── get-results.php             # GET vote stats
    │   └── add-bill.php                # Admin: manage bills
    ├── assets/
    │   ├── css/
    │   │   ├── style.css               # DSFR-inspired styles
    │   │   └── mobile.css              # Responsive
    │   ├── js/
    │   │   ├── app.js                  # Main logic (tabs, themes)
    │   │   └── voting.js               # Voting functionality
    │   └── images/
    ├── config/
    │   ├── database.example.php        # DB config template
    │   ├── database.php                # Your DB credentials (gitignored)
    │   ├── api-keys.example.php        # API keys template
    │   ├── api-keys.php                # Your API keys (gitignored)
    │   ├── config.php                  # App settings
    │   └── sources.php                 # Import sources config
    └── includes/
        └── mistral_ai.php              # AI classification module
```

## 📡 API Documentation

### GET `/api/get-votes.php`

Get bills with vote statistics, themes, and AI summaries.

**Query Parameters:**
- `level` (optional): `eu`, `france`, or `all` (default: `all`)
- `status` (optional): `upcoming`, `voting_now`, `completed`

**Response:**
```json
{
  "success": true,
  "bills": [
    {
      "id": "bill-123",
      "title": "Loi de financement de la sécurité sociale pour 2025",
      "summary": "Original technical summary...",
      "ai_summary": "Cette loi organise le budget...",
      "theme": "Santé",
      "level": "france",
      "chamber": "Assemblée Nationale",
      "vote_datetime": "2024-12-15 14:00:00",
      "vote_datetime_formatted": "15 déc 2024, 14:00",
      "status": "upcoming",
      "urgency": {
        "is_soon": true,
        "label": "Vote aujourd'hui",
        "urgency": "urgent"
      },
      "votes": {
        "for": 234,
        "against": 45,
        "abstain": 21,
        "total": 300
      },
      "percentages": {
        "for": 78,
        "against": 15,
        "abstain": 7
      },
      "user_voted": "for"
    }
  ],
  "count": 1
}
```

### POST `/api/cast-vote.php`

Cast a vote on a bill (one per IP).

**Request:**
```json
{
  "bill_id": "bill-123",
  "vote_type": "for"
}
```

**Response:**
```json
{
  "success": true,
  "message": "Vote enregistré avec succès",
  "vote": {
    "bill_id": "bill-123",
    "vote_type": "for",
    "action": "created"
  }
}
```

**Error (Already Voted):**
```json
{
  "success": false,
  "error": "Vous avez déjà voté sur ce projet de loi"
}
```

### Full API Docs

See detailed API documentation at: `/docs/API.md`

## 🎨 Frontend

### Features

**Tabbed Interface:**
- "Lois en cours" - Active votes (vote_datetime >= NOW)
- "Votes passés" - Completed votes (vote_datetime < NOW)
- Client-side switching (no page reload)

**Theme Slider:**
- Horizontal scrollable pills
- Touch-friendly swipe on mobile
- Shows bill count per theme
- Instant filtering (CSS display:none)

**Bill Cards:**
- Theme badge (color-coded)
- Level badge (🇫🇷 France / 🇪🇺 UE)
- AI-generated summary (if available)
- Vote buttons (Pour/Contre/Abstention)
- Progress bars showing results

**DSFR Colors:**
- Primary: `#000091` (French gov blue)
- Success: `#18753C`
- Error: `#CE0500`
- Warning: `#FF9940`

**Ordering:**
- France bills always before EU bills
- Sorted by vote_datetime (soonest first)

### Accessibility

- WCAG 2.1 AA compliant
- ARIA labels on all interactive elements
- Keyboard navigation
- Focus-visible states
- High contrast colors
- Screen reader announcements

## 👨‍💼 Admin Panel

Access: `https://yourdomain.com/admin/`

**Default Password:** Change in `config/config.php`

### Features

**Pending Bills** (`/admin/pending-bills.php`):
- Review auto-imported bills
- View AI classifications
- Approve/reject imports
- Edit metadata before publishing

**Published Bills** (`/admin/index.php`):
- View all active bills
- See vote counts
- Edit/delete bills
- Manage vote dates

### Workflow

1. **Import** runs automatically (cron)
2. Bills go to `pending_bills` table with AI data
3. **Admin reviews** in `/admin/pending-bills.php`
4. **Approve** → moves to `bills` table (public)
5. **Reject** → deletes from pending

## 🔒 Security

### Implemented Measures

✅ **SQL Injection**: PDO prepared statements
✅ **XSS Prevention**: `htmlspecialchars()` on output
✅ **CSRF Protection**: Token validation
✅ **Rate Limiting**: 10 votes/hour per IP
✅ **Input Validation**: All user input sanitized
✅ **API Key Security**: Keys in separate config (gitignored)
✅ **Secure Headers**: X-Content-Type, X-Frame-Options
✅ **Password Protection**: Admin panel authentication
✅ **File Protection**: `.htaccess` blocks sensitive files

### Best Practices

- ⚠️ **Change default admin password immediately**
- ✅ Use HTTPS in production (Let's Encrypt)
- ✅ Keep PHP/MySQL updated
- ✅ Regular database backups
- ✅ Monitor access logs
- ✅ Never commit `api-keys.php` to Git
- ✅ Use strong database passwords

## 🐛 Troubleshooting

### No Bills Showing

```bash
# Check if bills imported
mysql -u user -p -e "SELECT COUNT(*) FROM constituant.bills;"

# Run import manually
php cron/fetch-bills.php

# Check logs
tail -100 logs/bill-imports.log
```

### AI Classification Fails

```bash
# Test Mistral API connection
php examples/test_mistral_classification.php

# Check API key
grep MISTRAL_API_KEY public_html/config/api-keys.php

# Re-classify manually
php cron/reclassify-bills.php --limit=5
```

### Import Errors

```bash
# Test individual sources
php cron/sources/lafabrique.php
php cron/sources/nosdeputes.php

# Check API responses
php cron/test-lafabrique-csv.php
php cron/test-nosdeputes-api.php
```

### Log File Not Writing

```bash
# Check directory exists and is writable
ls -la logs/
chmod 755 logs/

# Test logging
php cron/test-logging.php
```

## 🤝 Contributing

Contributions welcome! See [CONTRIBUTING.md](CONTRIBUTING.md)

**Quick Guidelines:**
- Fork repository
- Create feature branch: `git checkout -b feature/amazing-feature`
- Follow PSR-12 (PHP) and ES6+ (JavaScript)
- Test thoroughly
- Submit PR with clear description

## 📄 License

**GNU Affero General Public License v3.0 (AGPL-3.0)**

This means:
- ✅ Free to use, modify, and distribute
- ✅ Must make source code available if running on server
- ✅ Modifications must be open-source under AGPL-3.0
- ✅ Include original license and copyright

See [LICENSE](LICENSE) file for details.

## 🙏 Acknowledgments

- **Mistral AI** for classification API
- **NosDéputés.fr** for legislative data
- **La Fabrique de la Loi** for comprehensive bill database
- **DSFR** (Système de Design de l'État) for design inspiration
- **O2switch** for reliable hosting
- The open-source community

## 📞 Support

- **GitHub Issues**: https://github.com/djassoRaph/constituant/issues
- **Documentation**: See `/docs` and `SETUP.md`
- **Email**: contact@constituant.fr

## 🗓️ Roadmap

### v0.3 (Next Release)
- [ ] User accounts with vote history
- [ ] Email notifications for new bills
- [ ] Advanced analytics dashboard
- [ ] Social sharing features
- [ ] Better EU Parliament integration

### v0.4
- [ ] Mobile apps (iOS, Android)
- [ ] Real-time WebSocket notifications
- [ ] Multi-language support (EN, DE, ES)
- [ ] Community discussion forums
- [ ] Integration with official APIs

### Future
- [ ] AI debate summaries (pros/cons)
- [ ] Sentiment analysis on votes
- [ ] Representative comparison
- [ ] Bill prediction/trending
- [ ] Civic education resources

---

**Made with ❤️ for democracy and civic engagement**

*Version 0.2.0 - Last updated: 2024-12-08*
