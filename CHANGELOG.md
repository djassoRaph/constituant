# Changelog

All notable changes to the Constituant project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Planned
- Representative scorecards (alignment tracking)
- Petition threshold system
- Discourse integration for discussions
- User account system (optional identity verification)
- Email notifications for new votes
- Enhanced analytics dashboard

---

## [0.2.0] - 2024-12-XX - Production Deployment

### Added
- **Full Automation**: Bills now flow automatically from API → classification → publication
- **Mistral AI Integration**: Automatic theme classification and citizen-friendly summaries
  - Free tier usage (~95% confidence)
  - 15 predefined themes (Economy, Health, Environment, etc.)
  - Plain-language summaries (2-3 sentences)
- **Cron Jobs**: Automated bill fetching every 6 hours
- **Database Triggers**: Automatic vote counting (yes_count, no_count, abstain_count)
- **Theme Filtering**: Browse bills by category on frontend
- **Social Sharing**: Twitter and Facebook integration
- **Mobile Optimizations**: Improved responsive design
- **API Rate Limiting**: 10 votes/hour per IP to prevent abuse
- **Comprehensive Logging**: Import logs with execution times and error tracking

### Changed
- Removed manual approval workflow (pending_bills → bills)
- Bills now auto-publish after AI classification
- Updated UI to move voted items to separate column
- Improved admin panel layout and navigation
- Enhanced error handling in fetchers
- Optimized database queries with indexes

### Fixed
- French legislative URLs (corrected period references)
- Twitter share integration (proper URL encoding)
- Facebook sharing functionality
- Admin panel JavaScript issues
- Database connection timeout handling
- Vote double-counting prevention

### Technical
- **Database Schema**: Added AI classification columns (theme, ai_summary, ai_processed_at)
- **New Tables**: import_logs for tracking automated imports
- **Triggers**: AUTO_UPDATE_VOTE_COUNTS for real-time statistics
- **Cron Scripts**: 
  - fetch-bills.php (main orchestrator)
  - reclassify-bills.php (Mistral AI classification)
  - test-import.php (comprehensive test suite)
- **API Integrations**:
  - NosDéputés.fr API
  - European Parliament Legislative Observatory
  - La Fabrique de la Loi
  - Mistral AI API

### Infrastructure
- Deployed to production: constituant.fr
- Configured SSL certificate
- Set up automated backups
- Cron jobs configured on o2switch
- Log rotation implemented

---

## [0.1.0] - 2024-12-10 - MVP Launch

### Added
- **Core Voting System**
  - Anonymous voting (IP-based)
  - For/Against/Abstain options
  - Real-time result aggregation
  - Vote prevention (one vote per IP per bill)
  
- **Bill Management**
  - Database schema for bills and votes
  - Sample data for testing
  - Admin panel for bill CRUD operations
  - Pending bills workflow (later removed in 0.2.0)

- **Frontend**
  - Mobile-first responsive design
  - Two-card layout (EU and France)
  - Vote statistics with progress bars
  - Expandable bill details
  - Links to full legislative texts

- **Backend**
  - PHP 8.x with PDO
  - MySQL database with proper indexes
  - REST API endpoints:
    - GET /api/get-votes.php (fetch bills)
    - POST /api/cast-vote.php (submit vote)
    - GET /api/get-results.php (aggregated results)
  - Admin API (bill management)

- **Security**
  - SQL injection prevention (prepared statements)
  - XSS prevention (output escaping)
  - CSRF protection for admin
  - Rate limiting (10 votes/hour)
  - Input validation and sanitization
  - Secure headers (.htaccess)

- **Documentation**
  - README.md with project overview
  - INSTALLATION.md with setup guide
  - QUICKSTART.md for rapid deployment
  - CONTRIBUTING.md with guidelines
  - Inline code comments

### Infrastructure
- GitHub repository: github.com/djassoRaph/constituant
- Domain registered: constituant.fr
- License: AGPL-3.0
- Hosting: o2switch shared hosting
- Development: WSL (Ubuntu) environment

---

## Project Milestones

### Phase 1: Transparency (Current)
**Status**: ✅ COMPLETE - MVP deployed with full automation

**Goals Achieved**:
- [x] Automated legislative data ingestion
- [x] AI-powered bill classification
- [x] Simple voting interface
- [x] Real-time result aggregation
- [x] Plain-language summaries
- [x] Mobile-responsive design
- [x] Production deployment

**Next Steps**:
- [ ] Gather user feedback
- [ ] Improve UI/UX based on usage patterns
- [ ] Expand to more legislative sources
- [ ] Add user accounts (optional)
- [ ] Implement discussion features

### Phase 2: Accountability (Planned - Years 2-3)
- [ ] Representative scorecards (alignment tracking)
- [ ] Petition thresholds (trigger responses)
- [ ] Citizen amendment proposals
- [ ] Impact tracking (6-month follow-ups)
- [ ] Partnership with civic organizations

### Phase 3: Transformation (Planned - Year 3+)
- [ ] Constituent assembly simulator
- [ ] Sortition experiments (random panels)
- [ ] Alternative legislation drafting
- [ ] Deliberation training workshops
- [ ] Educational content on sortition

---

## Technical Debt & Known Issues

### Current Issues
- [ ] EU Parliament API occasionally returns 406 (Accept header issue)
- [ ] Admin panel JavaScript needs refactoring
- [ ] Occasional database connection timeouts (need connection pooling)
- [ ] La Fabrique CSV parser needs optimization
- [ ] Some error messages not translated to French

### Future Improvements
- [ ] Add database connection pooling
- [ ] Implement Redis caching for vote counts
- [ ] Add Elasticsearch for bill search
- [ ] Migrate to modern PHP framework (Phase 2)
- [ ] Add comprehensive test suite (PHPUnit)
- [ ] Implement continuous integration (GitHub Actions)
- [ ] Add performance monitoring (APM)

---

## Version History Summary

| Version | Date       | Description                          |
|---------|------------|--------------------------------------|
| 0.2.0   | 2024-12-XX | Full automation + AI classification  |
| 0.1.0   | 2024-12-10 | MVP with manual bill management      |

---

## Contributing

See [CONTRIBUTING.md](CONTRIBUTING.md) for guidelines on how to contribute to this changelog and the project.

### Changelog Guidelines
- Keep entries concise but informative
- Use present tense ("Add feature" not "Added feature")
- Group changes by type (Added, Changed, Fixed, etc.)
- Link to relevant issues/PRs
- Update unreleased section for ongoing work

---

*"We are all constituants" - building tools for democratic participation*

**Repository**: https://github.com/djassoRaph/constituant  
**License**: AGPL-3.0  
**Status**: Production - Active Development
