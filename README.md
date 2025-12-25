# Constituant - Civic Engagement Platform

> Transform from passive voter to active participant in governance

[![License: AGPL v3](https://img.shields.io/badge/License-AGPL%20v3-blue.svg)](https://www.gnu.org/licenses/agpl-3.0)
[![Status](https://img.shields.io/badge/status-production-green.svg)](https://constituant.fr)

**Live**: [constituant.fr](https://constituant.fr)

## What is Constituant?

Constituant enables French and EU citizens to vote anonymously on real legislation from the European Parliament and French National Assembly. The platform displays aggregate results in real-time, providing transparency by comparing citizen votes with actual parliamentary outcomes.

**The name**: In French, "constituant" means both "constituent" (a voter) and "constitution-maker" — reflecting the project's mission to transform citizens from passive observers to active participants in governance.

## Philosophy

Inspired by Étienne Chouard's work on direct democracy and sortition:
- **Transparency**: See what your representatives are voting on
- **Accountability**: Compare their votes with citizen preferences  
- **Practice**: Train citizens in legislative deliberation
- **Evolution**: Path toward sortition-based governance

*"A weapon for the people against the opacity of political powers"*

## Features

✅ **Automated Bill Import** - Fetches legislation from NosDéputés.fr, EU Parliament, La Fabrique de la Loi  
✅ **AI Classification** - Mistral AI categorizes bills into themes and creates citizen-friendly summaries  
✅ **Anonymous Voting** - Vote For/Against/Abstain on real legislation  
✅ **Real-time Results** - See how other citizens voted, compare with representatives  
✅ **Theme Filtering** - Browse by category: Economy, Health, Environment, Justice, etc.  
✅ **Mobile-First** - Responsive design optimized for all devices  

## Tech Stack

- **Backend**: PHP 8.x + MySQL
- **AI**: Mistral AI (free tier) for classification & summarization
- **Hosting**: o2switch shared hosting
- **Frontend**: Vanilla HTML/CSS/JavaScript (progressive enhancement)
- **Automation**: Cron jobs for bill fetching & classification

**Why Traditional Stack?**  
We deliberately chose PHP/MySQL over blockchain/modern frameworks for:
- Simplicity (no technical barriers for contributors)
- Privacy (GDPR compliant, no immutable records)
- Cost (zero AI costs with free tier)
- Deployment (optimized for affordable shared hosting)

## Quick Start

### Requirements
- PHP 8.0+
- MySQL 8.0+
- Web server (Apache/Nginx)
- Mistral API key (free tier: https://console.mistral.ai)

### Installation

1. **Clone repository**
```bash
git clone https://github.com/djassoRaph/constituant.git
cd constituant
```

2. **Configure database**
```bash
# Import schema
mysql -u root -p < database/schema.sql

# Edit config
nano config/database.php
# Set: DB_HOST, DB_NAME, DB_USER, DB_PASS
```

3. **Configure Mistral AI**
```bash
nano config/sources.php
# Set: MISTRAL_API_KEY (get free key from console.mistral.ai)
```

4. **Test the system**
```bash
php cron/test-import.php
```

5. **Run initial import**
```bash
php cron/fetch-bills.php
```

6. **Set up cron jobs**
```cron
# Fetch bills every 6 hours
0 */6 * * * /usr/bin/php /path/to/cron/fetch-bills.php >> /path/to/logs/cron.log 2>&1
```

7. **Access the platform**
- Frontend: `http://localhost/`
- Admin: `http://localhost/admin/` (default: admin/changeme)

**⚠️ SECURITY**: Change the default admin password immediately!

## Project Structure

```
constituant/
├── public/              # Frontend (entry point)
│   ├── index.php       # Main voting interface
│   ├── css/           # Styles
│   └── js/            # Client-side scripts
├── admin/              # Admin panel
│   ├── index.php      # Dashboard
│   └── pending-bills/ # Bill management
├── api/                # REST endpoints
│   ├── get-votes.php
│   ├── cast-vote.php
│   └── get-results.php
├── cron/               # Automation scripts
│   ├── fetch-bills.php        # Main orchestrator
│   ├── reclassify-bills.php   # Mistral AI classifier
│   ├── lib/fetcher-base.php   # Shared functions
│   └── sources/               # API fetchers
├── config/             # Configuration
│   ├── database.php   # DB credentials
│   └── sources.php    # API keys + Mistral config
├── database/           # Database files
│   ├── schema.sql     # Full schema
│   └── migrations/    # Version history
└── logs/              # Application logs
```

## Architecture

### Automated Workflow
```
1. Cron triggers fetch-bills.php (every 6 hours)
2. Fetchers pull from NosDéputés, EU Parliament, La Fabrique
3. Bills saved to database
4. Mistral AI classifies (theme + citizen summary)
5. Published automatically (no manual approval)
6. Citizens vote via frontend
7. Results aggregated in real-time
```

### Database Schema (Simplified)
```sql
-- Bills with AI classification
bills (
    id, title, summary, full_text_url,
    level, chamber, theme,
    ai_summary, ai_processed_at,  -- Mistral outputs
    vote_datetime, status,
    yes_count, no_count, abstain_count  -- Auto-updated
)

-- Anonymous votes (IP-based for MVP)
votes (
    bill_id, vote_type, voter_ip, voted_at
    -- UNIQUE(bill_id, voter_ip) prevents double voting
)
```

## AI Classification

**Mistral AI Integration** (FREE tier):
- Categorizes bills into 15 themes (Economy, Health, Environment, etc.)
- Generates plain-language summaries (2-3 sentences)
- Confidence scores typically ~95%
- Zero cost with free tier

**Themes**:
Affaires sociales, Économie, Environnement & Énergie, Justice, Numérique, Santé, Éducation, Défense, Culture, Agriculture, Transports, Logement, Institutions, International, Libertés publiques

## Contributing

Contributions welcome! This is a civic project built by citizens, for citizens.

**Before contributing**:
1. Read the philosophy (see PHILOSOPHY.md)
2. Check existing issues/PRs
3. Discuss major changes first

**Development principles**:
- Build on existing work, never recreate from scratch
- Backend functionality before frontend polish
- Test thoroughly (we're dealing with democratic data)
- Document your changes

See [CONTRIBUTING.md](CONTRIBUTING.md) for detailed guidelines.

## Roadmap

### Phase 1: Transparency (CURRENT - MVP)
✅ Legislative digests  
✅ Simple voting interface  
✅ Real-time results  
✅ AI-powered summaries  

### Phase 2: Accountability (Years 2-3)
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

**AGPL-3.0** - This project must remain open source forever. Any modifications must be shared publicly.

Why AGPL? To prevent:
- Corporate acquisition/privatization
- Closed-source forks
- Weaponization by bad actors

The code is a public good, owned by citizens, for citizens.

## Legal Structure

**Planned**: Association loi 1901 (French non-profit)

**Two-tier membership**:
- **Basic**: Anonymous viewing + indicative voting (open to all)
- **Full**: Identity-verified members with weighted voting rights (association members)

## Support

- **Issues**: [GitHub Issues](https://github.com/djassoRaph/constituant/issues)
- **Discussions**: [GitHub Discussions](https://github.com/djassoRaph/constituant/discussions)
- **Email**: contact@constituant.fr (coming soon)

## Acknowledgments

- **Étienne Chouard** - Philosophical foundation on sortition and direct democracy
- **NosDéputés.fr** - French legislative data
- **European Parliament** - EU legislative data
- **La Fabrique de la Loi** - Legislative history
- **Mistral AI** - Free tier for bill classification

---

**"We are all constituants"** - capable of governance when given proper information and deliberation space.

*Project initiated: December 2024*  
*Current status: Production deployment with full automation*
