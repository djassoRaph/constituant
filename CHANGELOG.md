# Changelog

All notable changes to the Constituant project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [1.0.0] - 2024-12-05

### 🎉 Initial Release

First public release of Constituant MVP - A civic engagement platform for voting on EU and French legislation.

### Added

#### Frontend
- **Landing Page**: Mobile-first responsive design with bill cards
- **Vote Interface**: Interactive voting with confirmation modals
- **Real-time Results**: Live vote statistics with progress bars
- **Read More/Less**: Expandable bill summaries
- **Urgency Indicators**: Visual badges for urgent votes
- **Toast Notifications**: User feedback for actions
- **Dark Mode Support**: Automatic theme switching
- **Accessibility**: WCAG 2.1 AA compliant with ARIA labels

#### Backend
- **Database Schema**: MySQL tables for bills and votes
- **API Endpoints**:
  - `GET /api/get-votes.php` - Retrieve bills with vote counts
  - `POST /api/cast-vote.php` - Submit votes
  - `GET /api/get-results.php` - Get detailed vote statistics
  - `POST /api/add-bill.php` - Admin bill management
- **Security Features**:
  - SQL injection prevention with PDO prepared statements
  - XSS protection with output sanitization
  - Rate limiting (10 votes/hour per IP)
  - CSRF token validation
  - Secure session handling
- **Admin Panel**: Simple interface to manage bills

#### Infrastructure
- **Apache Configuration**: `.htaccess` with security headers and caching
- **Compression**: gzip compression for text assets
- **Browser Caching**: 1-year cache for static assets
- **SEO Optimization**: `robots.txt` and meta tags
- **Documentation**:
  - Comprehensive README with API docs
  - O2switch-specific installation guide
  - Troubleshooting guide

#### Design
- **Color Scheme**: Institutional blue (#2E5090) and red (#E63946)
- **Typography**: System fonts with Georgia for body text
- **Responsive Grid**: Single column mobile, multi-column desktop
- **Animations**: Smooth transitions and loading states
- **Icons**: Emoji-based for broad compatibility

### Security
- PDO prepared statements prevent SQL injection
- `htmlspecialchars()` prevents XSS attacks
- Rate limiting prevents vote spam
- CSRF tokens on admin forms
- IP-based vote tracking
- Secure session configuration
- File access protection via `.htaccess`

### Performance
- Mobile-first CSS loading
- Optimized database queries with indexes
- Browser caching (1 year for assets)
- gzip compression enabled
- Minimal JavaScript (~10KB combined)
- No external dependencies
- < 2 second load time on 3G

### Browser Support
- ✅ Chrome/Edge 90+
- ✅ Firefox 88+
- ✅ Safari 14+
- ✅ Mobile Safari iOS 14+
- ✅ Chrome Android 90+

### Tested On
- ✅ O2switch shared hosting
- ✅ PHP 8.0, 8.1, 8.2
- ✅ MySQL 5.7+ / MariaDB 10.2+
- ✅ Apache 2.4

### Sample Data
- 4 sample bills included (2 EU, 2 France)
- Ready for testing immediately after installation

---

## [Unreleased]

### Planned for v1.1
- Email notifications for new bills
- User accounts with vote history
- Social sharing buttons
- Export vote results to CSV
- Bill categories/tags
- Search and filter functionality
- Vote change tracking over time

### Planned for v1.2
- Multi-language support (EN, DE, ES, IT)
- Advanced analytics dashboard
- Vote reminders
- Bill comparison feature
- Public API for developers
- Automated bill import from official sources

### Planned for v2.0
- Mobile apps (iOS, Android)
- Real-time WebSocket updates
- User profiles and reputation system
- Discussion forums per bill
- Vote impact visualization
- Integration with official parliamentary APIs

---

## Version History

### [1.0.0] - 2024-12-05
- Initial release

---

## Notes

### Breaking Changes
None yet - this is the first release!

### Deprecated
None

### Known Issues
- Vote counts may be delayed by up to 30 seconds (polling interval)
- No vote history for users (IP-based only)
- Admin panel has basic authentication only
- No email notifications yet
- Single language (French) only

### Migration Guide
Not applicable for initial release.

---

## How to Upgrade

When new versions are released, follow these steps:

1. **Backup Everything**
   - Database: Export via phpMyAdmin
   - Files: Download entire `public_html` directory

2. **Check CHANGELOG**
   - Read breaking changes
   - Note new features
   - Check database migrations needed

3. **Update Files**
   - Replace all files except `config/`
   - Keep your `config/database.php` and `config/config.php`

4. **Run Migrations**
   - Import any SQL migration files
   - Check for new config options

5. **Test Thoroughly**
   - Test voting functionality
   - Check admin panel
   - Verify API endpoints

6. **Clear Caches**
   - Browser cache (Ctrl + F5)
   - Server cache if applicable

---

## Contributors

- **Development**: Constituant Team
- **Design**: Constituant Design
- **Testing**: Community Contributors

## Support

- GitHub Issues: https://github.com/constituant/constituant/issues
- Email: contact@constituant.fr
- Documentation: See README.md

---

**Legend:**
- 🎉 Major release
- ✨ New feature
- 🐛 Bug fix
- 🔒 Security fix
- ⚡ Performance improvement
- 📚 Documentation
- 🎨 UI/UX improvement
- ♿ Accessibility
- 🌍 Internationalization
- 🗃️ Database
- 🔧 Configuration
- 🚀 Deployment

---

*Keep this file updated with every release!*
