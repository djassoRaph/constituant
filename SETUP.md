# Constituant - Setup Instructions

## 🔧 Initial Setup

### 1. **Clone the Repository**
```bash
git clone https://github.com/djassoRaph/constituant.git
cd constituant
```

### 2. **Configure Database**
```bash
# Copy the example file
cp public_html/config/database.example.php public_html/config/database.php

# Edit with your database credentials
nano public_html/config/database.php
```

Update these values:
```php
define('DB_HOST', 'localhost');
define('DB_NAME', 'db_name');
define('DB_USER', 'db_user');
define('DB_PASS', 'db_password');
```

### 3. **Configure API Keys** 🔑
```bash
# Copy the example file
cp public_html/config/api-keys.example.php public_html/config/api-keys.php

# Edit with your real API keys
nano public_html/config/api-keys.php
```

Update the Mistral AI key:
```php
define('MISTRAL_API_KEY', 'your-mistral-api-key-here');
```

**⚠️ IMPORTANT:**
- `api-keys.php` is in `.gitignore` and will NOT be committed
- NEVER commit real API keys to version control
- Get your Mistral AI key from: https://console.mistral.ai/

### 4. **Run Database Migrations**
```bash
# Add AI classification columns to pending_bills table
mysql -u username -p rera8347_constituant < database/migrations/add_ai_classification_columns.sql

# Add AI classification columns to bills table
mysql -u username -p rera8347_constituant < database/migrations/add_ai_classification_to_bills.sql
```

### 5. **Set File Permissions**
```bash
# Make logs directory writable
chmod 755 logs/
chmod 644 logs/*.log

# Make cron scripts executable
chmod +x cron/*.php
chmod +x cron/sources/*.php
```

---

## 🤖 AI Classification Setup

The platform uses **Mistral AI** to automatically classify and summarize legislative bills.

### Get Mistral API Key:
1. Visit https://console.mistral.ai/
2. Create an account (free tier available)
3. Generate an API key
4. Add it to `public_html/config/api-keys.php`

### Test AI Classification:
```bash
# Test a single bill classification
php examples/test_mistral_classification.php

# Re-classify existing bills without AI data
php cron/reclassify-bills.php --limit=5
```

---

## 📥 Bill Import Setup

### Configure Sources:
Edit `public_html/config/sources.php` to enable/disable sources:
```php
'lafabrique' => [
    'enabled' => true,  // Set to false to disable
    'priority' => 2,
    // ...
],
```

### Test Imports:
```bash
# Test individual sources
php cron/sources/lafabrique.php
php cron/sources/nosdeputes.php
php cron/sources/eu-parliament.php

# Run full import
php cron/fetch-bills.php
```

### Schedule Cron Job:
Add to your crontab (`crontab -e`):
```bash
# Import bills twice daily at 6 AM and 6 PM
0 6,18 * * * /usr/bin/php /path/to/constituant/cron/fetch-bills.php

# Re-classify bills daily at 3 AM
0 3 * * * /usr/bin/php /path/to/constituant/cron/reclassify-bills.php --limit=50
```

---

## 🗂️ Configuration Files

| File | Purpose | Commit to Git? |
|------|---------|----------------|
| `database.example.php` | Database config template | ✅ Yes |
| `database.php` | Your actual DB credentials | ❌ No (.gitignore) |
| `api-keys.example.php` | API keys template | ✅ Yes |
| `api-keys.php` | Your actual API keys | ❌ No (.gitignore) |
| `config.example.php` | Site config template | ✅ Yes |
| `config.php` | Your actual site config | ❌ No (.gitignore) |
| `sources.php` | Bill sources configuration | ✅ Yes |

---

## 🔒 Security Checklist

- [ ] `api-keys.php` is NOT committed to Git
- [ ] `database.php` is NOT committed to Git
- [ ] API keys are stored in `api-keys.php` (not hardcoded)
- [ ] `.gitignore` includes sensitive files
- [ ] File permissions are set correctly (755 for directories, 644 for files)
- [ ] Database credentials use strong passwords
- [ ] Admin panel requires authentication

---

## 🧪 Testing

### Test Frontend:
```bash
# Visit in browser
http://localhost/constituant/public_html/
```

### Test API Endpoints:
```bash
# Get all bills
curl http://localhost/constituant/public_html/api/get-votes.php?level=all

# Get only French bills
curl http://localhost/constituant/public_html/api/get-votes.php?level=france
```

### Test AI Classification:
```bash
# Classify 5 bills
php cron/reclassify-bills.php --limit=5

# Check logs
tail -100 logs/bill-imports.log
```

---

## 📊 Verify Setup

```bash
# Check database tables exist
mysql -u username -p rera8347_constituant -e "SHOW TABLES;"

# Check AI columns exist
mysql -u username -p rera8347_constituant -e "
DESCRIBE bills;
DESCRIBE pending_bills;
"

# Count imported bills
mysql -u username -p rera8347_constituant -e "
SELECT source, COUNT(*) as count
FROM pending_bills
GROUP BY source;
"
```

---

## 🆘 Troubleshooting

### "API key not found" error:
- Ensure `api-keys.php` exists (not just `.example`)
- Check file permissions: `chmod 644 public_html/config/api-keys.php`
- Verify the constant is defined: `grep MISTRAL_API_KEY public_html/config/api-keys.php`

### "Column not found: ai_summary":
- Run the database migrations (see step 4 above)
- Check columns exist: `DESCRIBE bills;`

### Import returns 0 bills:
- Check logs: `tail -100 logs/bill-imports.log`
- Run diagnostic scripts:
  - `php cron/test-lafabrique-csv.php`
  - `php cron/test-nosdeputes-api.php`

### Frontend shows no bills:
- Import bills first: `php cron/fetch-bills.php`
- Approve bills in admin panel
- Publish approved bills to `bills` table

---

## 📚 Additional Resources

- **API Documentation**: See `/docs/API.md`
- **Import Fixes**: See `/IMPORT_FIXES.md`
- **Database Schema**: See `/database/schema.sql`
- **Mistral AI Docs**: https://docs.mistral.ai/

---

## 🚀 Quick Start

```bash
# One-command setup (after cloning)
cp public_html/config/database.example.php public_html/config/database.php
cp public_html/config/api-keys.example.php public_html/config/api-keys.php

# Edit configs
nano public_html/config/database.php
nano public_html/config/api-keys.php

# Run migrations
mysql -u username -p rera8347_constituant < database/migrations/add_ai_classification_columns.sql
mysql -u username -p rera8347_constituant < database/migrations/add_ai_classification_to_bills.sql

# Import bills
php cron/fetch-bills.php

# Done! Visit http://localhost/constituant/public_html/
```

---

**Need help?** Open an issue at https://github.com/djassoRaph/constituant/issues
