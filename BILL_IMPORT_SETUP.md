# Automatic Bill Import System - Setup Guide

This guide will help you set up and configure the automatic legislative bill import system for Constituant.

## Overview

The system automatically fetches bills from:
- **NosDéputés.fr** (France) - Priority 1
- **La Fabrique de la Loi** (France) - Priority 2
- **EU Parliament Open Data API** (EU) - Priority 3

Bills are imported into a `pending_bills` table for admin review before being published.

---

## 1. Database Setup

### Run the migration to create tables

```bash
mysql -u constituant_user -p constituant < database/migrations/001_add_pending_bills.sql
```

This creates two tables:
- `pending_bills` - Stores fetched bills awaiting approval
- `import_logs` - Tracks import operations

### Verify tables were created

```sql
SHOW TABLES LIKE '%pending%';
SELECT COUNT(*) FROM pending_bills;
SELECT COUNT(*) FROM import_logs;
```

---

## 2. Configuration

### Update source settings (optional)

Edit `public_html/config/sources.php` to:
- Enable/disable specific sources
- Adjust rate limits
- Change fetch parameters
- Configure email notifications

Example:
```php
const IMPORT_SETTINGS = [
    'fetch_days_back' => 90,           // How far back to fetch
    'max_bills_per_source' => 50,      // Max bills per run
    'auto_approve' => false,           // Set true to skip review
    'notify_admin' => true,            // Email notifications
    'admin_email' => 'your@email.com', // Your email
];
```

---

## 3. Test the Import System

### Test individual sources

```bash
# Test NosDéputés.fr (recommended to test first)
php cron/sources/nosdeputes.php

# Test EU Parliament
php cron/sources/eu-parliament.php

# Test La Fabrique de la Loi
php cron/sources/lafabrique.php
```

### Test the full import process

```bash
php cron/fetch-bills.php
```

Expected output:
```
================================================================================
Constituant - Automatic Bill Import
Started: 2024-12-06 14:30:00
================================================================================

Enabled sources: 3

--------------------------------------------------------------------------------
Source: NosDéputés.fr (Priority: 1)
--------------------------------------------------------------------------------
✓ Status: SUCCESS
  New: 12, Updated: 3, Skipped: 5

... (other sources)

================================================================================
Import Summary
================================================================================
Sources Run: 3
Success: 3
Failed: 0
Total New Bills: 25
Total Updated: 5
Total Errors: 0
Execution Time: 45.23s
```

---

## 4. Review Imported Bills

### Access the admin panel

1. Go to: `https://your-domain.com/admin/`
2. Login with admin credentials
3. Click on **"Projets de loi en attente"** or go to `/admin/pending-bills.php`

### Review workflow

For each pending bill, you can:

1. **✓ Approuver** - Instantly publish the bill (copies to `bills` table)
2. **✏ Modifier puis approuver** - Edit fields before publishing
3. **✗ Rejeter** - Mark as rejected (won't be shown again)
4. **🔍 Données brutes** - View original API response (for debugging)

### What to check before approving

- ✅ Title is clear and readable
- ✅ Summary is accurate and informative
- ✅ Vote date is correct
- ✅ Full text URL works
- ✅ Chamber is correctly identified

---

## 5. Set Up Cron Job

### For o2switch hosting

Access your cron job manager in cPanel or add via SSH:

```bash
# Edit crontab
crontab -e

# Add this line (runs every 6 hours)
0 */6 * * * /usr/local/php8.1/bin/php /home/your-username/constituant/cron/fetch-bills.php >> /home/your-username/logs/fetch-bills.log 2>&1
```

### Cron schedule options

```bash
# Every 6 hours (recommended)
0 */6 * * *

# Every day at 2 AM
0 2 * * *

# Twice a day (9 AM and 9 PM)
0 9,21 * * *

# Every 3 hours
0 */3 * * *
```

### Verify cron is running

Check the log file:
```bash
tail -f logs/bill-imports.log
```

---

## 6. Monitoring & Maintenance

### Check import logs

```sql
-- Recent imports
SELECT * FROM import_logs
ORDER BY started_at DESC
LIMIT 10;

-- Failed imports
SELECT * FROM import_logs
WHERE status = 'failed'
ORDER BY started_at DESC;

-- Statistics by source
SELECT
    source,
    COUNT(*) as runs,
    AVG(execution_time) as avg_time,
    SUM(bills_new) as total_new
FROM import_logs
GROUP BY source;
```

### Check pending bills

```sql
-- Pending bills by source
SELECT source, COUNT(*) as count
FROM pending_bills
WHERE status = 'pending'
GROUP BY source;

-- Old pending bills (review needed)
SELECT title, fetched_at, DATEDIFF(NOW(), fetched_at) as days_old
FROM pending_bills
WHERE status = 'pending'
AND fetched_at < DATE_SUB(NOW(), INTERVAL 7 DAY);
```

### Log file locations

- **Import logs**: `logs/bill-imports.log`
- **Cron output**: `/home/your-username/logs/fetch-bills.log` (as configured in crontab)
- **PHP error log**: Check your hosting control panel

---

## 7. Troubleshooting

### No bills are being fetched

1. Check if sources are enabled in `config/sources.php`
2. Test individual source scripts manually
3. Check API URLs are accessible: `curl https://www.nosdeputes.fr/dossiers/date/json`
4. Review error messages in logs

### Import fails with "Connection timeout"

- Increase timeout in `config/sources.php`
- Check server firewall allows outbound HTTPS
- Try fetching fewer bills: reduce `max_bills_per_source`

### Duplicate bills appearing

- The system uses `(source, external_id)` as unique key
- Check `raw_data` field to see if external_id is consistent
- Already approved/rejected bills won't be re-imported

### Bills not appearing on main site after approval

- Check that bills were copied to `bills` table: `SELECT * FROM bills ORDER BY created_at DESC`
- Verify `vote_datetime` is set correctly
- Check bill `status` is 'upcoming' not 'completed'

### Email notifications not working

- Verify `notify_admin` is `true` in config
- Check `admin_email` is correct
- Test PHP mail: `php -r "mail('your@email.com', 'Test', 'Test message');"`
- Some shared hosting requires SMTP configuration

---

## 8. Advanced Configuration

### Auto-approve trusted sources

If you trust a source completely, enable auto-approval:

```php
// In config/sources.php
const IMPORT_SETTINGS = [
    'auto_approve' => true, // Dangerous! Bills published immediately
];
```

**Warning**: Only use this if you're confident in data quality!

### Add custom sources

To add a new data source:

1. Add source config to `config/sources.php`
2. Create fetcher in `cron/sources/your-source.php`
3. Follow the pattern from existing fetchers
4. Add to `callSourceFetcher()` in `cron/fetch-bills.php`

### Customize import logic

Edit `cron/lib/fetcher-base.php` to modify:
- Date parsing logic
- Text cleaning/truncation
- Deduplication strategy
- Error handling

---

## 9. File Structure

```
constituant/
├── cron/
│   ├── fetch-bills.php          # Main orchestration script
│   ├── lib/
│   │   └── fetcher-base.php     # Shared utilities
│   └── sources/
│       ├── nosdeputes.php       # NosDéputés.fr fetcher
│       ├── eu-parliament.php    # EU Parliament fetcher
│       └── lafabrique.php       # La Fabrique fetcher
├── public_html/
│   ├── admin/
│   │   └── pending-bills.php    # Admin review interface
│   └── config/
│       └── sources.php          # Source configuration
├── database/
│   └── migrations/
│       └── 001_add_pending_bills.sql
└── logs/
    └── bill-imports.log         # Import logs
```

---

## 10. Next Steps

1. ✅ Run database migration
2. ✅ Test individual sources manually
3. ✅ Review first batch of imported bills in admin panel
4. ✅ Approve a few test bills
5. ✅ Verify bills appear on main site
6. ✅ Set up cron job
7. ✅ Monitor logs for 1-2 weeks
8. ✅ Adjust configuration as needed

---

## Support

For issues or questions:
- Check logs: `logs/bill-imports.log`
- Review import_logs table in database
- Test API endpoints manually with curl
- Enable verbose error reporting in PHP

## Credits

Data sources:
- NosDéputés.fr (Licence ODbL) - https://www.nosdeputes.fr
- La Fabrique de la Loi - https://www.lafabriquedelaloi.fr
- European Parliament Open Data Portal - https://data.europarl.europa.eu
