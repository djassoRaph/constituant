# 🏛️ Constituant MVP

**Votre voix sur les lois du jour** - A civic engagement platform that allows French and EU citizens to vote on legislation being debated in the European Parliament and French National Assembly.

![Version](https://img.shields.io/badge/version-1.0.0-blue.svg)
![PHP](https://img.shields.io/badge/PHP-8.0%2B-blue.svg)
![License](https://img.shields.io/badge/license-AGPL--3.0-green.svg)

## 📋 Table of Contents

- [Overview](#overview)
- [Features](#features)
- [Tech Stack](#tech-stack)
- [Installation](#installation)
- [Configuration](#configuration)
- [Project Structure](#project-structure)
- [API Documentation](#api-documentation)
- [Admin Panel](#admin-panel)
- [Security](#security)
- [Troubleshooting](#troubleshooting)
- [Contributing](#contributing)
- [License](#license)

## 🎯 Overview

Constituant is a lightweight civic engagement web application that enables citizens to:
- View current legislative bills from EU Parliament and French National Assembly
- Cast their opinion (For, Against, Abstain) on each bill
- See real-time aggregate voting results
- Access full legislative texts

The platform is designed with a **mobile-first** approach and uses progressive enhancement for an optimal experience across all devices.

## ✨ Features

### Core Features
- **Real-time Voting**: Cast votes on current legislation with instant feedback
- **Aggregate Results**: See voting statistics with visual progress bars
- **Dual Legislative Tracking**: Follow both EU and French legislation
- **Mobile-First Design**: Optimized for smartphones, scales beautifully to desktop
- **Anonymous Voting**: IP-based vote tracking (one vote per IP per bill)
- **Urgency Indicators**: Visual badges for urgent votes (today, this week)
- **Direct Access**: Links to official legislative texts

### Technical Features
- **Progressive Enhancement**: Works without JavaScript (basic functionality)
- **Responsive Design**: Single codebase from 320px to 4K displays
- **Accessibility**: WCAG 2.1 AA compliant with ARIA labels
- **Dark Mode**: Automatic theme switching based on system preferences
- **Fast Performance**: < 2s load time on 3G, optimized assets
- **SEO Optimized**: Semantic HTML, meta tags, and structured data
- **Rate Limiting**: Protection against vote spam (10 votes/hour per IP)
- **Admin Panel**: Simple interface to manage bills

## 🛠️ Tech Stack

- **Frontend**: Vanilla HTML5, CSS3, JavaScript (ES6+)
- **Backend**: PHP 8.0+
- **Database**: MySQL 5.7+ / MariaDB 10.2+
- **Server**: Apache 2.4+ (O2switch shared hosting optimized)
- **No Frameworks**: Pure vanilla code for maximum performance and minimal dependencies

## 📦 Installation

### Prerequisites

- PHP 8.0 or higher
- MySQL 5.7+ or MariaDB 10.2+
- Apache with mod_rewrite enabled
- Composer (optional, for future dependencies)

### Step 1: Upload Files

Upload the contents of the `public_html` directory to your web server's public directory (typically `public_html` or `www`).

For **O2switch**:
```bash
# Via FTP/SFTP, upload to:
/home/YOUR_USERNAME/public_html/
```

### Step 2: Create Database

1. Log in to your cPanel
2. Go to **MySQL Databases**
3. Create a new database (e.g., `constituant`)
4. Create a database user and grant all privileges
5. Note down: database name, username, and password

### Step 3: Import Database Schema

1. Go to **phpMyAdmin** in cPanel
2. Select your database
3. Click **Import** tab
4. Choose `database/schema.sql`
5. Click **Go**

The schema will create two tables (`bills` and `votes`) and insert sample data for testing.

### Step 4: Configure Database Connection

Edit `public_html/config/database.php`:

```php
const DB_HOST = 'localhost';     // Usually 'localhost' for shared hosting
const DB_NAME = 'constituant';    // Your database name
const DB_USER = 'your_db_user';   // Your database username
const DB_PASS = 'your_password';  // Your database password
```

### Step 5: Set Admin Password

Edit `public_html/config/config.php`:

```php
define('ADMIN_PASSWORD', 'your_secure_password_here');
```

**⚠️ IMPORTANT**: Change this from the default `constituant2024` immediately!

### Step 6: Configure Site URL

Edit `public_html/config/config.php`:

```php
define('SITE_URL', 'https://constituant.fr'); // Your actual domain
```

### Step 7: Set File Permissions

```bash
# Make sure PHP can write logs (if needed)
chmod 755 public_html/
chmod 644 public_html/*.php
chmod 644 public_html/config/*.php
chmod 644 public_html/api/*.php
```

### Step 8: Test Installation

1. Visit your domain: `https://yourdomain.com`
2. You should see the landing page with sample bills
3. Test voting on a bill
4. Visit `/admin/` and log in with your admin password

## ⚙️ Configuration

### Environment Variables (Optional)

For enhanced security, move sensitive config to environment variables:

Create `public_html/.env` (outside public directory if possible):
```env
DB_HOST=localhost
DB_NAME=constituant
DB_USER=your_user
DB_PASS=your_password
ADMIN_PASSWORD=your_admin_password
```

### HTTPS Setup

Uncomment these lines in `public_html/.htaccess`:
```apache
RewriteCond %{HTTPS} off
RewriteRule ^(.*)$ https://%{HTTP_HOST}%{REQUEST_URI} [L,R=301]
```

### CORS Configuration

If your frontend is on a different domain, enable CORS in `config/config.php`:
```php
define('API_CORS_ENABLED', true);
```

## 📁 Project Structure

```
constituant/
├── database/
│   └── schema.sql              # Database schema and sample data
├── public_html/                # Web root (upload this to server)
│   ├── index.php              # Main landing page
│   ├── .htaccess              # Apache configuration
│   ├── robots.txt             # SEO and bot rules
│   ├── admin/
│   │   └── index.php          # Admin panel
│   ├── api/
│   │   ├── get-votes.php      # GET endpoint for bills
│   │   ├── cast-vote.php      # POST endpoint to vote
│   │   ├── get-results.php    # GET endpoint for stats
│   │   └── add-bill.php       # Admin: manage bills
│   ├── assets/
│   │   ├── css/
│   │   │   ├── style.css      # Main styles
│   │   │   └── mobile.css     # Responsive styles
│   │   ├── js/
│   │   │   ├── app.js         # Main app logic
│   │   │   └── voting.js      # Voting functionality
│   │   └── images/
│   │       ├── logo.svg       # Site logo
│   │       └── flags/         # Country flags
│   └── config/
│       ├── database.php       # DB connection
│       └── config.php         # App configuration
└── README.md                  # This file
```

## 📡 API Documentation

### GET `/api/get-votes.php`

Get all bills with vote statistics.

**Query Parameters:**
- `level` (optional): Filter by `eu`, `france`, or `all` (default: `all`)
- `status` (optional): Filter by `upcoming`, `voting_now`, or `completed`

**Response:**
```json
{
  "success": true,
  "bills": [
    {
      "id": "eu-dsa-2024",
      "title": "Digital Services Act - Amendment 247",
      "summary": "...",
      "full_text_url": "https://...",
      "level": "eu",
      "chamber": "European Parliament",
      "vote_datetime": "2024-12-15 14:00:00",
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

Cast a vote on a bill.

**Request Body:**
```json
{
  "bill_id": "eu-dsa-2024",
  "vote_type": "for"
}
```

**Vote Types:** `for`, `against`, `abstain`

**Response (Success):**
```json
{
  "success": true,
  "message": "Vote enregistré avec succès",
  "vote": {
    "bill_id": "eu-dsa-2024",
    "bill_title": "...",
    "vote_type": "for",
    "action": "created"
  }
}
```

**Response (Error):**
```json
{
  "success": false,
  "error": "Vous avez déjà voté sur ce projet de loi"
}
```

### GET `/api/get-results.php`

Get detailed vote statistics for a specific bill.

**Query Parameters:**
- `bill_id` (required): Bill ID

**Response:**
```json
{
  "success": true,
  "bill_id": "eu-dsa-2024",
  "bill_title": "...",
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
  "timeline": [...],
  "updated_at": "2024-12-05 14:30:00"
}
```

### POST `/api/add-bill.php` (Admin Only)

Add, update, or delete bills.

**Request Body:**
```json
{
  "admin_password": "your_password",
  "bill": {
    "id": "eu-dsa-2024",
    "title": "...",
    "summary": "...",
    "full_text_url": "...",
    "level": "eu",
    "chamber": "European Parliament",
    "vote_datetime": "2024-12-15 14:00:00",
    "status": "upcoming"
  },
  "action": "create"
}
```

**Actions:** `create`, `update`, `delete`

## 👨‍💼 Admin Panel

Access the admin panel at: `https://yourdomain.com/admin/`

**Default Password:** `constituant2024` (⚠️ CHANGE THIS!)

### Features:
- View all bills with vote counts
- Add new bills
- Edit existing bills
- Delete bills (also deletes associated votes)
- Real-time validation

### Adding a Bill:

1. Click "Ajouter un projet"
2. Fill in required fields:
   - **ID**: Unique identifier (lowercase, hyphens only, e.g., `eu-dsa-2024`)
   - **Title**: Bill title (max 500 chars)
   - **Summary**: Brief description
   - **Level**: EU or France
   - **Chamber**: e.g., "European Parliament" or "Assemblée Nationale"
   - **Vote Date/Time**: When the official vote takes place
   - **Full Text URL** (optional): Link to official legislation
   - **Status**: upcoming, voting_now, or completed
3. Click "Enregistrer"

## 🔒 Security

### Implemented Security Measures

1. **SQL Injection Prevention**: PDO prepared statements
2. **XSS Prevention**: `htmlspecialchars()` on all output
3. **CSRF Protection**: Token validation on admin forms
4. **Rate Limiting**: 10 votes per hour per IP
5. **Input Validation**: All user input sanitized and validated
6. **Secure Headers**: X-Content-Type-Options, X-Frame-Options, X-XSS-Protection
7. **Password Protection**: Admin panel requires password
8. **File Protection**: `.htaccess` blocks access to sensitive files
9. **Session Security**: HTTP-only, secure cookies
10. **Error Handling**: Detailed errors logged, generic errors shown to users

### Security Best Practices

- **Change default admin password immediately**
- **Use HTTPS in production** (Let's Encrypt free SSL)
- **Keep PHP and MySQL updated**
- **Regular database backups**
- **Monitor access logs** for suspicious activity
- **Consider IP whitelisting** for admin panel
- **Use strong database passwords**

### Future Security Enhancements

- Implement proper user authentication (OAuth, JWT)
- Add CAPTCHA for vote submissions
- Two-factor authentication for admin
- Database encryption for sensitive data
- Implement Content Security Policy (CSP)

## 🐛 Troubleshooting

### Database Connection Error

**Error:** "Unable to connect to database"

**Solutions:**
1. Verify database credentials in `config/database.php`
2. Ensure database exists and user has privileges
3. Check if MySQL is running: `service mysql status`
4. Verify hostname (usually `localhost` for shared hosting)

### White Screen / 500 Error

**Solutions:**
1. Enable error display temporarily (only for debugging):
   ```php
   ini_set('display_errors', 1);
   error_reporting(E_ALL);
   ```
2. Check PHP error logs in cPanel
3. Verify PHP version is 8.0+
4. Check file permissions (755 for directories, 644 for files)

### Vote Not Recording

**Solutions:**
1. Check browser console for JavaScript errors
2. Verify API endpoint is accessible: `/api/cast-vote.php`
3. Check if IP is being detected correctly
4. Ensure not hitting rate limit (10 votes/hour)
5. Check database connection

### Admin Login Not Working

**Solutions:**
1. Verify admin password in `config/config.php`
2. Clear browser cookies
3. Check if sessions are enabled: `session_status()`
4. Verify session directory is writable

### Styles Not Loading

**Solutions:**
1. Check file paths in `index.php`
2. Verify CSS files exist in `/assets/css/`
3. Clear browser cache (Ctrl + F5)
4. Check `.htaccess` is not blocking CSS files

### Votes Not Showing Up

**Solutions:**
1. Check network tab in browser DevTools
2. Verify `/api/get-votes.php` returns data
3. Check database has bills: `SELECT * FROM bills`
4. Ensure JavaScript is enabled in browser

## 📊 Performance Optimization

### Current Optimizations

- **Minification**: Consider minifying CSS and JS for production
- **Caching**: Browser caching configured in `.htaccess` (1 year for static assets)
- **Compression**: gzip compression enabled via mod_deflate
- **Database Indexing**: Indexes on frequently queried columns
- **Lazy Loading**: Consider lazy loading images in future
- **CDN**: Consider using a CDN for static assets

### Page Speed Tips

1. Enable OPcache in PHP
2. Use HTTP/2 if available
3. Optimize images (use WebP format)
4. Consider Redis for session storage
5. Implement service workers for offline support

## 🤝 Contributing

Contributions are welcome! Please follow these guidelines:

1. Fork the repository
2. Create a feature branch: `git checkout -b feature/amazing-feature`
3. Commit your changes: `git commit -m 'Add amazing feature'`
4. Push to the branch: `git push origin feature/amazing-feature`
5. Open a Pull Request

### Code Style

- **PHP**: Follow PSR-12 coding standard
- **JavaScript**: Use ES6+ features, semicolons required
- **CSS**: Use BEM methodology for class names
- **Comments**: Write clear, concise comments for complex logic

## 📄 License

This project is licensed under the **GNU Affero General Public License v3.0 (AGPL-3.0)**.

This means:
- ✅ You can use, modify, and distribute this software freely
- ✅ You must make source code available if you run this on a server
- ✅ Any modifications must also be open-source under AGPL-3.0
- ✅ You must include the original license and copyright notice

See the [LICENSE](LICENSE) file for full details.

## 🙏 Acknowledgments

- European Parliament and French National Assembly for legislative data
- O2switch for reliable shared hosting
- The open-source community for inspiration

## 📞 Support

- **Issues**: [GitHub Issues](https://github.com/constituant/constituant/issues)
- **Email**: contact@constituant.fr
- **Documentation**: [Wiki](https://github.com/constituant/constituant/wiki)

## 🗓️ Roadmap

### v1.1 (Upcoming)
- [ ] Email notifications for new bills
- [ ] User accounts with vote history
- [ ] Social sharing features
- [ ] Advanced analytics dashboard
- [ ] Multi-language support (EN, FR, DE, ES)

### v2.0 (Future)
- [ ] Mobile apps (iOS, Android)
- [ ] Real-time notifications
- [ ] Integration with official legislative APIs
- [ ] AI-powered bill summaries
- [ ] Community discussion forums

---

**Made with ❤️ for democracy and civic engagement**

*Last updated: 2024-12-05*
