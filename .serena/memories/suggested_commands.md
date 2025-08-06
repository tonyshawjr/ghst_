# Suggested Commands

## System Commands (Darwin/macOS)
- `ls -la` - List files with permissions
- `cd` - Change directory
- `grep -r "pattern" .` - Search for patterns in files
- `find . -name "*.php"` - Find PHP files
- `chmod 755 uploads` - Set upload directory permissions
- `chmod 644 config.php` - Set config file permissions

## Git Commands
- `git status` - Check current changes
- `git add .` - Stage all changes
- `git commit -m "message"` - Commit changes
- `git push origin main` - Push to main branch
- `git pull origin main` - Pull latest changes

## PHP Development
- `php -S 0.0.0.0:8000` - Start development server for mobile testing
- `php -S localhost:8000` - Start local development server
- `php -v` - Check PHP version
- `php -m` - List PHP modules
- `php cron.php` - Run cron job manually for testing

## Database Commands
- `mysql -u username -p database < db/schema.sql` - Import database schema
- `mysqldump -u username -p database > backup.sql` - Backup database

## Testing & Debugging
- `tail -f logs/error.log` - Watch error logs
- `php -l file.php` - Check PHP syntax
- No unit tests or test framework currently configured
- No linting tools configured (vanilla PHP project)

## Cron Jobs (Production)
```bash
# Post scheduler (every 5 minutes)
*/5 * * * * /usr/bin/php /path/to/ghst_/cron.php

# Analytics collection (hourly)
0 * * * * /usr/bin/php /path/to/ghst_/cron/collect-analytics.php

# Email queue processor (every 5 minutes)
*/5 * * * * /usr/bin/php /path/to/ghst_/includes/workers/email-queue-worker.php
```

## Installation
1. Upload all files to domain root
2. Copy `config.example.php` to `config.php`
3. Update database credentials in `config.php`
4. Run installer at `https://yourdomain.com/installer.php`
5. Set up cron jobs as shown above
6. Configure OAuth for social platforms

## Common Development Tasks
- Check error logs when debugging
- Clear browser cache when CSS changes don't appear
- Use browser DevTools for JavaScript debugging
- Test on mobile using IP address (not localhost)