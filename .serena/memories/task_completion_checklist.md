# Task Completion Checklist

## Before Marking a Task Complete

### Code Quality Checks
- [ ] Code follows existing conventions (snake_case for DB, camelCase for PHP)
- [ ] All user input is sanitized using `sanitizeInput()`
- [ ] All output is escaped with `htmlspecialchars()`
- [ ] Database queries use prepared statements
- [ ] CSRF token validation on forms

### Testing Requirements
- [ ] Test in browser (no automated tests available)
- [ ] Test on mobile view if UI changes
- [ ] Check browser console for JavaScript errors
- [ ] Verify database changes if applicable
- [ ] Test with multiple clients if multi-client feature

### Security Validation
- [ ] No hardcoded credentials or API keys
- [ ] Session checks for authenticated pages
- [ ] File upload restrictions enforced
- [ ] SQL injection prevention verified
- [ ] XSS protection in place

### Documentation Updates
- [ ] Code comments added for complex logic
- [ ] Update UPDATES.md if significant change
- [ ] Update README.md if new feature added

## Common Issues to Check

### PHP Issues
- Check PHP error logs at `/logs/error.log`
- Verify PHP 8+ compatibility
- Check session configuration for development

### Database Issues
- Verify foreign key constraints
- Check for proper indexes on frequently queried fields
- Ensure soft delete logic if applicable

### Frontend Issues
- Tailwind classes applied correctly
- Alpine.js directives working
- Mobile responsive design maintained
- Dark theme consistency

### API Integration Issues
- OAuth tokens not expired
- API rate limits considered
- Error handling for API failures
- Retry logic for failed requests

## Deployment Checklist
- [ ] config.php updated with production values
- [ ] Debug mode disabled in production
- [ ] File permissions set correctly
- [ ] Cron jobs configured
- [ ] SSL certificate active
- [ ] Session cookies configured for HTTPS

## Quick Validation Commands
```bash
# Check PHP syntax
php -l filename.php

# Check file permissions
ls -la uploads/

# Test cron job
php cron.php

# Check error logs
tail -f logs/error.log

# Test database connection
php -r "require 'config.php'; require 'includes/Database.php'; Database::getInstance();"
```

## Notes
- No automated testing framework available
- No linting tools configured
- Manual testing required for all changes
- Use browser DevTools for debugging
- Test with actual social media accounts when possible