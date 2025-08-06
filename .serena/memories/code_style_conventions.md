# Code Style and Conventions

## PHP Conventions
- **PHP 8+ syntax** used throughout
- **Object-oriented approach** for major components (Auth, Database, etc.)
- **Singleton pattern** for Database class
- **Prepared statements** for all database queries
- **Type hints** not extensively used (PHP 7 compatibility in some areas)
- **Namespace usage**: Not used (vanilla PHP approach)

## Naming Conventions
- **Classes**: PascalCase (e.g., `Auth`, `MediaProcessor`, `ReportGenerator`)
- **Methods**: camelCase (e.g., `getCurrentClient()`, `validateCSRFToken()`)
- **Variables**: snake_case for database fields, camelCase for PHP variables
- **Constants**: UPPER_SNAKE_CASE (e.g., `APP_NAME`, `DB_HOST`)
- **Files**: 
  - Classes: PascalCase.php (e.g., `Auth.php`)
  - Scripts: kebab-case.php (e.g., `oauth-setup.php`)

## Database Conventions
- **Tables**: Plural snake_case (e.g., `users`, `social_accounts`, `scheduled_posts`)
- **Columns**: snake_case (e.g., `user_id`, `created_at`, `is_active`)
- **Foreign keys**: Named as `[table]_id` (e.g., `client_id`, `user_id`)
- **Timestamps**: `created_at`, `updated_at` fields on most tables
- **Soft deletes**: Using `deleted_at` field where applicable

## Security Practices
- **Input sanitization**: Using `sanitizeInput()` helper function
- **Output escaping**: `htmlspecialchars()` for all user-generated content
- **CSRF tokens**: Required on all forms
- **Password hashing**: Using `password_hash()` with PASSWORD_DEFAULT

## Frontend Conventions
- **Tailwind classes**: Used directly in HTML
- **Alpine.js**: x-data, x-show, x-if directives for interactivity
- **Icons**: Font Awesome for all icons
- **Dark theme**: Primary UI uses dark/hacker aesthetic
- **Mobile-first**: Responsive design with mobile optimizations

## File Organization
- `/dashboard` - Main application pages
- `/api` - API endpoints
- `/includes` - PHP classes and helpers
- `/assets` - CSS, JS, images
- `/uploads` - User uploaded media
- `/db` - Database schema
- `/installer` - Installation wizard
- `/includes/platforms` - Platform-specific implementations
- `/includes/email-templates` - Email template files
- `/includes/report-templates` - Report template files