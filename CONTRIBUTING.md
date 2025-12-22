# Contributing to Constituant

Thank you for your interest in contributing to Constituant! This document provides guidelines and instructions for contributing.

## 🌟 How Can I Contribute?

### Reporting Bugs

Before creating a bug report:
1. Check the [existing issues](https://github.com/constituant/constituant/issues) to avoid duplicates
2. Test with the latest version
3. Gather relevant information (browser, PHP version, error messages)

**Good Bug Report Includes:**
- Clear, descriptive title
- Steps to reproduce
- Expected vs actual behavior
- Screenshots if applicable
- Environment details (browser, OS, PHP version)
- Error messages from browser console or PHP logs

**Example:**
```
Title: Vote button doesn't respond on Safari iOS 14

Steps to reproduce:
1. Open site on Safari iOS 14
2. Click "Pour" button on any bill
3. Nothing happens

Expected: Confirmation modal should appear
Actual: No response, no console errors

Environment:
- Browser: Safari iOS 14.8
- Device: iPhone 12
- PHP: 8.1
```

### Suggesting Features

We welcome feature suggestions! Please:
1. Check [existing feature requests](https://github.com/constituant/constituant/issues?q=is%3Aissue+is%3Aopen+label%3Aenhancement)
2. Describe the problem you're trying to solve
3. Explain your proposed solution
4. Consider implementation complexity

**Good Feature Request:**
```
Title: Add email notifications when new bills are added

Problem: Users miss new bills and forget to check back

Proposed Solution:
- Add email subscription option
- Send digest email (daily or weekly)
- Include bill summary and direct link

Implementation Considerations:
- Need email service (SMTP, SendGrid, etc.)
- User email storage and privacy
- Unsubscribe functionality required
- Potential spam concerns
```

### Pull Requests

1. **Fork the repository**
2. **Create a feature branch**
   ```bash
   git checkout -b feature/amazing-feature
   ```
3. **Make your changes**
4. **Test thoroughly**
5. **Commit with clear messages**
6. **Push and create PR**

## 💻 Development Setup

### Prerequisites
- PHP 8.0+
- MySQL 5.7+ or MariaDB 10.2+
- Apache with mod_rewrite
- Git

### Local Setup

1. **Clone the repository**
   ```bash
   git clone https://github.com/constituant/constituant.git
   cd constituant
   ```

2. **Set up database**
   ```bash
   mysql -u root -p
   CREATE DATABASE constituant;
   USE constituant;
   SOURCE database/schema.sql;
   ```

3. **Configure application**
   ```bash
   cp public_html/config/database.example.php public_html/config/database.php
   cp public_html/config/config.example.php public_html/config/config.php
   # Edit both files with your local credentials
   ```

4. **Start local server**
   ```bash
   cd public_html
   php -S localhost:8000
   ```

5. **Visit** http://localhost:8000

### Development Tools

- **Code Editor**: VS Code recommended with PHP Intelephense extension
- **Browser DevTools**: Chrome or Firefox for debugging
- **Database Tool**: phpMyAdmin or MySQL Workbench
- **API Testing**: Postman or Insomnia

## 📝 Coding Standards

### PHP

Follow [PSR-12](https://www.php-fig.org/psr/psr-12/) coding standard:

```php
<?php
// Good
function calculateVotePercentage(int $votes, int $total): float
{
    if ($total === 0) {
        return 0.0;
    }

    return round(($votes / $total) * 100, 2);
}

// Bad
function calc_vote_perc($v,$t){
  if($t==0)return 0;
  return round(($v/$t)*100,2);
}
```

**Guidelines:**
- Use descriptive variable and function names
- Add docblocks for functions
- Use type hints
- Handle errors gracefully
- Sanitize all user input
- Use prepared statements for SQL

### JavaScript

Use modern ES6+ features:

```javascript
// Good
async function loadBills() {
    try {
        const response = await fetch('/api/get-votes.php');
        const data = await response.json();

        if (!data.success) {
            throw new Error(data.error);
        }

        return data.bills;
    } catch (error) {
        console.error('Error loading bills:', error);
        showErrorMessage(error.message);
    }
}

// Bad
function loadBills() {
    var xhr = new XMLHttpRequest();
    xhr.open('GET', '/api/get-votes.php', false);
    xhr.send();
    return JSON.parse(xhr.responseText);
}
```

**Guidelines:**
- Use `const` and `let`, never `var`
- Use arrow functions for callbacks
- Use async/await for promises
- Use template literals
- Add comments for complex logic
- Keep functions small and focused

### CSS

Use BEM methodology for class names:

```css
/* Good */
.bill-card {}
.bill-card__header {}
.bill-card__title {}
.bill-card--urgent {}

/* Bad */
.card {}
.header {}
.title {}
.urgent-card {}
```

**Guidelines:**
- Mobile-first approach
- Use CSS custom properties (variables)
- Avoid !important
- Use semantic class names
- Keep specificity low
- Group related properties

### HTML

Use semantic HTML5:

```html
<!-- Good -->
<article class="bill-card">
    <header class="bill-card__header">
        <h3 class="bill-card__title">Bill Title</h3>
    </header>
    <section class="bill-card__content">
        <p>Bill summary...</p>
    </section>
    <footer class="bill-card__actions">
        <button type="button">Vote</button>
    </footer>
</article>

<!-- Bad -->
<div class="card">
    <div class="header">
        <div class="title">Bill Title</div>
    </div>
    <div class="content">
        <div>Bill summary...</div>
    </div>
    <div class="actions">
        <div onclick="vote()">Vote</div>
    </div>
</div>
```

**Guidelines:**
- Use semantic elements (article, section, nav, etc.)
- Include ARIA labels for accessibility
- Use meaningful alt text for images
- Proper heading hierarchy (h1, h2, h3)
- Form labels for all inputs

## 🧪 Testing

### Manual Testing Checklist

Before submitting a PR, test:

- [ ] Works on Chrome, Firefox, Safari
- [ ] Works on mobile (iOS Safari, Chrome Android)
- [ ] Responsive design (320px to 2560px)
- [ ] Vote submission works
- [ ] Vote results update correctly
- [ ] Admin panel functions
- [ ] No console errors
- [ ] No PHP errors in logs
- [ ] Accessibility (keyboard navigation, screen reader)
- [ ] Performance (< 2s load time)

### Testing on Different Devices

- **Desktop**: Windows, macOS, Linux
- **Mobile**: iPhone (Safari), Android (Chrome)
- **Tablet**: iPad, Android tablet

### API Testing

Test API endpoints with curl:

```bash
# Get bills
curl http://localhost:8000/api/get-votes.php

# Cast vote
curl -X POST http://localhost:8000/api/cast-vote.php \
  -H "Content-Type: application/json" \
  -d '{"bill_id":"eu-dsa-2024","vote_type":"for"}'

# Get results
curl http://localhost:8000/api/get-results.php?bill_id=eu-dsa-2024
```

## 📚 Documentation

When adding features:

1. **Update README.md** with new functionality
2. **Add API documentation** for new endpoints
3. **Update CHANGELOG.md** in [Unreleased] section
4. **Add code comments** for complex logic
5. **Include inline documentation** (docblocks)

## 🎯 Project Structure

```
constituant/
├── database/           # SQL schemas and migrations
├── public_html/        # Web root
│   ├── admin/         # Admin panel
│   ├── api/           # API endpoints
│   ├── assets/        # CSS, JS, images
│   └── config/        # Configuration files
├── README.md          # Main documentation
├── CHANGELOG.md       # Version history
├── CONTRIBUTING.md    # This file
└── LICENSE            # AGPL-3.0 license
```

## 🚀 Pull Request Process

1. **Update documentation** if needed
2. **Update CHANGELOG.md** in [Unreleased]
3. **Test thoroughly** (see checklist above)
4. **Write clear commit messages**
5. **Create PR with description**

### Commit Message Format

```
type: Short description (50 chars max)

Longer explanation if needed. Wrap at 72 characters.

- Bullet points for multiple changes
- Use present tense ("add" not "added")
- Explain why, not just what

Fixes #123
```

**Types:**
- `feat`: New feature
- `fix`: Bug fix
- `docs`: Documentation only
- `style`: Code style (formatting, no logic change)
- `refactor`: Code refactoring
- `perf`: Performance improvement
- `test`: Adding tests
- `chore`: Maintenance tasks

**Examples:**
```
feat: Add email notifications for new bills

Implements email subscription system allowing users to receive
daily or weekly digests of new legislation.

- Add email field to user table
- Create notification service
- Add unsubscribe functionality
- Include email templates

Closes #45
```

```
fix: Vote button not working on Safari iOS

The confirmation modal wasn't appearing due to event delegation
issue with touch events. Changed to direct event listeners.

Fixes #78
```

## ⚖️ Code of Conduct

### Our Pledge

We pledge to make participation in our project harassment-free for everyone, regardless of:
- Age
- Body size
- Disability
- Ethnicity
- Gender identity and expression
- Level of experience
- Nationality
- Personal appearance
- Race
- Religion
- Sexual identity and orientation

### Our Standards

**Positive behavior:**
- Using welcoming and inclusive language
- Being respectful of differing viewpoints
- Gracefully accepting constructive criticism
- Focusing on what's best for the community
- Showing empathy towards others

**Unacceptable behavior:**
- Trolling, insulting/derogatory comments, personal attacks
- Public or private harassment
- Publishing others' private information without permission
- Other conduct inappropriate in a professional setting

### Enforcement

Violations may be reported to contact@constituant.fr. All complaints will be reviewed and investigated.

## 🎓 Learning Resources

### PHP
- [PHP.net Documentation](https://www.php.net/docs.php)
- [PSR Standards](https://www.php-fig.org/psr/)
- [PHP The Right Way](https://phptherightway.com/)

### JavaScript
- [MDN Web Docs](https://developer.mozilla.org/)
- [JavaScript.info](https://javascript.info/)
- [You Don't Know JS](https://github.com/getify/You-Dont-Know-JS)

### CSS
- [CSS Tricks](https://css-tricks.com/)
- [BEM Methodology](http://getbem.com/)
- [Modern CSS](https://moderncss.dev/)

### Accessibility
- [WCAG Guidelines](https://www.w3.org/WAI/WCAG21/quickref/)
- [A11y Project](https://www.a11yproject.com/)
- [WebAIM](https://webaim.org/)

## 💡 Getting Help

- **Questions**: Open a [GitHub Discussion](https://github.com/constituant/constituant/discussions)
- **Bugs**: Create an [Issue](https://github.com/constituant/constituant/issues)
- **Chat**: Join our Discord (coming soon)
- **Email**: contact@constituant.fr

## 🎉 Recognition

Contributors will be:
- Listed in README.md
- Mentioned in release notes
- Credited in CHANGELOG.md
- Appreciated forever! 💙

## 📜 License

By contributing, you agree that your contributions will be licensed under the AGPL-3.0 License.

---

**Thank you for contributing to Constituant!** 🏛️

Every contribution, no matter how small, makes a difference in civic engagement.
