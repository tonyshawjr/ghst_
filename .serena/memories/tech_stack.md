# Tech Stack

## Backend
- **PHP 8+** - Vanilla PHP optimized for cPanel compatibility
- **MySQL 5.7+** - Database for all application data
- **No PHP framework** - Intentionally vanilla for shared hosting compatibility

## Frontend
- **Tailwind CSS** - Utility-first CSS framework
- **Alpine.js** - Lightweight JavaScript framework for interactivity
- **HTML5** - Semantic markup
- **No build process** - All assets served directly

## Authentication
- Email/password authentication
- Google Sign-In (optional OAuth)
- Session-based with proper cookie configuration

## API Integrations
- **Social Platforms**: Facebook, Instagram, LinkedIn, Twitter/X, Threads (OAuth)
- **AI Providers**: Claude (Anthropic) and OpenAI APIs
- **Email**: SMTP/SendGrid for report delivery

## Infrastructure
- Optimized for shared cPanel servers
- cPanel cron jobs for scheduled post execution
- Local file storage (uploads folder)
- No Redis/Memcached requirements
- No Node.js or build tools required

## Development Environment
- MAMP/XAMPP compatible
- Supports both localhost and IP-based development
- Mobile testing friendly with proper session configuration