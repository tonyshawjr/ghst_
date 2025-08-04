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
- OAuth-based authentication (placeholders ready)
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
    settings.php  # User settings
    switch-client.php # Client management
  /uploads        # User uploaded media
  /api           # API endpoints
  /assets        # CSS, JS, images
  /includes      # PHP includes and classes
    auth.php     # Authentication class
    db.php       # Database singleton
    functions.php # Helper functions
    layout.php   # Layout components
  /db            # Database schema
  index.php      # Entry point
  login.php      # Login page
  logout.php     # Logout handler
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

## Troubleshooting

### Common Issues
1. **Database connection failed**: 
   - For MAMP: Change DB_HOST to '127.0.0.1' instead of 'localhost'
   - Check DB_PORT (usually 8889 for MAMP)

2. **Mobile login issues**:
   - Set `session.cookie_secure` to 0 for HTTP development
   - Use `session.cookie_samesite` = 'Lax' for cross-site compatibility

3. **Cron not running**: Check cPanel error logs

4. **OAuth failures**: Verify redirect URLs match

5. **Upload errors**: Check file permissions (755 for uploads folder)

6. **Token expired**: Re-authenticate in settings

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
- AI-powered content suggestions

## Support
For issues or questions, check the UPDATES.md file for recent changes and known issues.

## License
Proprietary - All rights reserved

---
Built with 💀 by ghst_