# Bill Import Cron Scripts

Automated bill fetching system for Constituant.

## Quick Start

```bash
# 1. Test the system
php test-import.php

# 2. Run a manual import
php fetch-bills.php

# 3. Check the logs
tail -f ../logs/bill-imports.log

# 4. Review imported bills
# Visit: https://your-domain.com/admin/pending-bills.php
```

## Scripts

### `fetch-bills.php` (Main Script)
Main orchestration script that runs all enabled fetchers.

**Usage:**
```bash
php fetch-bills.php
```

**Cron setup (every 6 hours):**
```bash
0 */6 * * * /usr/local/php8.1/bin/php /path/to/cron/fetch-bills.php >> /path/to/logs/cron.log 2>&1
```

### `test-import.php` (Test Script)
Comprehensive test suite - safe to run multiple times.

**Usage:**
```bash
php test-import.php
```

**Tests:**
- Database connectivity
- Table existence
- Configuration
- File permissions
- API connectivity
- Date parsing
- Data processing

### Individual Source Fetchers

Can be run independently for testing:

```bash
# NosDéputés.fr (France - Priority 1)
php sources/nosdeputes.php

# EU Parliament (EU - Priority 3)
php sources/eu-parliament.php

# La Fabrique de la Loi (France - Priority 2)
php sources/lafabrique.php
```

## Directory Structure

```
cron/
├── fetch-bills.php          # Main orchestrator
├── test-import.php          # Test suite
├── README.md                # This file
├── lib/
│   └── fetcher-base.php     # Shared utilities and database functions
└── sources/
    ├── nosdeputes.php       # NosDéputés.fr fetcher
    ├── eu-parliament.php    # EU Parliament fetcher
    └── lafabrique.php       # La Fabrique de la Loi fetcher
```

## Configuration

Edit `../public_html/config/sources.php` to:
- Enable/disable sources
- Adjust rate limits
- Set max bills per run
- Configure notifications

## Logs

- **Application logs**: `../logs/bill-imports.log`
- **Cron logs**: As configured in crontab
- **Database logs**: `import_logs` table

## Troubleshooting

### Import fails
```bash
# Check logs
tail -50 ../logs/bill-imports.log

# Test individual source
php sources/nosdeputes.php

# Test system
php test-import.php
```

### No bills imported
1. Check if sources are enabled in config
2. Verify API connectivity: `curl https://www.nosdeputes.fr/dossiers/date/json`
3. Check database tables exist
4. Review error messages in logs

### Cron not running
```bash
# Check cron status
crontab -l

# Check cron logs
grep CRON /var/log/syslog
```

## Workflow

1. **Cron runs** → `fetch-bills.php`
2. **Fetches bills** from enabled sources
3. **Saves to** `pending_bills` table
4. **Admin reviews** at `/admin/pending-bills.php`
5. **Approved bills** copied to `bills` table
6. **Published** on main site

## Performance

- Average execution time: 30-60 seconds
- Bills fetched per run: 50-150 (configurable)
- Memory usage: < 50MB
- API requests: 10-30 per source

## Support

See `../BILL_IMPORT_SETUP.md` for detailed setup instructions.
