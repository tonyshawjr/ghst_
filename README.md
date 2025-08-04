# ghst_ - Multi-Client Social Media Scheduling Tool

## Overview
ghst_ is a powerful multi-client social media scheduling platform designed for agencies and social media managers. Built with a hacker/crypto aesthetic, it provides a stylish dashboard for managing multiple clients' social media accounts, scheduled posts, and media uploads.

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
- Client switching functionality
- Dark mode hacker/crypto aesthetic UI

### 2. Multi-Client Management
- Seamless switching between clients
- Isolated data per client
- Client-specific timezone support

### 3. Social Media Integrations
- Instagram, Facebook, LinkedIn support
- OAuth-based authentication
- Token management with expiry alerts

### 4. Post Scheduler
- Schedule posts to multiple platforms
- Media upload support (images/videos)
- Platform-specific previews
- Draft and scheduled post management

### 5. Calendar View
- Monthly/weekly calendar interface
- Color-coded post statuses
- Drag-and-drop rescheduling

### 6. Media Library
- Client-specific media management
- Thumbnail generation
- Media metadata tracking

### 7. Analytics & Logs
- Post success/failure tracking
- Platform response logging
- Basic analytics dashboard

## Installation

### Requirements
- PHP 8.0 or higher
- MySQL 5.7 or higher
- cPanel hosting account
- SSL certificate (for OAuth)

### Setup Steps

1. **Upload Files**
   ```bash
   # Upload the ghst_ folder to your cPanel home directory
   # Move public_html contents to your web root
   ```

2. **Database Setup**
   ```bash
   # Import the database schema
   mysql -u your_username -p your_database < db/schema.sql
   ```

3. **Configuration**
   - Copy `config.example.php` to `config.php`
   - Update database credentials
   - Set your domain and paths
   - Configure OAuth credentials

4. **Cron Job Setup**
   ```bash
   # Add to cPanel cron jobs (every 5 minutes)
   */5 * * * * /usr/bin/php /home/username/public_html/cron.php
   ```

5. **Permissions**
   ```bash
   chmod 755 public_html/uploads
   chmod 644 config.php
   ```

## Directory Structure
```
/ghst_
  /public_html
    /dashboard      # Main dashboard files
    /uploads        # User uploaded media
    /api           # API endpoints
    /assets        # CSS, JS, images
    index.php      # Entry point
    cron.php       # Scheduled post processor
  /includes        # PHP includes and classes
  /db             # Database schema and migrations
  config.php      # Configuration file
```

## Security Features
- Session-based authentication
- CSRF protection
- Input validation and sanitization
- File upload restrictions
- SQL injection prevention
- XSS protection

## Platform Limitations

| Platform | Character Limit | Media Support | Special Notes |
|----------|----------------|---------------|---------------|
| Instagram | 2,200 chars | Images, Videos (60s) | 30 hashtag limit |
| Facebook | 63,206 chars | Images, Videos | Link previews |
| LinkedIn | 3,000 chars | Images, Videos, Documents | Professional tone |
| Twitter/X | 280 chars | 4 images, 1 video | Thread support |

## Advanced Features
- Post preview by platform
- Timezone handling per client
- Failure recovery system
- Draft management
- Activity audit trail
- Token expiry alerts
- Mobile-responsive design

## Troubleshooting

### Common Issues
1. **Cron not running**: Check cPanel error logs
2. **OAuth failures**: Verify redirect URLs match
3. **Upload errors**: Check file permissions
4. **Token expired**: Re-authenticate in settings

## Future Roadmap
- TikTok integration
- YouTube Shorts support
- Advanced analytics
- White-label options
- API for external integrations

## Support
For issues or questions, check the UPDATES.md file for recent changes and known issues.

## License
Proprietary - All rights reserved

---
Built with 💀 by ghst_