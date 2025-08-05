# ghst_ - Multi-Client Social Media Scheduling Tool

## Overview
ghst_ is a powerful, mobile-responsive multi-client social media scheduling platform designed for agencies and social media managers. Built with a hacker/crypto aesthetic, it provides a stylish dashboard for managing multiple clients' social media accounts, scheduled posts, and media uploads across desktop, tablet, and mobile devices.

## Tech Stack
- **Backend**: PHP 8+ (vanilla PHP for cPanel compatibility)
- **Database**: MySQL
- **Frontend**: Tailwind CSS, Alpine.js, HTML5
- **Authentication**: Email/password + Google Sign-In (optional)
- **Jobs**: cPanel cron jobs for scheduled post execution
- **Storage**: Local uploads folder (S3 compatible for future)
- **Hosting**: Optimized for shared cPanel servers

## Core Features

### 1. Admin Dashboard
- Secure admin-only login system
- Quick client switching via dropdown
- Dark mode hacker/crypto aesthetic UI
- Mobile-responsive design with bottom navigation
- Real-time statistics and activity tracking

### 2. Multi-Client Management
- Create and delete clients through UI
- Seamless switching between clients
- Isolated data per client
- Client-specific timezone support
- Smart deletion logic (soft delete with data, hard delete without)

### 3. Social Media Integrations
- Instagram, Facebook, LinkedIn, Twitter/X, Threads support
- **Dedicated OAuth Setup Page** with step-by-step platform configuration
- **Conditional OAuth Setup** - only appears in menu until all platforms configured
- OAuth-based authentication with comprehensive setup instructions
- Token management with expiry alerts
- Platform-specific character limits and validation

### 4. Post Scheduler
- Schedule posts to multiple platforms simultaneously
- Media upload support (images/videos)
- Platform-specific previews and character counting
- Draft and scheduled post management
- Mobile-optimized post creation with floating action button (FAB)

### 5. Calendar View
- Monthly/weekly calendar interface
- Color-coded post statuses by platform
- Drag-and-drop rescheduling
- Mobile-optimized compact view
- Touch-friendly date selection

### 6. Media Library
- Client-specific media management
- Drag-and-drop file uploads
- Thumbnail generation
- Media metadata tracking
- Storage usage statistics

### 7. Analytics & Logs
- Post success/failure tracking
- Platform response logging
- Interactive analytics dashboard with charts
- Performance metrics and engagement tracking
- Activity audit trail

### 8. Settings & Configuration
- **Tabbed Settings Interface** with Profile, System Info, and Reset & Reinstall tabs
- **Client Settings Management** - update client name, timezone, and notes
- **Password Management** - secure password changes with validation
- **Complete Reset Functionality** - wipe all data and reinstall cleanly
- **Foreign Key Safe Reset** - proper table deletion order without constraint errors
- **System Information Display** - view app configuration and database details

### 9. AI-Powered Content Suggestions
- **Multi-Provider Support** - Configure both Claude (Anthropic) and OpenAI simultaneously
- **Provider Selection** - Choose between Claude or ChatGPT for each generation
- **User-Provided API Keys** - Users supply their own keys at their own cost
- **Platform-Specific Content** - Optimized suggestions for each social platform
- **Customizable Generation** - Tone, length, hashtags, and emoji options
- **Real-Time Generation** - Instant AI-powered content creation
- **Usage Tracking** - Monitor API usage and costs
- **Seamless Integration** - Direct content transfer to post scheduler

## Installation

### Requirements
- PHP 8.0 or higher
- MySQL 5.7 or higher (port 8889 for MAMP)
- cPanel hosting account (or any PHP hosting)
- SSL certificate (for OAuth in production)

### Setup Steps

1. **Upload Files**
   ```bash
   # Upload all files to your domain/subdomain root directory
   # No need to move files - everything is already in the correct structure
   ```

2. **Database Setup**
   ```bash
   # Import the database schema
   mysql -u your_username -p your_database < db/schema.sql
   ```

3. **Configuration**
   - Copy `config.example.php` to `config.php`
   - Update database credentials
   - For MAMP: Set DB_HOST to '127.0.0.1' and DB_PORT to 8889
   - Set your domain and paths
   - Configure OAuth credentials when ready
   - For development: session.cookie_secure = 0

4. **Cron Job Setup**
   ```bash
   # Add to cPanel cron jobs (every 5 minutes)
   */5 * * * * /usr/bin/php /path/to/your/domain/cron.php
   ```

5. **Permissions**
   ```bash
   chmod 755 uploads
   chmod 644 config.php
   ```

## Directory Structure
```
/ghst_
  /dashboard      # Main dashboard files
    index.php     # Dashboard home
    posts.php     # Post management
    calendar.php  # Calendar view
    media.php     # Media library
    analytics.php # Analytics dashboard
    accounts.php  # Social accounts
    settings.php  # User settings (Profile, System Info, Reset)
    oauth-setup.php # OAuth platform configuration (conditional)
    ai-suggestions.php # AI content generation
    switch-client.php # Client management
  /uploads        # User uploaded media
  /api           # API endpoints
  /assets        # CSS, JS, images
  /includes      # PHP includes and classes
    auth.php     # Authentication class
    db.php       # Database singleton
    functions.php # Helper functions
    layout.php   # Layout components
    AIContentSuggestions.php # AI integration class
  /db            # Database schema
  /installer     # Installation wizard steps
  index.php      # Entry point
  login.php      # Login page
  logout.php     # Logout handler
  installer.php  # Complete setup installer
  cron.php       # Scheduled post processor
  config.php     # Configuration file
  .htaccess      # Security rules
```

## Security Features
- Session-based authentication with proper cookie configuration
- CSRF protection on all forms
- Input validation and sanitization
- File upload restrictions (type and size)
- SQL injection prevention via prepared statements
- XSS protection through output encoding
- Directory access restrictions via .htaccess
- Protected configuration files

## Platform Limitations

| Platform | Character Limit | Media Support | Special Notes |
|----------|----------------|---------------|---------------|
| Instagram | 2,200 chars | Images, Videos (60s) | 30 hashtag limit |
| Facebook | 63,206 chars | Images, Videos | Link previews |
| LinkedIn | 3,000 chars | Images, Videos, Documents | Professional tone |
| Twitter/X | 280 chars | 4 images, 1 video | Thread support |
| Threads | 500 chars | Images, Videos | Instagram integration |

## Advanced Features
- Post preview by platform with real-time character counting
- Timezone handling per client
- Failure recovery system with retry queue
- Draft management
- Activity audit trail
- Token expiry alerts
- Mobile-first responsive design
- Touch gestures (swipe actions, pull-to-refresh)
- Floating Action Button (FAB) for quick posts
- Bottom navigation bar (mobile)
- Haptic feedback support
- Safe area compatibility (iPhone notch, etc.)

## OAuth Setup & Installation
- **Comprehensive 4-Step Installer** - database, admin account, OAuth, completion
- **Smart OAuth Workflow** - setup page only appears until all platforms configured
- **Visual Setup Progress** - clear indicators for configured vs setup needed platforms
- **Developer Console Links** - direct access to platform developer pages
- **Automatic Menu Updates** - OAuth setup disappears when configuration complete
- **Complete Reset Feature** - safely wipe all data and restart installation

## Troubleshooting

### Common Issues
1. **Database connection failed**: 
   - Change DB_HOST to '127.0.0.1' instead of 'localhost' if needed
   - Check DB_PORT (usually 3306, 8889 for MAMP)

2. **Admin account creation failed**:
   - Ensure database schema uses `password_hash` column, not `password`
   - Run installer which handles proper column names

3. **Reset installation fails**:
   - Check for foreign key constraint errors
   - Use the built-in reset feature which handles table deletion order properly

4. **OAuth setup issues**:
   - Use the dedicated OAuth setup page (appears in menu when needed)
   - Verify redirect URLs match exactly with platform developer settings
   - Check that all three platforms (Facebook, Twitter, LinkedIn) are configured

5. **Mobile login issues**:
   - Set `session.cookie_secure` to 0 for HTTP development
   - Use `session.cookie_samesite` = 'Lax' for cross-site compatibility

6. **Settings page issues**:
   - Client settings should only appear in Profile tab
   - OAuth setup should not be in settings (it has its own page)

7. **Upload errors**: Check file permissions (755 for uploads folder)

8. **Token expired**: Re-authenticate through OAuth setup page

## Mobile Testing

To test on mobile devices:
```bash
# Start server on all interfaces
php -S 0.0.0.0:8000

# Access from mobile device using computer's IP
http://YOUR_COMPUTER_IP:8000
```

## Future Roadmap
- TikTok integration
- YouTube Shorts support
- Advanced analytics with export
- White-label options
- API for external integrations
- Team collaboration features
- A/B testing for posts
- AI image generation
- Brand voice training
- Competitor analysis
- Trending topic integration
- Multi-language support

## Support

For issues or questions, check the UPDATES.md file for recent changes and known issues.

## License

Proprietary - All rights reserved

---

Built with 💀 by ghst_