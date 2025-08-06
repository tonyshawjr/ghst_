-- ghst_ Database Schema
-- Version: 1.0
-- Created: 2025-08-04

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET time_zone = "+00:00";

-- Database creation (optional, remove if database already exists)
-- CREATE DATABASE IF NOT EXISTS `ghst_db` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
-- USE `ghst_db`;

-- --------------------------------------------------------

-- Table structure for `users` (Admin users only)
CREATE TABLE `users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `email` varchar(255) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `name` varchar(100) NOT NULL,
  `google_id` varchar(255) DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `email` (`email`),
  KEY `google_id` (`google_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

-- Table structure for `settings` (User-level settings)
CREATE TABLE `settings` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `setting_key` varchar(100) NOT NULL,
  `setting_value` text,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_user_setting` (`user_id`, `setting_key`),
  KEY `user_id` (`user_id`),
  KEY `setting_key` (`setting_key`),
  CONSTRAINT `fk_settings_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

-- Table structure for `clients`
CREATE TABLE `clients` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `timezone` varchar(50) DEFAULT 'UTC',
  `claude_api_key` text DEFAULT NULL,
  `claude_model` varchar(100) DEFAULT NULL,
  `openai_api_key` text DEFAULT NULL,
  `openai_model` varchar(100) DEFAULT NULL,
  `notes` text,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `is_active` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

-- Table structure for `client_settings` (Client-level settings for OAuth, etc.)
CREATE TABLE `client_settings` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `client_id` int(11) NOT NULL,
  `setting_key` varchar(100) NOT NULL,
  `setting_value` text,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_client_setting` (`client_id`, `setting_key`),
  KEY `client_id` (`client_id`),
  KEY `setting_key` (`setting_key`),
  CONSTRAINT `fk_client_settings_client` FOREIGN KEY (`client_id`) REFERENCES `clients` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

-- Table structure for `brands` (Sub-clients/brands per client)
CREATE TABLE `brands` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `client_id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `description` text,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `client_id` (`client_id`),
  CONSTRAINT `fk_brands_client` FOREIGN KEY (`client_id`) REFERENCES `clients` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

-- Table structure for `accounts` (Social media accounts)
CREATE TABLE `accounts` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `client_id` int(11) NOT NULL,
  `brand_id` int(11) DEFAULT NULL,
  `platform` enum('instagram','facebook','facebook_page','linkedin','twitter','threads') NOT NULL,
  `platform_user_id` varchar(255) NOT NULL,
  `username` varchar(255) NOT NULL,
  `display_name` varchar(255) DEFAULT NULL,
  `account_id` varchar(255) DEFAULT NULL,
  `access_token` text NOT NULL,
  `refresh_token` text,
  `expires_at` datetime DEFAULT NULL,
  `token_expires_at` datetime DEFAULT NULL,
  `account_data` json DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `last_verified` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `client_id` (`client_id`),
  KEY `brand_id` (`brand_id`),
  KEY `platform` (`platform`),
  KEY `expires_at` (`expires_at`),
  KEY `platform_user` (`platform`, `platform_user_id`),
  KEY `token_expiry` (`token_expires_at`, `is_active`),
  CONSTRAINT `fk_accounts_client` FOREIGN KEY (`client_id`) REFERENCES `clients` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_accounts_brand` FOREIGN KEY (`brand_id`) REFERENCES `brands` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

-- Table structure for `posts`
CREATE TABLE `posts` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `client_id` int(11) NOT NULL,
  `brand_id` int(11) DEFAULT NULL,
  `content` text NOT NULL,
  `media_url` varchar(500) DEFAULT NULL,
  `scheduled_at` datetime NOT NULL,
  `status` enum('draft','scheduled','publishing','published','failed','cancelled') DEFAULT 'scheduled',
  `platforms_json` json NOT NULL,
  `retry_count` int(11) DEFAULT 0,
  `last_error` text,
  `published_at` datetime DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `client_id` (`client_id`),
  KEY `brand_id` (`brand_id`),
  KEY `scheduled_at` (`scheduled_at`),
  KEY `status` (`status`),
  KEY `created_by` (`created_by`),
  CONSTRAINT `fk_posts_client` FOREIGN KEY (`client_id`) REFERENCES `clients` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_posts_brand` FOREIGN KEY (`brand_id`) REFERENCES `brands` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_posts_user` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

-- Table structure for `media`
CREATE TABLE `media` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `client_id` int(11) NOT NULL,
  `brand_id` int(11) DEFAULT NULL,
  `file_url` varchar(500) NOT NULL,
  `file_name` varchar(255) NOT NULL,
  `file_size` int(11) NOT NULL,
  `mime_type` varchar(100) NOT NULL,
  `type` enum('image','video','document') NOT NULL,
  `width` int(11) DEFAULT NULL,
  `height` int(11) DEFAULT NULL,
  `duration` int(11) DEFAULT NULL,
  `thumbnail_url` varchar(500) DEFAULT NULL,
  `uploaded_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `client_id` (`client_id`),
  KEY `brand_id` (`brand_id`),
  KEY `type` (`type`),
  KEY `uploaded_by` (`uploaded_by`),
  CONSTRAINT `fk_media_client` FOREIGN KEY (`client_id`) REFERENCES `clients` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_media_brand` FOREIGN KEY (`brand_id`) REFERENCES `brands` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_media_user` FOREIGN KEY (`uploaded_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

-- Table structure for `logs`
CREATE TABLE `logs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `client_id` int(11) DEFAULT NULL,
  `post_id` int(11) DEFAULT NULL,
  `action` varchar(100) NOT NULL,
  `level` enum('debug','info','warning','error') DEFAULT 'info',
  `status` enum('success','warning','error','info') NOT NULL,
  `message` text NOT NULL,
  `platform` varchar(50) DEFAULT NULL,
  `details` json DEFAULT NULL,
  `context` json DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `client_id` (`client_id`),
  KEY `post_id` (`post_id`),
  KEY `status` (`status`),
  KEY `created_at` (`created_at`),
  KEY `level` (`level`, `created_at`),
  CONSTRAINT `fk_logs_client` FOREIGN KEY (`client_id`) REFERENCES `clients` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_logs_post` FOREIGN KEY (`post_id`) REFERENCES `posts` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

-- Table structure for `user_actions` (Audit trail)
CREATE TABLE `user_actions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `client_id` int(11) DEFAULT NULL,
  `action_type` varchar(100) NOT NULL,
  `description` text NOT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  KEY `client_id` (`client_id`),
  KEY `action_type` (`action_type`),
  KEY `created_at` (`created_at`),
  CONSTRAINT `fk_actions_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_actions_client` FOREIGN KEY (`client_id`) REFERENCES `clients` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

-- Table structure for `sessions` (PHP sessions)
CREATE TABLE `sessions` (
  `id` varchar(128) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `client_id` int(11) DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text,
  `payload` text NOT NULL,
  `last_activity` int(11) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  KEY `last_activity` (`last_activity`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

-- Table structure for `retry_queue` (Failed post retry)
CREATE TABLE `retry_queue` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `post_id` int(11) NOT NULL,
  `platform` varchar(50) NOT NULL,
  `retry_after` datetime NOT NULL,
  `attempts` int(11) DEFAULT 0,
  `max_attempts` int(11) DEFAULT 3,
  `last_error` text,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `post_id` (`post_id`),
  KEY `retry_after` (`retry_after`),
  CONSTRAINT `fk_retry_post` FOREIGN KEY (`post_id`) REFERENCES `posts` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

-- Table structure for `platform_limits` (Platform-specific limitations)
CREATE TABLE `platform_limits` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `platform` varchar(50) NOT NULL,
  `char_limit` int(11) DEFAULT NULL,
  `hashtag_limit` int(11) DEFAULT NULL,
  `image_max_size` int(11) DEFAULT NULL,
  `video_max_size` int(11) DEFAULT NULL,
  `video_max_duration` int(11) DEFAULT NULL,
  `supported_formats` json DEFAULT NULL,
  `aspect_ratios` json DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `platform` (`platform`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

-- Insert default platform limits
INSERT INTO `platform_limits` (`platform`, `char_limit`, `hashtag_limit`, `image_max_size`, `video_max_size`, `video_max_duration`, `supported_formats`, `aspect_ratios`) VALUES
('instagram', 2200, 30, 8388608, 104857600, 60, '["jpg", "jpeg", "png", "mp4", "mov"]', '{"feed": ["1:1", "4:5"], "stories": ["9:16"], "reels": ["9:16"]}'),
('facebook', 63206, NULL, 10485760, 1073741824, 240, '["jpg", "jpeg", "png", "gif", "mp4", "mov"]', '{"feed": ["16:9", "1:1", "4:5"], "stories": ["9:16"]}'),
('linkedin', 3000, NULL, 10485760, 5368709120, 600, '["jpg", "jpeg", "png", "gif", "mp4"]', '{"feed": ["16:9", "1:1"]}'),
('twitter', 280, NULL, 5242880, 536870912, 140, '["jpg", "jpeg", "png", "gif", "mp4"]', '{"feed": ["16:9", "1:1"]}'),
('threads', 500, NULL, 8388608, 104857600, 60, '["jpg", "jpeg", "png", "mp4", "mov"]', '{"feed": ["1:1", "4:5", "16:9"]}');

-- --------------------------------------------------------

-- Table structure for `ai_usage_logs`
CREATE TABLE `ai_usage_logs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `client_id` int(11) NOT NULL,
  `provider` varchar(50) NOT NULL,
  `model` varchar(100) DEFAULT NULL,
  `tokens_used` int(11) DEFAULT 0,
  `cost` decimal(10,4) DEFAULT 0.0000,
  `request_type` varchar(50) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `client_id` (`client_id`),
  KEY `idx_client_provider` (`client_id`, `provider`),
  KEY `idx_created` (`created_at`),
  CONSTRAINT `fk_ai_logs_client` FOREIGN KEY (`client_id`) REFERENCES `clients` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

-- Table structure for `ai_suggestions`
CREATE TABLE `ai_suggestions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `client_id` int(11) NOT NULL,
  `platform` varchar(50) DEFAULT NULL,
  `topic` text,
  `tone` varchar(50) DEFAULT NULL,
  `suggestions` json DEFAULT NULL,
  `selected_index` int(11) DEFAULT NULL,
  `used_in_post_id` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `client_id` (`client_id`),
  KEY `used_in_post_id` (`used_in_post_id`),
  KEY `idx_client_created` (`client_id`, `created_at`),
  CONSTRAINT `fk_ai_suggestions_client` FOREIGN KEY (`client_id`) REFERENCES `clients` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_ai_suggestions_post` FOREIGN KEY (`used_in_post_id`) REFERENCES `posts` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

-- Table structure for `webhook_logs`
CREATE TABLE `webhook_logs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `platform` varchar(50) NOT NULL,
  `event_type` varchar(100) DEFAULT NULL,
  `payload` text,
  `processed` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_platform_created` (`platform`, `created_at`),
  KEY `idx_processed` (`processed`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

-- Table structure for `notifications`
CREATE TABLE `notifications` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `client_id` int(11) NOT NULL,
  `type` varchar(50) NOT NULL,
  `platform` varchar(50) NOT NULL,
  `title` varchar(255) DEFAULT NULL,
  `message` text,
  `data` json DEFAULT NULL,
  `is_read` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `client_id` (`client_id`),
  KEY `idx_client_read` (`client_id`, `is_read`),
  KEY `idx_created` (`created_at`),
  CONSTRAINT `fk_notifications_client` FOREIGN KEY (`client_id`) REFERENCES `clients` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

-- Table structure for `post_metrics`
CREATE TABLE `post_metrics` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `post_id` int(11) NOT NULL,
  `platform` varchar(50) NOT NULL,
  `metric_name` varchar(50) NOT NULL,
  `metric_value` int(11) DEFAULT 0,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_post_platform_metric` (`post_id`, `platform`, `metric_name`),
  KEY `post_id` (`post_id`),
  KEY `idx_post_platform` (`post_id`, `platform`),
  KEY `idx_updated` (`updated_at`),
  CONSTRAINT `fk_metrics_post` FOREIGN KEY (`post_id`) REFERENCES `posts` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

-- Table structure for `post_reactions`
CREATE TABLE `post_reactions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `platform` varchar(50) NOT NULL,
  `post_id` varchar(255) NOT NULL,
  `reaction_type` varchar(50) DEFAULT NULL,
  `user_id` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_platform_post` (`platform`, `post_id`),
  KEY `idx_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

-- Table structure for `analytics`
CREATE TABLE `analytics` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `client_id` int(11) NOT NULL,
  `platform` varchar(50) NOT NULL,
  `post_id` varchar(255) DEFAULT NULL,
  `metric_name` varchar(100) NOT NULL,
  `metric_value` decimal(15,2) DEFAULT NULL,
  `metric_data` json DEFAULT NULL,
  `recorded_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `client_id` (`client_id`),
  KEY `idx_client_platform` (`client_id`, `platform`),
  KEY `idx_post` (`post_id`),
  KEY `idx_recorded` (`recorded_at`),
  CONSTRAINT `fk_analytics_client` FOREIGN KEY (`client_id`) REFERENCES `clients` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

-- SECTION: BRANDING SETTINGS
-- --------------------------------------------------------

-- Table for client branding settings
CREATE TABLE `client_branding` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `client_id` int(11) NOT NULL,
  `business_name` varchar(255) DEFAULT NULL,
  `tagline` varchar(500) DEFAULT NULL,
  `website` varchar(255) DEFAULT NULL,
  `email` varchar(255) DEFAULT NULL,
  `phone` varchar(50) DEFAULT NULL,
  `logo_path` varchar(500) DEFAULT NULL,
  `primary_color` varchar(7) DEFAULT '#8B5CF6',
  `secondary_color` varchar(7) DEFAULT '#1F2937',
  `accent_color` varchar(7) DEFAULT '#10B981',
  `email_signature` text,
  `report_header` text,
  `report_footer` text,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `client_id` (`client_id`),
  CONSTRAINT `fk_branding_client` FOREIGN KEY (`client_id`) REFERENCES `clients` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

-- SECTION: ANALYTICS TABLES
-- --------------------------------------------------------

-- Enhanced post analytics with engagement metrics
CREATE TABLE `post_analytics` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `post_id` int(11) NOT NULL,
  `platform` varchar(50) NOT NULL,
  `impressions` int(11) DEFAULT 0,
  `reach` int(11) DEFAULT 0,
  `engagement_rate` decimal(5,2) DEFAULT 0.00,
  `clicks` int(11) DEFAULT 0,
  `shares` int(11) DEFAULT 0,
  `comments` int(11) DEFAULT 0,
  `likes` int(11) DEFAULT 0,
  `saves` int(11) DEFAULT 0,
  `reactions` json DEFAULT NULL,
  `video_views` int(11) DEFAULT 0,
  `video_completion_rate` decimal(5,2) DEFAULT 0.00,
  `story_exits` int(11) DEFAULT 0,
  `story_replies` int(11) DEFAULT 0,
  `demographic_data` json DEFAULT NULL,
  `collected_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_post_platform` (`post_id`, `platform`),
  KEY `post_id` (`post_id`),
  KEY `platform` (`platform`),
  KEY `engagement_rate` (`engagement_rate`),
  KEY `collected_at` (`collected_at`),
  CONSTRAINT `fk_post_analytics_post` FOREIGN KEY (`post_id`) REFERENCES `posts` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Follower growth tracking
CREATE TABLE `follower_analytics` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `account_id` int(11) NOT NULL,
  `follower_count` int(11) DEFAULT 0,
  `following_count` int(11) DEFAULT 0,
  `daily_growth` int(11) DEFAULT 0,
  `weekly_growth` int(11) DEFAULT 0,
  `monthly_growth` int(11) DEFAULT 0,
  `growth_rate` decimal(5,2) DEFAULT 0.00,
  `demographics` json DEFAULT NULL,
  `top_locations` json DEFAULT NULL,
  `active_hours` json DEFAULT NULL,
  `recorded_date` date NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_account_date` (`account_id`, `recorded_date`),
  KEY `account_id` (`account_id`),
  KEY `recorded_date` (`recorded_date`),
  CONSTRAINT `fk_follower_analytics_account` FOREIGN KEY (`account_id`) REFERENCES `accounts` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Best posting times analysis
CREATE TABLE `posting_time_analytics` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `client_id` int(11) NOT NULL,
  `platform` varchar(50) NOT NULL,
  `day_of_week` tinyint(1) NOT NULL,
  `hour` tinyint(2) NOT NULL,
  `engagement_score` decimal(10,2) DEFAULT 0.00,
  `post_count` int(11) DEFAULT 0,
  `avg_impressions` decimal(10,2) DEFAULT 0.00,
  `avg_engagement_rate` decimal(5,2) DEFAULT 0.00,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_client_platform_time` (`client_id`, `platform`, `day_of_week`, `hour`),
  KEY `client_id` (`client_id`),
  KEY `platform` (`platform`),
  KEY `engagement_score` (`engagement_score`),
  CONSTRAINT `fk_posting_time_client` FOREIGN KEY (`client_id`) REFERENCES `clients` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Hashtag performance tracking
CREATE TABLE `hashtag_analytics` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `client_id` int(11) NOT NULL,
  `hashtag` varchar(100) NOT NULL,
  `platform` varchar(50) NOT NULL,
  `usage_count` int(11) DEFAULT 0,
  `total_impressions` int(11) DEFAULT 0,
  `total_engagement` int(11) DEFAULT 0,
  `avg_engagement_rate` decimal(5,2) DEFAULT 0.00,
  `trending_score` decimal(5,2) DEFAULT 0.00,
  `sentiment` enum('positive','neutral','negative') DEFAULT 'neutral',
  `last_used` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_client_hashtag_platform` (`client_id`, `hashtag`, `platform`),
  KEY `client_id` (`client_id`),
  KEY `platform` (`platform`),
  KEY `trending_score` (`trending_score`),
  KEY `hashtag` (`hashtag`),
  CONSTRAINT `fk_hashtag_analytics_client` FOREIGN KEY (`client_id`) REFERENCES `clients` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Content type performance
CREATE TABLE `content_type_analytics` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `client_id` int(11) NOT NULL,
  `platform` varchar(50) NOT NULL,
  `content_type` varchar(50) NOT NULL,
  `post_count` int(11) DEFAULT 0,
  `avg_impressions` decimal(10,2) DEFAULT 0.00,
  `avg_engagement_rate` decimal(5,2) DEFAULT 0.00,
  `avg_reach` decimal(10,2) DEFAULT 0.00,
  `total_engagement` int(11) DEFAULT 0,
  `performance_score` decimal(5,2) DEFAULT 0.00,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_client_platform_type` (`client_id`, `platform`, `content_type`),
  KEY `client_id` (`client_id`),
  KEY `platform` (`platform`),
  KEY `performance_score` (`performance_score`),
  CONSTRAINT `fk_content_type_client` FOREIGN KEY (`client_id`) REFERENCES `clients` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Platform comparison metrics
CREATE TABLE `platform_comparison` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `client_id` int(11) NOT NULL,
  `platform` varchar(50) NOT NULL,
  `metric_date` date NOT NULL,
  `total_posts` int(11) DEFAULT 0,
  `total_impressions` int(11) DEFAULT 0,
  `total_reach` int(11) DEFAULT 0,
  `total_engagement` int(11) DEFAULT 0,
  `avg_engagement_rate` decimal(5,2) DEFAULT 0.00,
  `follower_growth` int(11) DEFAULT 0,
  `best_performing_content` json DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_client_platform_date` (`client_id`, `platform`, `metric_date`),
  KEY `client_id` (`client_id`),
  KEY `platform` (`platform`),
  KEY `metric_date` (`metric_date`),
  CONSTRAINT `fk_platform_comparison_client` FOREIGN KEY (`client_id`) REFERENCES `clients` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- UTM campaign tracking
CREATE TABLE `utm_campaigns` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `client_id` int(11) NOT NULL,
  `post_id` int(11) DEFAULT NULL,
  `campaign_name` varchar(255) NOT NULL,
  `utm_source` varchar(100) DEFAULT NULL,
  `utm_medium` varchar(100) DEFAULT NULL,
  `utm_campaign` varchar(255) DEFAULT NULL,
  `utm_term` varchar(255) DEFAULT NULL,
  `utm_content` varchar(255) DEFAULT NULL,
  `clicks` int(11) DEFAULT 0,
  `conversions` int(11) DEFAULT 0,
  `revenue` decimal(10,2) DEFAULT 0.00,
  `roi` decimal(10,2) DEFAULT 0.00,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `client_id` (`client_id`),
  KEY `post_id` (`post_id`),
  KEY `campaign_name` (`campaign_name`),
  KEY `roi` (`roi`),
  CONSTRAINT `fk_utm_client` FOREIGN KEY (`client_id`) REFERENCES `clients` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_utm_post` FOREIGN KEY (`post_id`) REFERENCES `posts` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Audience demographics
CREATE TABLE `audience_demographics` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `account_id` int(11) NOT NULL,
  `demographic_type` enum('age','gender','location','device','interests') NOT NULL,
  `demographic_value` varchar(100) NOT NULL,
  `percentage` decimal(5,2) DEFAULT 0.00,
  `count` int(11) DEFAULT 0,
  `collected_date` date NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `account_id` (`account_id`),
  KEY `demographic_type` (`demographic_type`),
  KEY `collected_date` (`collected_date`),
  CONSTRAINT `fk_demographics_account` FOREIGN KEY (`account_id`) REFERENCES `accounts` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Competitor tracking
CREATE TABLE `competitor_tracking` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `client_id` int(11) NOT NULL,
  `competitor_name` varchar(255) NOT NULL,
  `platform` varchar(50) NOT NULL,
  `username` varchar(255) DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `client_id` (`client_id`),
  KEY `platform` (`platform`),
  CONSTRAINT `fk_competitor_client` FOREIGN KEY (`client_id`) REFERENCES `clients` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Competitor snapshots
CREATE TABLE `competitor_snapshots` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `competitor_id` int(11) NOT NULL,
  `follower_count` int(11) DEFAULT 0,
  `following_count` int(11) DEFAULT 0,
  `post_count` int(11) DEFAULT 0,
  `avg_engagement_rate` decimal(5,2) DEFAULT 0.00,
  `avg_likes` int(11) DEFAULT 0,
  `avg_comments` int(11) DEFAULT 0,
  `posting_frequency` decimal(5,2) DEFAULT 0.00,
  `top_hashtags` json DEFAULT NULL,
  `content_types` json DEFAULT NULL,
  `snapshot_date` date NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_competitor_date` (`competitor_id`, `snapshot_date`),
  KEY `competitor_id` (`competitor_id`),
  KEY `snapshot_date` (`snapshot_date`),
  CONSTRAINT `fk_snapshot_competitor` FOREIGN KEY (`competitor_id`) REFERENCES `competitor_tracking` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Conversion tracking
CREATE TABLE `conversion_tracking` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `client_id` int(11) NOT NULL,
  `post_id` int(11) DEFAULT NULL,
  `conversion_type` varchar(50) NOT NULL,
  `conversion_value` decimal(10,2) DEFAULT 0.00,
  `currency` varchar(3) DEFAULT 'USD',
  `attribution_model` varchar(50) DEFAULT 'last_click',
  `source_platform` varchar(50) DEFAULT NULL,
  `converted_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `client_id` (`client_id`),
  KEY `post_id` (`post_id`),
  KEY `conversion_type` (`conversion_type`),
  KEY `converted_at` (`converted_at`),
  CONSTRAINT `fk_conversion_client` FOREIGN KEY (`client_id`) REFERENCES `clients` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_conversion_post` FOREIGN KEY (`post_id`) REFERENCES `posts` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Social commerce metrics
CREATE TABLE `social_commerce_metrics` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `client_id` int(11) NOT NULL,
  `platform` varchar(50) NOT NULL,
  `product_id` varchar(255) DEFAULT NULL,
  `product_name` varchar(500) DEFAULT NULL,
  `product_clicks` int(11) DEFAULT 0,
  `add_to_carts` int(11) DEFAULT 0,
  `purchases` int(11) DEFAULT 0,
  `revenue` decimal(10,2) DEFAULT 0.00,
  `currency` varchar(3) DEFAULT 'USD',
  `metric_date` date NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `client_id` (`client_id`),
  KEY `platform` (`platform`),
  KEY `metric_date` (`metric_date`),
  KEY `revenue` (`revenue`),
  CONSTRAINT `fk_commerce_client` FOREIGN KEY (`client_id`) REFERENCES `clients` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

-- SECTION: REPORT MANAGEMENT
-- --------------------------------------------------------

-- Report templates
CREATE TABLE `report_templates` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `description` text,
  `template_type` enum('executive_summary','detailed_analytics','social_performance','custom') NOT NULL,
  `sections` json DEFAULT NULL,
  `is_default` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `template_type` (`template_type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Generated reports
CREATE TABLE `generated_reports` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `client_id` int(11) NOT NULL,
  `report_name` varchar(255) NOT NULL,
  `report_type` varchar(50) NOT NULL,
  `period_type` enum('daily','weekly','monthly','quarterly','yearly','custom') NOT NULL,
  `start_date` date NOT NULL,
  `end_date` date NOT NULL,
  `file_path` varchar(500) DEFAULT NULL,
  `file_size` int(11) DEFAULT NULL,
  `format` enum('html','pdf','csv','json') DEFAULT 'html',
  `status` enum('pending','generating','completed','failed') DEFAULT 'pending',
  `metadata` json DEFAULT NULL,
  `generated_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `completed_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `client_id` (`client_id`),
  KEY `status` (`status`),
  KEY `created_at` (`created_at`),
  KEY `generated_by` (`generated_by`),
  CONSTRAINT `fk_reports_client` FOREIGN KEY (`client_id`) REFERENCES `clients` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_reports_user` FOREIGN KEY (`generated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Shareable report links
CREATE TABLE `shareable_reports` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `report_id` int(11) NOT NULL,
  `share_token` varchar(255) NOT NULL,
  `expires_at` timestamp NULL DEFAULT NULL,
  `password_hash` varchar(255) DEFAULT NULL,
  `download_allowed` tinyint(1) DEFAULT 1,
  `view_count` int(11) DEFAULT 0,
  `download_count` int(11) DEFAULT 0,
  `ip_restrictions` text,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `last_accessed` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `share_token` (`share_token`),
  KEY `report_id` (`report_id`),
  KEY `expires_at` (`expires_at`),
  KEY `is_active` (`is_active`),
  CONSTRAINT `fk_shareable_report` FOREIGN KEY (`report_id`) REFERENCES `generated_reports` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Report email logs
CREATE TABLE `report_email_logs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `report_id` int(11) NOT NULL,
  `recipient_email` varchar(255) NOT NULL,
  `subject` varchar(500) DEFAULT NULL,
  `status` enum('pending','sent','failed','bounced','opened','clicked') DEFAULT 'pending',
  `sent_at` timestamp NULL DEFAULT NULL,
  `opened_at` timestamp NULL DEFAULT NULL,
  `clicked_at` timestamp NULL DEFAULT NULL,
  `error_message` text,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `report_id` (`report_id`),
  KEY `recipient_email` (`recipient_email`),
  KEY `status` (`status`),
  KEY `sent_at` (`sent_at`),
  CONSTRAINT `fk_email_report` FOREIGN KEY (`report_id`) REFERENCES `generated_reports` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

-- SECTION: EMAIL SYSTEM
-- --------------------------------------------------------

-- Email configuration per client
CREATE TABLE `email_settings` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `client_id` int(11) NOT NULL,
  `provider` enum('smtp','sendgrid','ses','mailgun') DEFAULT 'smtp',
  `smtp_host` varchar(255) DEFAULT NULL,
  `smtp_port` int(11) DEFAULT 587,
  `smtp_username` varchar(255) DEFAULT NULL,
  `smtp_password` text,
  `smtp_encryption` enum('tls','ssl','none') DEFAULT 'tls',
  `api_key` text,
  `from_email` varchar(255) DEFAULT NULL,
  `from_name` varchar(255) DEFAULT NULL,
  `reply_to` varchar(255) DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `client_id` (`client_id`),
  CONSTRAINT `fk_email_settings_client` FOREIGN KEY (`client_id`) REFERENCES `clients` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Email queue for background processing
CREATE TABLE `email_queue` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `client_id` int(11) NOT NULL,
  `to_email` varchar(255) NOT NULL,
  `cc_emails` text,
  `bcc_emails` text,
  `subject` varchar(500) NOT NULL,
  `body_html` text,
  `body_text` text,
  `attachments` json DEFAULT NULL,
  `priority` tinyint(1) DEFAULT 5,
  `status` enum('pending','processing','sent','failed') DEFAULT 'pending',
  `attempts` int(11) DEFAULT 0,
  `sent_at` timestamp NULL DEFAULT NULL,
  `error_message` text,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `client_id` (`client_id`),
  KEY `status` (`status`),
  KEY `priority` (`priority`),
  KEY `created_at` (`created_at`),
  CONSTRAINT `fk_email_queue_client` FOREIGN KEY (`client_id`) REFERENCES `clients` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Email tracking
CREATE TABLE `email_tracking` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tracking_id` varchar(255) NOT NULL,
  `recipient_email` varchar(255) NOT NULL,
  `subject` varchar(500) DEFAULT NULL,
  `status` enum('pending','sending','sent','failed','delivered','opened','clicked','bounced') DEFAULT 'sent',
  `provider_id` varchar(255) DEFAULT NULL,
  `provider_response` text,
  `open_count` int(11) DEFAULT 0,
  `click_count` int(11) DEFAULT 0,
  `opened_at` datetime DEFAULT NULL,
  `clicked_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `tracking_id` (`tracking_id`),
  KEY `recipient_email` (`recipient_email`),
  KEY `status` (`status`),
  KEY `created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Email click tracking
CREATE TABLE `email_clicks` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tracking_id` varchar(255) NOT NULL,
  `url` varchar(500) NOT NULL,
  `clicked_at` datetime NOT NULL,
  PRIMARY KEY (`id`),
  KEY `tracking_id` (`tracking_id`),
  KEY `clicked_at` (`clicked_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

-- SECTION: PDF GENERATION
-- --------------------------------------------------------

-- PDF generation queue
CREATE TABLE `pdf_queue` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `report_id` int(11) NOT NULL,
  `status` enum('pending','processing','completed','failed') DEFAULT 'pending',
  `priority` tinyint(1) DEFAULT 5,
  `attempts` int(11) DEFAULT 0,
  `error_message` text,
  `started_at` timestamp NULL DEFAULT NULL,
  `completed_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `report_id` (`report_id`),
  KEY `status` (`status`),
  KEY `priority` (`priority`),
  CONSTRAINT `fk_pdf_queue_report` FOREIGN KEY (`report_id`) REFERENCES `generated_reports` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- PDF cache
CREATE TABLE `pdf_cache` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `report_id` int(11) NOT NULL,
  `cache_key` varchar(255) NOT NULL,
  `file_path` varchar(500) NOT NULL,
  `file_size` int(11) DEFAULT NULL,
  `expires_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `cache_key` (`cache_key`),
  KEY `report_id` (`report_id`),
  KEY `expires_at` (`expires_at`),
  CONSTRAINT `fk_pdf_cache_report` FOREIGN KEY (`report_id`) REFERENCES `generated_reports` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

-- SECTION: SYSTEM SETTINGS AND MONITORING
-- --------------------------------------------------------

-- System settings for analytics
CREATE TABLE `system_settings` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `setting_key` varchar(100) NOT NULL,
  `setting_value` text,
  `setting_type` enum('string','integer','boolean','json') DEFAULT 'string',
  `description` text,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `setting_key` (`setting_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- API access logs for analytics
CREATE TABLE `api_access_logs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `client_id` int(11) DEFAULT NULL,
  `endpoint` varchar(255) NOT NULL,
  `method` varchar(10) NOT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text,
  `response_code` int(11) DEFAULT NULL,
  `response_time` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `client_id` (`client_id`),
  KEY `endpoint` (`endpoint`),
  KEY `created_at` (`created_at`),
  CONSTRAINT `fk_api_logs_client` FOREIGN KEY (`client_id`) REFERENCES `clients` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Share analytics tracking
CREATE TABLE `share_analytics` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `shareable_report_id` int(11) NOT NULL,
  `event_type` enum('view','download','password_attempt') NOT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text,
  `referrer` varchar(500) DEFAULT NULL,
  `location_data` json DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `shareable_report_id` (`shareable_report_id`),
  KEY `event_type` (`event_type`),
  KEY `created_at` (`created_at`),
  CONSTRAINT `fk_share_analytics_report` FOREIGN KEY (`shareable_report_id`) REFERENCES `shareable_reports` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

-- Create indexes for performance
CREATE INDEX idx_posts_scheduled ON posts(scheduled_at, status);
CREATE INDEX idx_accounts_expiry ON accounts(expires_at, is_active);
CREATE INDEX idx_logs_date ON logs(created_at);
CREATE INDEX idx_retry_queue ON retry_queue(retry_after, attempts);

-- Analytics performance indexes
CREATE INDEX idx_post_analytics_date ON post_analytics(collected_at);
CREATE INDEX idx_follower_growth ON follower_analytics(growth_rate, recorded_date);
CREATE INDEX idx_hashtag_trending ON hashtag_analytics(trending_score, platform);
CREATE INDEX idx_content_performance ON content_type_analytics(performance_score, platform);
CREATE INDEX idx_utm_roi ON utm_campaigns(roi, created_at);
CREATE INDEX idx_conversion_value ON conversion_tracking(conversion_value, converted_at);
CREATE INDEX idx_commerce_revenue ON social_commerce_metrics(revenue, metric_date);

-- Report indexes
CREATE INDEX idx_reports_client_date ON generated_reports(client_id, created_at);
CREATE INDEX idx_shareable_active ON shareable_reports(is_active, expires_at);
CREATE INDEX idx_email_logs_report ON report_email_logs(report_id, status);

-- Email indexes
CREATE INDEX idx_email_queue_status ON email_queue(status, priority, created_at);
CREATE INDEX idx_email_tracking_token ON email_tracking(tracking_id);

-- --------------------------------------------------------

-- Insert default report templates
INSERT INTO `report_templates` (`name`, `description`, `template_type`, `sections`, `is_default`) VALUES
('Executive Summary', 'High-level overview for executives and stakeholders', 'executive_summary', 
'{"sections": ["overview", "key_metrics", "growth", "top_content", "recommendations"]}', 1),
('Detailed Analytics Report', 'Comprehensive analysis with all metrics', 'detailed_analytics', 
'{"sections": ["overview", "platform_breakdown", "content_analysis", "audience_insights", "hashtag_performance", "competitor_analysis", "recommendations"]}', 1),
('Social Media Performance', 'Platform-specific performance analysis', 'social_performance', 
'{"sections": ["platform_overview", "content_performance", "engagement_trends", "best_times", "audience_demographics"]}', 1);

-- Insert default system settings
INSERT INTO `system_settings` (`setting_key`, `setting_value`, `setting_type`, `description`) VALUES
('analytics_retention_days', '365', 'integer', 'Days to retain analytics data'),
('report_retention_days', '90', 'integer', 'Days to retain generated reports'),
('max_report_size_mb', '50', 'integer', 'Maximum report size in MB'),
('email_tracking_enabled', 'true', 'boolean', 'Enable email open/click tracking'),
('pdf_cache_days', '7', 'integer', 'Days to cache PDF files'),
('share_link_default_expiry_hours', '168', 'integer', 'Default expiry for share links (7 days)');

-- --------------------------------------------------------

-- Create default admin user (password: admin123 - CHANGE THIS!)
INSERT INTO `users` (`email`, `password_hash`, `name`) VALUES
('admin@ghst.app', '$2y$10$YourHashedPasswordHere', 'Admin');

-- Create sample client for testing
INSERT INTO `clients` (`name`, `timezone`, `notes`) VALUES
('Demo Client', 'America/New_York', 'Sample client for testing');

-- --------------------------------------------------------
-- ghst_wrtr: AI Strategy Engine Tables
-- --------------------------------------------------------

-- Main AI campaigns table
CREATE TABLE IF NOT EXISTS `ai_campaigns` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `client_id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `client_name` varchar(255) DEFAULT NULL,
  `goal` varchar(100) DEFAULT NULL,
  `offer_details` text,
  `target_audience` text,
  `brand_voice` varchar(50) DEFAULT NULL,
  `writing_style` varchar(50) DEFAULT NULL,
  `personality_traits` text,
  `campaign_type` varchar(50) DEFAULT NULL,
  `frequency` varchar(50) DEFAULT NULL,
  `duration` int(11) DEFAULT NULL,
  `start_date` date DEFAULT NULL,
  `key_dates` text,
  `ai_provider` varchar(20) DEFAULT 'claude',
  `analytics_data` text,
  `additional_context` text,
  `status` enum('draft','active','paused','completed') DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_client_id` (`client_id`),
  KEY `idx_status` (`status`),
  KEY `idx_created_at` (`created_at`),
  CONSTRAINT `fk_ai_campaigns_client` FOREIGN KEY (`client_id`) REFERENCES `clients` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Campaign strategies storage
CREATE TABLE IF NOT EXISTS `campaign_strategies` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `campaign_id` int(11) NOT NULL,
  `strategy_data` json DEFAULT NULL,
  `version` int(11) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_campaign_id` (`campaign_id`),
  CONSTRAINT `fk_strategies_campaign` FOREIGN KEY (`campaign_id`) REFERENCES `ai_campaigns` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Campaign weekly plans
CREATE TABLE IF NOT EXISTS `campaign_weeks` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `campaign_id` int(11) NOT NULL,
  `week_number` int(11) NOT NULL,
  `theme` varchar(255) DEFAULT NULL,
  `focus_area` text,
  `content_data` json DEFAULT NULL,
  `status` enum('pending','generated','approved','in_progress','completed') DEFAULT 'pending',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_campaign_week` (`campaign_id`, `week_number`),
  CONSTRAINT `fk_weeks_campaign` FOREIGN KEY (`campaign_id`) REFERENCES `ai_campaigns` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Campaign week posts
CREATE TABLE IF NOT EXISTS `campaign_week_posts` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `week_id` int(11) NOT NULL,
  `platform` enum('instagram','facebook','twitter','linkedin','tiktok') NOT NULL,
  `post_content` text NOT NULL,
  `hashtags` text,
  `media_suggestions` text,
  `cta` varchar(255) DEFAULT NULL,
  `optimal_time` time DEFAULT NULL,
  `post_order` int(11) DEFAULT 1,
  `is_pushed_to_scheduler` tinyint(1) DEFAULT 0,
  `scheduler_post_id` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_week_platform` (`week_id`, `platform`),
  KEY `idx_scheduler_link` (`scheduler_post_id`),
  CONSTRAINT `fk_week_posts_week` FOREIGN KEY (`week_id`) REFERENCES `campaign_weeks` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Campaign sharing links
CREATE TABLE IF NOT EXISTS `campaign_share_links` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `campaign_id` int(11) NOT NULL,
  `token` varchar(64) NOT NULL,
  `password_hash` varchar(255) DEFAULT NULL,
  `expires_at` datetime DEFAULT NULL,
  `max_views` int(11) DEFAULT NULL,
  `view_count` int(11) DEFAULT 0,
  `permissions` json DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_by` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `last_accessed` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_token` (`token`),
  KEY `idx_campaign_share` (`campaign_id`),
  KEY `idx_expires` (`expires_at`),
  KEY `idx_active` (`is_active`),
  CONSTRAINT `fk_shares_campaign` FOREIGN KEY (`campaign_id`) REFERENCES `ai_campaigns` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Campaign share access logs
CREATE TABLE IF NOT EXISTS `campaign_share_access_logs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `share_link_id` int(11) NOT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text,
  `accessed_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `action` varchar(50) DEFAULT 'view',
  PRIMARY KEY (`id`),
  KEY `idx_share_link` (`share_link_id`),
  KEY `idx_accessed` (`accessed_at`),
  CONSTRAINT `fk_access_logs_share` FOREIGN KEY (`share_link_id`) REFERENCES `campaign_share_links` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Campaign versions for tracking changes
CREATE TABLE IF NOT EXISTS `campaign_versions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `campaign_id` int(11) NOT NULL,
  `version_number` int(11) NOT NULL,
  `changes_made` text,
  `previous_data` json DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_campaign_version` (`campaign_id`, `version_number`),
  CONSTRAINT `fk_versions_campaign` FOREIGN KEY (`campaign_id`) REFERENCES `ai_campaigns` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Week regeneration history
CREATE TABLE IF NOT EXISTS `week_regeneration_history` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `week_id` int(11) NOT NULL,
  `regeneration_reason` text,
  `user_feedback` text,
  `previous_content` json DEFAULT NULL,
  `new_content` json DEFAULT NULL,
  `regenerated_by` int(11) DEFAULT NULL,
  `regenerated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_week_regen` (`week_id`),
  KEY `idx_regen_date` (`regenerated_at`),
  CONSTRAINT `fk_regen_week` FOREIGN KEY (`week_id`) REFERENCES `campaign_weeks` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Campaign analytics uploads
CREATE TABLE IF NOT EXISTS `campaign_analytics_uploads` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `campaign_id` int(11) NOT NULL,
  `file_name` varchar(255) DEFAULT NULL,
  `file_type` varchar(50) DEFAULT NULL,
  `analytics_data` json DEFAULT NULL,
  `insights_extracted` json DEFAULT NULL,
  `uploaded_by` int(11) DEFAULT NULL,
  `uploaded_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_campaign_analytics` (`campaign_id`),
  KEY `idx_upload_date` (`uploaded_at`),
  CONSTRAINT `fk_analytics_campaign` FOREIGN KEY (`campaign_id`) REFERENCES `ai_campaigns` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Campaign to scheduler post mappings
CREATE TABLE IF NOT EXISTS `campaign_post_mappings` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `campaign_id` int(11) NOT NULL,
  `week_post_id` int(11) NOT NULL,
  `scheduler_post_id` int(11) NOT NULL,
  `pushed_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_campaign_map` (`campaign_id`),
  KEY `idx_week_post_map` (`week_post_id`),
  KEY `idx_scheduler_map` (`scheduler_post_id`),
  CONSTRAINT `fk_map_campaign` FOREIGN KEY (`campaign_id`) REFERENCES `ai_campaigns` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_map_week_post` FOREIGN KEY (`week_post_id`) REFERENCES `campaign_week_posts` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;