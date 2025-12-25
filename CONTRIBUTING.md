# Contributing to Constituant

Thank you for your interest in contributing to Constituant! This is a civic project built by citizens, for citizens.

## Philosophy First

Before contributing code, please understand our mission:
- Transform passive voters into active governance participants
- Inspired by Étienne Chouard's work on sortition and direct democracy
- "A weapon for the people against the opacity of political powers"

Read the [README Philosophy section](README.md#philosophy) to understand what we're building and why.

## How to Contribute

### 1. Start Small
- Fix bugs or typos
- Improve documentation
- Add tests
- Optimize existing features

### 2. Discuss Big Changes
Before starting major work:
1. Open a GitHub Issue describing your idea
2. Wait for maintainer feedback
3. Discuss approach and implementation
4. Only then start coding

### 3. Follow the Code Style
- **PHP**: PSR-12 coding standard
- **SQL**: Lowercase keywords, uppercase table/column names
- **JavaScript**: ES6+, semicolons required
- **Comments**: Explain WHY, not WHAT

### 4. Test Thoroughly
This platform handles democratic data. Testing is critical:
```bash
# Run test suite
php cron/test-import.php

# Test your specific change
# Add test cases for new features
```

### 5. Document Your Changes
- Update README.md if adding features
- Update CHANGELOG.md with your changes
- Add inline comments for complex logic
- Update API documentation if relevant

## Development Setup

### Local Environment
```bash
# 1. Clone repo
git clone https://github.com/djassoRaph/constituant.git
cd constituant

# 2. Set up database
mysql -u root -p < database/schema.sql

# 3. Configure
cp config/database.php.example config/database.php
cp config/sources.php.example config/sources.php
# Edit with your credentials

# 4. Test
php cron/test-import.php
```

### WSL Development (Recommended)
If on Windows, use WSL (Windows Subsystem for Linux):
```bash
# Install PHP, MySQL, Apache in WSL
sudo apt update
sudo apt install php8.1 mysql-server apache2

# Follow local environment steps above
```

## Development Principles

### 1. Build on Existing Work
**Never recreate from scratch.** Always:
- Read existing code first
- Understand current implementation
- Preserve previous work
- Extend and improve

### 2. Backend Before Frontend
Get functionality working correctly before polishing UI:
1. Database structure
2. Business logic
3. API endpoints
4. Frontend integration
5. UX improvements

### 3. Automation First
Manual processes don't scale:
- Prefer cron jobs over manual triggers
- Use database triggers for data consistency
- Automate testing and deployment
- Document automation setup

### 4. Privacy by Design
Every feature must respect user privacy:
- Minimize data collection
- Anonymous by default
- GDPR compliant
- Secure data storage

## Pull Request Process

### Before Submitting
- [ ] Code follows style guidelines
- [ ] Tests pass (`php cron/test-import.php`)
- [ ] Documentation updated
- [ ] CHANGELOG.md updated
- [ ] Commit messages are clear

### PR Template
```markdown
## Description
Brief description of changes

## Type of Change
- [ ] Bug fix
- [ ] New feature
- [ ] Documentation update
- [ ] Performance improvement
- [ ] Refactoring

## Testing
How did you test this?

## Checklist
- [ ] Code follows style guidelines
- [ ] Tests pass
- [ ] Documentation updated
- [ ] CHANGELOG.md updated
```

### Review Process
1. Automated tests run
2. Maintainer reviews code
3. Discussion/feedback
4. Revisions if needed
5. Approval and merge

## Code Areas

### Frontend (`public/`)
- Vanilla JavaScript (no frameworks for MVP)
- Mobile-first responsive design
- Progressive enhancement
- Accessibility (WCAG 2.1 AA)

**Key files**:
- `index.php` - Main voting interface
- `css/style.css` - Styles
- `js/main.js` - Client-side logic

### Backend (`api/`, `cron/`)
- PHP 8.x
- PDO for database (prepared statements)
- Error handling and logging
- Rate limiting and security

**Key files**:
- `cron/fetch-bills.php` - Main orchestrator
- `cron/reclassify-bills.php` - Mistral AI integration
- `api/*.php` - REST endpoints

### Database (`database/`)
- MySQL 8.0+
- Migrations for schema changes
- Triggers for data consistency
- Indexes for performance

**Important**: Test migrations on dev before production!

### Admin Panel (`admin/`)
- Simple, functional interface
- CSRF protection required
- Authentication required
- Audit logging for actions

## Mistral AI Integration

Our AI classification uses Mistral's FREE tier:
```php
// Configuration in config/sources.php
const MISTRAL_API_KEY = 'your_key';
const MISTRAL_MODEL = 'mistral-small-latest';

// Usage in lib/fetcher-base.php
function classifyBillWithAI($title, $summary, $fullText = '') {
    // Returns: theme, ai_summary, confidence
}
```

**Guidelines**:
- Keep prompts efficient (free tier has limits)
- Cache results (don't re-classify)
- Handle API errors gracefully
- Log classification performance

## Security Guidelines

### Critical Security Rules
1. **SQL Injection**: Use PDO prepared statements ALWAYS
2. **XSS**: Use `htmlspecialchars()` on ALL output
3. **CSRF**: Implement tokens for state-changing operations
4. **Rate Limiting**: Prevent abuse (10 votes/hour per IP)
5. **Input Validation**: Never trust user input

### Reporting Security Issues
**DO NOT** open public issues for security vulnerabilities.

Email: security@constituant.fr (coming soon)

For now: Contact maintainer privately via GitHub.

## Documentation

### What to Document
- New features and their usage
- API endpoints and parameters
- Configuration options
- Breaking changes
- Migration guides

### Where to Document
- `README.md` - User-facing features
- `CHANGELOG.md` - Version history
- `.llms.txt` - AI context (for LLM assistants)
- Inline comments - Complex logic
- GitHub Wiki - Detailed guides (future)

## Community Guidelines

### Be Respectful
This is a civic project. We welcome:
- Constructive criticism
- Diverse perspectives
- Beginner questions
- Different approaches

We do not tolerate:
- Personal attacks
- Discrimination
- Harassment
- Bad faith arguments

### Language
- Primary: French (platform targets French/EU citizens)
- Code/docs: English preferred for international collaboration
- Issues/PRs: Either language is fine

## Getting Help

### Before Asking
1. Read README.md thoroughly
2. Check existing Issues/Discussions
3. Review code comments
4. Try debugging yourself

### Where to Ask
- **Bugs**: GitHub Issues
- **Questions**: GitHub Discussions
- **Features**: GitHub Discussions → Issue after consensus
- **Security**: Private contact (see above)

### How to Ask
Good questions include:
- What you're trying to do
- What you tried
- What happened (with error messages)
- Relevant code/configuration
- Environment details (OS, PHP version, etc.)

## Recognition

Contributors will be:
- Listed in CONTRIBUTORS.md
- Credited in CHANGELOG.md
- Thanked in release notes

Major contributors may become maintainers.

## License

By contributing, you agree that your contributions will be licensed under AGPL-3.0.

This ensures:
- Code remains open source forever
- Modifications must be shared publicly
- Platform cannot be privatized

---

## Quick Reference

### Useful Commands
```bash
# Test system
php cron/test-import.php

# Manual bill import
php cron/fetch-bills.php

# Reclassify bills
php cron/reclassify-bills.php --limit=10

# View logs
tail -f logs/bill-imports.log

# Database access
mysql -u root -p constituant
```

### File Structure
```
public/         # Frontend
admin/          # Admin panel
api/            # REST endpoints
cron/           # Automation scripts
config/         # Configuration
database/       # Schema & migrations
logs/           # Application logs
```

### Key Technologies
- PHP 8.x + MySQL 8.0
- Mistral AI (free tier)
- Vanilla JS (no frameworks)
- Cron for automation

---

**Thank you for contributing to democratic innovation!**

*"We are all constituants" - capable of governance when given proper tools.*
