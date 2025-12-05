# Installation Guide for O2switch Hosting

This guide will walk you through deploying Constituant on O2switch shared hosting.

## Prerequisites

- O2switch hosting account with cPanel access
- FTP/SFTP client (FileZilla recommended)
- Text editor (VS Code, Sublime, or Notepad++)

## Step-by-Step Installation

### 1. Prepare Database

1. **Log in to cPanel**
   - Go to: `https://cpanel.o2switch.fr/`
   - Enter your credentials

2. **Create MySQL Database**
   - Click on **MySQL Databases**
   - Under "Create New Database", enter: `constituant`
   - Click **Create Database**
   - Note the full database name (usually: `username_constituant`)

3. **Create Database User**
   - Scroll to "MySQL Users"
   - Username: `constituant_user`
   - Generate strong password (click generator)
   - **Save this password securely!**
   - Click **Create User**

4. **Grant Privileges**
   - Scroll to "Add User to Database"
   - User: Select `constituant_user`
   - Database: Select `constituant`
   - Click **Add**
   - Check **ALL PRIVILEGES**
   - Click **Make Changes**

5. **Import Schema**
   - Go back to cPanel home
   - Click **phpMyAdmin**
   - Select your `constituant` database from left sidebar
   - Click **Import** tab
   - Click **Choose File**
   - Select `database/schema.sql` from your computer
   - Click **Go** at bottom
   - Wait for success message

### 2. Configure Files

1. **Edit Database Configuration**
   - Open `public_html/config/database.php` in text editor
   - Update these values:
     ```php
     const DB_HOST = 'localhost';              // Keep as localhost
     const DB_NAME = 'username_constituant';   // Your full database name
     const DB_USER = 'username_constituant_user'; // Your full username
     const DB_PASS = 'your_password';          // Password from step 1.3
     ```

2. **Edit App Configuration**
   - Open `public_html/config/config.php`
   - Update:
     ```php
     define('SITE_URL', 'https://yourdomain.com');
     define('ADMIN_PASSWORD', 'choose_strong_password_here');
     ```

3. **Enable HTTPS Redirect**
   - Open `public_html/.htaccess`
   - Uncomment lines 12-13:
     ```apache
     RewriteCond %{HTTPS} off
     RewriteRule ^(.*)$ https://%{HTTP_HOST}%{REQUEST_URI} [L,R=301]
     ```

### 3. Upload Files

#### Option A: Using FileZilla (Recommended)

1. **Connect to FTP**
   - Host: `ftp.yourdomain.com` or FTP server from O2switch email
   - Username: Your cPanel username
   - Password: Your cPanel password
   - Port: 21 (or 22 for SFTP)

2. **Navigate to public_html**
   - In the right pane (server), go to: `/home/username/public_html/`

3. **Upload Files**
   - In the left pane (local), navigate to `constituant/public_html/`
   - Select ALL files and folders
   - Right-click → Upload
   - Wait for upload to complete (may take 2-5 minutes)

#### Option B: Using cPanel File Manager

1. **Compress Files Locally**
   - Zip the entire `public_html` folder contents
   - Name it: `constituant.zip`

2. **Upload via File Manager**
   - In cPanel, click **File Manager**
   - Navigate to `public_html/`
   - Click **Upload**
   - Select `constituant.zip`
   - Wait for upload
   - Right-click the zip file → **Extract**
   - Delete the zip file after extraction

### 4. Set Permissions

In cPanel File Manager:

1. Select `public_html` folder
2. Click **Permissions** (or right-click → Permissions)
3. Set to: `755` (rwxr-xr-x)
4. Check "Recurse into subdirectories"
5. Click **Change Permissions**

### 5. Test Installation

1. **Visit Your Site**
   - Go to: `https://yourdomain.com`
   - You should see the Constituant homepage with 4 sample bills

2. **Test Voting**
   - Click a vote button (Pour, Contre, or Abstention)
   - Confirm the vote
   - Check if success message appears
   - Verify vote count increases

3. **Test Admin Panel**
   - Go to: `https://yourdomain.com/admin/`
   - Enter your admin password
   - You should see the admin dashboard
   - Try adding a test bill

### 6. Security Hardening

1. **Change Admin Password**
   - Edit `config/config.php`
   - Change `ADMIN_PASSWORD` to something very strong
   - Use: letters, numbers, symbols, 16+ characters

2. **Protect Config Files** (already done in `.htaccess`)
   - Config files are not accessible via web
   - Verify by visiting: `https://yourdomain.com/config/config.php`
   - Should show 403 Forbidden

3. **Enable SSL Certificate**
   - In cPanel, go to **SSL/TLS Status**
   - Click **Run AutoSSL**
   - Wait for certificate to be issued (5-10 minutes)

4. **Set Up Backups**
   - In cPanel, go to **Backup Wizard**
   - Set up daily automated backups
   - O2switch includes automatic backups

### 7. Maintenance

#### Adding Your First Real Bill

1. Go to Admin Panel: `/admin/`
2. Click "Ajouter un projet"
3. Fill in:
   - **ID**: Use format like `eu-ai-act-2024` or `fr-climat-2024`
   - **Title**: Full bill title in original language
   - **Summary**: 2-3 sentence description
   - **Full Text URL**: Link to official source
   - **Level**: EU or France
   - **Chamber**: "European Parliament" or "Assemblée Nationale"
   - **Date/Time**: When the real vote happens
   - **Status**: Usually "upcoming"
4. Click "Enregistrer"

#### Updating Bills

- Edit vote datetime if it changes
- Change status to "voting_now" when vote is happening
- Change to "completed" after official vote
- Or delete old bills (caution: deletes all votes too!)

#### Monitoring

Check regularly:
- cPanel **Error Log** for PHP errors
- **Awstats** for traffic analysis
- **Metrics** for resource usage

### Troubleshooting

#### "Database connection failed"
- Verify database credentials in `config/database.php`
- Check database exists in phpMyAdmin
- Ensure user has all privileges

#### "500 Internal Server Error"
- Check Error Log in cPanel
- Verify `.htaccess` syntax
- Check PHP version is 8.0+ (cPanel → MultiPHP Manager)

#### "Page not found" or styles missing
- Verify all files uploaded correctly
- Check file permissions (should be 644 for files, 755 for directories)
- Clear browser cache

#### Admin login doesn't work
- Verify password in `config/config.php`
- Check if sessions are enabled (they are by default on O2switch)
- Clear browser cookies

#### Votes not recording
- Check Error Log for PHP errors
- Verify database connection works
- Check if you hit rate limit (10 votes/hour per IP)
- Test API directly: `/api/cast-vote.php`

### O2switch Specific Notes

1. **PHP Version**: O2switch supports PHP 8.0, 8.1, 8.2
   - Recommended: PHP 8.1 for best compatibility
   - Change in: cPanel → MultiPHP Manager

2. **MySQL Version**: MariaDB 10.6 (fully compatible)

3. **Resource Limits**:
   - Unlimited bandwidth
   - Unlimited disk space
   - No CPU/RAM limits (fair use)
   - Connection limits: 50 concurrent (more than enough)

4. **Email Setup**: Configure contact form to use O2switch SMTP
   - SMTP Server: `mail.yourdomain.com`
   - Port: 587 (TLS) or 465 (SSL)
   - Credentials: Create email account in cPanel

5. **Cron Jobs** (for future features):
   - cPanel → Cron Jobs
   - Can run scheduled tasks (e.g., update bill statuses)

### Performance Tips for O2switch

1. **Enable OPcache** (usually on by default)
   - Check in: cPanel → MultiPHP INI Editor
   - Verify `opcache.enable=1`

2. **Optimize Images**
   - Use WebP format for images
   - Compress with TinyPNG before upload

3. **Use o2switch CDN**
   - Available in cPanel
   - Speeds up static asset delivery

4. **Database Optimization**
   - Run regularly: phpMyAdmin → Operations → Optimize table
   - Or add cron job: `mysqlcheck -o -u user -p database`

### Getting Help

- **O2switch Support**: Ticket in client area (very responsive!)
- **Documentation**: https://faq.o2switch.fr/
- **Community**: Forum o2switch
- **This Project**: GitHub issues or contact@constituant.fr

### Next Steps

Once installed:
1. Delete sample bills from admin panel
2. Add real legislative bills
3. Share on social media to get voters
4. Monitor analytics in cPanel
5. Consider adding features from README roadmap

---

**Congratulations! Your Constituant platform is now live! 🎉**

*Installation time: ~30 minutes*
*Difficulty: Easy to Medium*
