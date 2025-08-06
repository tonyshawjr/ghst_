# Project Structure

## Root Directory
```
/ghst_
├── index.php           # Entry point, redirects to login or dashboard
├── login.php           # Login page with auth handling
├── logout.php          # Logout handler
├── installer.php       # Complete setup installer
├── cron.php           # Scheduled post processor
├── config.php         # Configuration file (created from config.example.php)
├── config.example.php  # Configuration template
├── .htaccess          # Security rules and redirects
├── README.md          # Project documentation
├── CLAUDE.md          # AI team collaboration guidelines
├── UPDATES.md         # Change log and known issues
└── .gitignore         # Git ignore rules
```

## Application Directories

### /dashboard
Main application pages:
- `index.php` - Dashboard home with stats
- `posts.php` - Post management and scheduler
- `calendar.php` - Calendar view of scheduled posts
- `media.php` - Media library management
- `analytics.php` - Analytics dashboard
- `accounts.php` - Social account management
- `settings.php` - User and client settings
- `oauth-setup.php` - OAuth platform configuration
- `ai-suggestions.php` - AI content generation
- `switch-client.php` - Client switching interface
- `wrtr.php` - Campaign strategy engine
- `wrtr-scheduler.php` - Campaign post scheduler
- `reports.php` - Report generation and management
- `email-management.php` - Email queue management

### /api
API endpoints organized by function:
- `/oauth` - OAuth callback handlers
- `/webhooks` - Platform webhook receivers
- `/analytics` - Analytics collection endpoints
- `/reports` - Report generation and sharing
- `/media` - Media upload and optimization
- `/wrtr` - Campaign strategy API
- `/email` - Email sending and testing

### /includes
PHP classes and helpers:
- Core classes: `Auth.php`, `Database.php`, `functions.php`
- Feature classes: `AIContentSuggestions.php`, `ReportGenerator.php`
- `/platforms` - Platform-specific implementations
- `/email-templates` - Email template files
- `/report-templates` - Report template files
- `/exceptions` - Custom exception classes
- `/workers` - Background job processors

### /assets
Frontend resources:
- `/css` - Stylesheets (Tailwind output)
- `/js` - JavaScript files
- `/img` - Application images and logos
- `/fonts` - Custom fonts (if any)

### /uploads
User uploaded media:
- Organized by client ID
- Subdirectories for processed versions
- Thumbnails and optimized versions

### /db
Database files:
- `schema.sql` - Complete database schema
- Migration files (if any)

### /installer
Installation wizard steps:
- `step1-database.php` - Database setup
- `step2-admin.php` - Admin account creation
- `step3-oauth.php` - OAuth configuration
- `step4-complete.php` - Installation completion

### /logs
Application logs:
- `error.log` - PHP errors
- `cron.log` - Cron job execution
- `api.log` - API request logs

### /cache
Cache storage (if enabled):
- Template cache
- API response cache

### /cron
Cron job scripts:
- `collect-analytics.php` - Analytics collection job
- Other scheduled tasks

### /shared
Public sharing pages:
- `report.php` - Shared report viewer

## Key Design Patterns
1. **Singleton Database** - One DB connection per request
2. **Session-based Auth** - Stateful authentication
3. **Template Includes** - PHP includes for layouts
4. **Direct File Serving** - No build process
5. **Platform Abstraction** - Interface for social platforms
6. **Queue Pattern** - Email and post scheduling queues