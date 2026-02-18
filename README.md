# Constituant - Civic Engagement Platform

> Transform from passive voter to active participant in governance

[![License: AGPL v3](https://img.shields.io/badge/License-AGPL%20v3-blue.svg)](https://www.gnu.org/licenses/agpl-3.0)
[![Status](https://img.shields.io/badge/status-production-green.svg)](https://constituant.fr)

**Live**: [constituant.fr](https://constituant.fr)

## What is Constituant?

Constituant enables French and EU citizens to vote anonymously on real legislation from the European Parliament and the French National Assembly. The platform displays aggregate results in real-time, providing transparency by comparing citizen votes with actual parliamentary outcomes.

**The name**: In French, "constituant" means both "constituent" (a voter) and "constitution-maker" — reflecting the project's mission to transform citizens from passive observers to active participants in governance.

## Philosophy

Inspired by Étienne Chouard's work on direct democracy and sortition:
- **Transparency**: See what your representatives are voting on
- **Accountability**: Compare their votes with citizen preferences
- **Practice**: Train citizens in legislative deliberation
- **Evolution**: Path toward sortition-based governance

*"A weapon for the people against the opacity of political powers"*

## Features

✅ **Automated Bill Import** - Fetches legislation from Légifrance (PISTE API) and EU Parliament
✅ **AI Classification** - Mistral AI categorizes bills into themes and creates citizen-friendly summaries
✅ **Anonymous Voting** - Vote For/Against/Abstain on real legislation
✅ **Real-time Results** - See how other citizens voted, compare with representatives
✅ **Theme Filtering** - Browse by category: Economy, Health, Environment, Justice, etc.
✅ **Mobile-First** - Responsive design optimized for all devices

## Tech Stack

- **Backend**: PHP 8.x + MySQL 8.0+
- **AI**: Mistral AI (`mistral-small-latest`) for classification & summarization
- **Hosting**: o2switch shared hosting (Apache)
- **Frontend**: Vanilla HTML/CSS/JavaScript (no frameworks)
- **Automation**: Cron jobs every 6 hours for bill fetching & classification

**Why Traditional Stack?**
We deliberately chose PHP/MySQL over modern frameworks for:
- Simplicity (no technical barriers for contributors)
- Privacy (GDPR compliant, no immutable records)
- Cost (minimal AI costs with free tier)
- Deployment (optimized for affordable shared hosting)

## Quick Start

### Requirements
- PHP 8.0+
- MySQL 8.0+
- Web server (Apache with mod_rewrite)
- Mistral API key — [console.mistral.ai](https://console.mistral.ai)
- PISTE API credentials — [piste.gouv.fr](https://piste.gouv.fr) (for Légifrance)

### Installation

1. **Clone repository**
```bash
git clone https://github.com/djassoRaph/constituant.git
cd constituant
```

2. **Configure the application**
```bash
# Copy example configs (never commit the real ones)
cp cron/lib/config/database.example.php cron/lib/config/database.php
cp cron/lib/config/config.example.php   cron/lib/config/config.php
cp cron/lib/config/api-keys.example.php cron/lib/config/api-keys.php

# Edit each file with your credentials
nano cron/lib/config/database.php   # DB_HOST, DB_NAME, DB_USER, DB_PASS
nano cron/lib/config/api-keys.php   # MISTRAL_API_KEY, PISTE_CLIENT_ID, PISTE_CLIENT_SECRET
```

3. **Set up the database**
```bash
mysql -u root -p < database/schema.sql
```

4. **Run initial import**
```bash
# Fetches ~140 bills from Légifrance + EU Parliament, takes ~6 min (AI processing)
php cron/fetch-bills.php
```

5. **Set up cron jobs** (via cPanel or crontab)
```cron
# Fetch bills every 6 hours
0 */6 * * * /usr/bin/php /path/to/cron/fetch-bills.php >> /path/to/logs/cron.log 2>&1
```

6. **Access the platform**
- Frontend: `http://localhost/`

**⚠️ SECURITY**: Change `ADMIN_PASSWORD` in `config.php` immediately after setup!

## Project Structure

```
constituant/
├── public_html/             # Web root (served by Apache)
│   ├── index.php           # Main voting interface
│   ├── .htaccess           # Apache config: security headers, HTTPS, caching
│   └── api/                # Public API endpoints
│       ├── cast-vote.php   # POST: submit a vote
│       ├── get-votes.php   # GET:  list bills with vote stats
│       ├── get-results.php # GET:  vote stats for a specific bill
│       └── add-bill.php    # POST: add bill (admin/automated)
├── cron/                    # Automation & bill processing
│   ├── fetch-bills.php     # Main orchestrator (runs every 6h)
│   ├── reclassify-bills.php# Re-run AI classification on existing bills
│   ├── lib/
│   │   ├── fetcher-base.php     # Shared utilities & logging
│   │   ├── mistral_ai.php       # Mistral AI integration
│   │   ├── piste-api.php        # PISTE OAuth2 + Légifrance API wrapper
│   │   └── config/              # All configuration lives here (gitignored)
│   │       ├── database.php     # DB credentials
│   │       ├── api-keys.php     # Mistral + PISTE API keys
│   │       ├── config.php       # App settings, rate limits, session config
│   │       └── sources.php      # Data source toggles
│   ├── sources/             # Per-source bill fetchers
│   │   ├── legifrance.php  # Légifrance via PISTE (PRIMARY — France)
│   │   ├── eu-parliament.php    # EU Parliament (PRIMARY — EU)
│   │   ├── nosdeputes.php  # Legacy (replaced by Légifrance)
│   │   └── lafabrique.php  # Legacy
│   └── test/               # Manual test scripts
├── database/
│   └── schema.sql          # Full schema: tables, triggers, events, procedure
├── logs/                   # Application logs (gitignored)
└── Documentation/          # Project documentation
```

## Architecture

### Automated Workflow
```
External APIs (Légifrance/PISTE, EU Parliament)
    ↓
cron/fetch-bills.php  (every 6 hours)
    ↓
Mistral AI classification (theme + citizen summary)
    ↓
MySQL (bills table)
    ↓
REST API (public_html/api/)
    ↓
Frontend → Citizen votes in real time
```

### Database Schema (Simplified)
```sql
-- Bills with AI classification
bills (
    id VARCHAR(100) PRIMARY KEY,  -- e.g. "legifrance-JORFDOLE000050646591-2024"
    title, summary, ai_summary,
    theme, ai_confidence,
    level ENUM('france','eu'), chamber,
    vote_datetime, status ENUM('upcoming','voting_now','completed'),
    votes_for, votes_against, votes_abstain, votes_total  -- auto-updated by triggers
)

-- Anonymous votes (IP-based)
votes (
    bill_id, vote_type ENUM('for','against','abstain'),
    voter_ip,  -- hashed for GDPR in future
    voted_at
    -- UNIQUE(bill_id, voter_ip): one vote per bill per IP, changes allowed
)
```

### Status Transitions
MySQL event `auto_update_bill_statuses` runs hourly and calls stored procedure `update_bill_statuses()` to transition: `upcoming → voting_now → completed` based on `vote_datetime`.

## Data Sources

| Source | Status | Coverage |
|--------|--------|----------|
| **Légifrance (PISTE API)** | ✅ Primary | French parliament bills (legislature 17) |
| **EU Parliament API** | ✅ Active | European Parliament legislation |
| NosDéputés.fr | ⚠️ Legacy | Replaced by Légifrance |
| La Fabrique de la Loi | ⚠️ Legacy | French legislative history |
| DILA CSV | ❌ Disabled | Unreliable column format |

## AI Classification

**Model**: `mistral-small-latest`
**Input**: Bill title + description
**Output**: theme, 20-word abstract, 2–3 sentence summary, pro/con arguments, affected groups
**Confidence**: ~95% typical

**Themes**: Affaires sociales, Économie, Environnement & Énergie, Justice, Numérique, Santé, Éducation, Défense, Culture, Agriculture, Transports, Logement, Institutions, International, Libertés publiques

## API Reference

### `GET /api/get-votes.php`
Returns bills with voting statistics and the current user's vote.

| Param | Type | Default | Description |
|-------|------|---------|-------------|
| `level` | string | `all` | Filter: `france`, `eu`, `all` |
| `status` | string | — | Filter: `upcoming`, `voting_now`, `completed` |

### `POST /api/cast-vote.php`
Submit or change a vote. Body: `{"bill_id": "...", "vote_type": "for|against|abstain"}`
Rate limit: 10 votes/hour per IP. One vote per bill per IP (changes allowed).

### `GET /api/get-results.php`
Vote statistics for a specific bill. Param: `bill_id` (required).

## Contributing

Contributions welcome! This is a civic project built by citizens, for citizens.

**Before contributing**:
1. Read the philosophy (see `PHILOSOPHY.md`)
2. Check existing issues/PRs
3. Discuss major changes first

**Development principles**:
- Use PDO prepared statements for all SQL — never concatenate user input
- Sanitize all output with `htmlspecialchars()`
- Follow existing patterns and keep changes focused
- Test locally before pushing
- Config files are gitignored — never commit real credentials

See [CONTRIBUTING.md](CONTRIBUTING.md) for detailed guidelines.

## Roadmap

### Phase 1: Transparency (CURRENT)
✅ Automated bill import (Légifrance + EU Parliament)
✅ AI-powered citizen summaries
✅ Anonymous voting interface
✅ Real-time results

### Phase 2: Accountability (Years 2–3)
- Representative scorecards (alignment tracking)
- Petition thresholds (trigger responses)
- Citizen amendment proposals
- Impact tracking

### Phase 3: Transformation (Year 3+)
- Constituent assembly simulator
- Sortition experiments (random citizen panels)
- Alternative legislation drafting
- Deliberation training workshops

## License

**AGPL-3.0** — This project must remain open source forever. Any modifications must be shared publicly.

Why AGPL? To prevent corporate acquisition, closed-source forks, or weaponization by bad actors.
The code is a public good, owned by citizens, for citizens.

## Legal Structure

**Planned**: Association loi 1901 (French non-profit)

## Support

- **Issues**: [GitHub Issues](https://github.com/djassoRaph/constituant/issues)
- **Discussions**: [GitHub Discussions](https://github.com/djassoRaph/constituant/discussions)
- **Email**: contact@constituant.fr

## Acknowledgments

- **Étienne Chouard** — Philosophical foundation on sortition and direct democracy
- **Légifrance / DILA / PISTE** — Official French legislative data
- **European Parliament** — EU legislative data
- **Mistral AI** — Bill classification and summarization

---

**"We are all constituants"** — capable of governance when given proper information and deliberation space.

*Project initiated: December 2024*
*Current status: Production deployment with full automation*
