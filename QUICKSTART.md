# Constituant - Quick Start Guide

Get Constituant running in 10 minutes! ⚡

## Prerequisites

- PHP 8.0+
- MySQL 5.7+
- Apache with mod_rewrite
- Text editor

## Installation in 5 Steps

### 1. Create Database (2 min)

```bash
# MySQL command line
mysql -u root -p

# In MySQL prompt:
CREATE DATABASE constituant CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
GRANT ALL PRIVILEGES ON constituant.* TO 'constituant_user'@'localhost' IDENTIFIED BY 'your_password';
FLUSH PRIVILEGES;
EXIT;

# Import schema
mysql -u constituant_user -p constituant < database/schema.sql
```

### 2. Configure Database (1 min)

Copy example and edit credentials:

```bash
cp public_html/config/database.example.php public_html/config/database.php
```

Edit `public_html/config/database.php`:
```php
const DB_NAME = 'constituant';
const DB_USER = 'constituant_user';
const DB_PASS = 'your_password';
```

### 3. Configure App (1 min)

```bash
cp public_html/config/config.example.php public_html/config/config.php
```

Edit `public_html/config/config.php`:
```php
define('SITE_URL', 'http://localhost:8000');
define('ADMIN_PASSWORD', 'change_this_now');
```

### 4. Start Server (1 min)

```bash
cd public_html
php -S localhost:8000
```

### 5. Test It! (5 min)

**Visit:** http://localhost:8000

You should see:
- ✅ 4 sample bills (2 EU, 2 France)
- ✅ Vote buttons functional
- ✅ Results updating

**Test voting:**
1. Click "Pour" on any bill
2. Confirm in modal
3. See success message
4. Vote count increases

**Test admin:**
1. Visit http://localhost:8000/admin/
2. Login with password from step 3
3. Try adding a test bill

## Production Deployment (O2switch)

### Quick Deploy (10 min)

1. **Upload via FTP**
   ```
   Host: ftp.yourdomain.com
   User: your_cpanel_user
   Path: /public_html/
   ```

2. **Create database in cPanel**
   - MySQL Databases → Create
   - Import `database/schema.sql` via phpMyAdmin

3. **Edit config files**
   - Update `config/database.php` with cPanel DB credentials
   - Update `config/config.php` with your domain

4. **Enable HTTPS**
   - Uncomment lines in `.htaccess`:
     ```apache
     RewriteCond %{HTTPS} off
     RewriteRule ^(.*)$ https://%{HTTP_HOST}%{REQUEST_URI} [L,R=301]
     ```

5. **Done!** Visit https://yourdomain.com

## Common Commands

### Database

```bash
# Backup database
mysqldump -u constituant_user -p constituant > backup.sql

# Restore database
mysql -u constituant_user -p constituant < backup.sql

# Reset votes (keep bills)
mysql -u constituant_user -p constituant -e "TRUNCATE TABLE votes;"
```

### Testing

```bash
# Test vote API
curl -X POST http://localhost:8000/api/cast-vote.php \
  -H "Content-Type: application/json" \
  -d '{"bill_id":"eu-dsa-2024","vote_type":"for"}'

# Get bills
curl http://localhost:8000/api/get-votes.php

# Get results
curl http://localhost:8000/api/get-results.php?bill_id=eu-dsa-2024
```

### File Permissions (Production)

```bash
# Set correct permissions
find public_html -type d -exec chmod 755 {} \;
find public_html -type f -exec chmod 644 {} \;
```

## Troubleshooting

### White Screen
```bash
# Enable errors temporarily
# In config/config.php:
ini_set('display_errors', '1');
error_reporting(E_ALL);
```

### Database Error
```bash
# Check connection
mysql -u constituant_user -p constituant -e "SELECT 1;"

# Check tables exist
mysql -u constituant_user -p constituant -e "SHOW TABLES;"
```

### Vote Not Working
```bash
# Check JavaScript console in browser (F12)
# Check PHP error log
tail -f /var/log/apache2/error.log  # Linux
# Or check cPanel Error Log
```

## Quick Customization

### Change Colors

Edit `public_html/assets/css/style.css`:
```css
:root {
    --color-primary: #2E5090;    /* Main blue */
    --color-secondary: #E63946;  /* Red accent */
    --color-eu: #003399;         /* EU blue */
    --color-france: #0055A4;     /* France blue */
}
```

### Add Your Logo

Replace `public_html/assets/images/logo.svg` with your logo.

### Change Site Name

Edit `public_html/config/config.php`:
```php
define('SITE_NAME', 'Your Name');
define('SITE_TAGLINE', 'Your Tagline');
```

## Adding Your First Bill

**Via Admin Panel:**
1. Go to `/admin/`
2. Click "Ajouter un projet"
3. Fill in:
   - ID: `eu-ai-act-2025` (lowercase, hyphens)
   - Title: Full bill name
   - Summary: 2-3 sentences
   - Level: EU or France
   - Chamber: Parliament name
   - Date: When vote happens
   - URL: Link to official text (optional)
4. Click "Enregistrer"

**Via API:**
```bash
curl -X POST http://localhost:8000/api/add-bill.php \
  -H "Content-Type: application/json" \
  -d '{
    "admin_password": "your_password",
    "action": "create",
    "bill": {
      "id": "eu-ai-act-2025",
      "title": "Artificial Intelligence Act",
      "summary": "Comprehensive regulation of AI systems...",
      "level": "eu",
      "chamber": "European Parliament",
      "vote_datetime": "2025-01-15 14:00:00",
      "status": "upcoming",
      "full_text_url": "https://..."
    }
  }'
```

## Next Steps

- 📖 Read full [README.md](README.md)
- 🔧 See [INSTALLATION.md](INSTALLATION.md) for detailed setup
- 🤝 Check [CONTRIBUTING.md](CONTRIBUTING.md) to contribute
- 📝 View [CHANGELOG.md](CHANGELOG.md) for updates

## Getting Help

- **Issues**: GitHub Issues
- **Email**: contact@constituant.fr
- **Docs**: Check README.md

## Security Checklist

Before going live:

- [ ] Change admin password from default
- [ ] Update `SITE_URL` in config
- [ ] Enable HTTPS redirect in `.htaccess`
- [ ] Set up regular database backups
- [ ] Check file permissions (755/644)
- [ ] Test all functionality
- [ ] Review error logs
- [ ] Test on mobile devices

## Performance Tips

```apache
# Enable OPcache (in php.ini or cPanel MultiPHP INI Editor)
opcache.enable=1
opcache.memory_consumption=128
opcache.max_accelerated_files=10000

# Already configured in .htaccess:
# - gzip compression
# - Browser caching
# - Security headers
```

## Useful Links

- **Repository**: https://github.com/constituant/constituant
- **Issues**: https://github.com/constituant/constituant/issues
- **License**: AGPL-3.0

---

**You're ready to go! 🚀**

Questions? Open an issue or email contact@constituant.fr
