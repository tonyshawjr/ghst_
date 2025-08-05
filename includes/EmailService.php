<?php
/**
 * Email Service Class
 * Handles email delivery with multiple providers, templates, and tracking
 */

require_once 'Database.php';
require_once 'exceptions/EmailException.php';

class EmailService {
    private static $instance = null;
    private $db;
    private $config;
    private $provider;
    
    // Email providers
    const PROVIDER_SMTP = 'smtp';
    const PROVIDER_SENDGRID = 'sendgrid';
    const PROVIDER_SES = 'ses';
    
    // Email statuses
    const STATUS_PENDING = 'pending';
    const STATUS_SENDING = 'sending';
    const STATUS_SENT = 'sent';
    const STATUS_FAILED = 'failed';
    const STATUS_DELIVERED = 'delivered';
    const STATUS_OPENED = 'opened';
    const STATUS_CLICKED = 'clicked';
    const STATUS_BOUNCED = 'bounced';
    
    private function __construct() {
        $this->db = Database::getInstance();
        $this->loadConfig();
        $this->initializeProvider();
    }
    
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Load email configuration from database or config file
     */
    private function loadConfig() {
        // Try to load from database first (user settings)
        try {
            $stmt = $this->db->prepare("
                SELECT setting_key, setting_value 
                FROM settings 
                WHERE setting_key LIKE 'email_%'
            ");
            $stmt->execute();
            $settings = $stmt->fetchAll();
            
            $this->config = [];
            foreach ($settings as $setting) {
                $this->config[$setting['setting_key']] = $setting['setting_value'];
            }
            
            // If no settings in DB, use config file defaults
            if (empty($this->config)) {
                $this->loadDefaultConfig();
            }
        } catch (Exception $e) {
            error_log("Failed to load email config from database: " . $e->getMessage());
            $this->loadDefaultConfig();
        }
    }
    
    /**
     * Load default configuration from config.php
     */
    private function loadDefaultConfig() {
        $this->config = [
            'email_provider' => self::PROVIDER_SMTP,
            'email_smtp_host' => SMTP_HOST ?? '',
            'email_smtp_port' => SMTP_PORT ?? 587,
            'email_smtp_user' => SMTP_USER ?? '',
            'email_smtp_pass' => SMTP_PASS ?? '',
            'email_smtp_encryption' => 'tls',
            'email_from_name' => SMTP_FROM_NAME ?? 'ghst_',
            'email_from_email' => SMTP_FROM_EMAIL ?? '',
            'email_reply_to' => SMTP_FROM_EMAIL ?? '',
            'email_tracking_enabled' => '1',
            'email_queue_enabled' => '1',
            'email_max_retries' => '3',
            'email_retry_delay' => '300', // 5 minutes
        ];
    }
    
    /**
     * Initialize the email provider
     */
    private function initializeProvider() {
        $provider = $this->config['email_provider'] ?? self::PROVIDER_SMTP;
        
        switch ($provider) {
            case self::PROVIDER_SENDGRID:
                $this->provider = new SendGridProvider($this->config);
                break;
            case self::PROVIDER_SES:
                $this->provider = new SESProvider($this->config);
                break;
            default:
                $this->provider = new SMTPProvider($this->config);
        }
    }
    
    /**
     * Send email immediately
     */
    public function sendEmail($to, $subject, $htmlBody, $textBody = null, $attachments = [], $options = []) {
        try {
            // Generate tracking ID if tracking is enabled
            $trackingId = $this->generateTrackingId();
            
            // Insert HTML tracking pixel if tracking enabled
            if ($this->isTrackingEnabled()) {
                $htmlBody = $this->insertTrackingPixel($htmlBody, $trackingId);
                $htmlBody = $this->processLinkTracking($htmlBody, $trackingId);
            }
            
            // Prepare email data
            $emailData = [
                'to' => $to,
                'subject' => $subject,
                'html_body' => $htmlBody,
                'text_body' => $textBody ?: $this->htmlToText($htmlBody),
                'attachments' => $attachments,
                'tracking_id' => $trackingId,
                'options' => $options
            ];
            
            // Send via provider
            $result = $this->provider->send($emailData);
            
            // Log the email
            $this->logEmail($emailData, $result);
            
            return $result;
            
        } catch (Exception $e) {
            error_log("Email send failed: " . $e->getMessage());
            throw new EmailException("Failed to send email: " . $e->getMessage());
        }
    }
    
    /**
     * Queue email for later delivery
     */
    public function queueEmail($to, $subject, $htmlBody, $textBody = null, $attachments = [], $options = []) {
        try {
            $trackingId = $this->generateTrackingId();
            
            if ($this->isTrackingEnabled()) {
                $htmlBody = $this->insertTrackingPixel($htmlBody, $trackingId);
                $htmlBody = $this->processLinkTracking($htmlBody, $trackingId);
            }
            
            $stmt = $this->db->prepare("
                INSERT INTO email_queue 
                (tracking_id, recipient_email, recipient_name, subject, html_body, text_body, 
                 attachments, options, status, created_at, scheduled_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), ?)
            ");
            
            $recipientName = is_array($to) ? $to['name'] ?? '' : '';
            $recipientEmail = is_array($to) ? $to['email'] : $to;
            $scheduledAt = $options['scheduled_at'] ?? null;
            
            $stmt->execute([
                $trackingId,
                $recipientEmail,
                $recipientName,
                $subject,
                $htmlBody,
                $textBody ?: $this->htmlToText($htmlBody),
                json_encode($attachments),
                json_encode($options),
                self::STATUS_PENDING,
                $scheduledAt
            ]);
            
            return [
                'success' => true,
                'queue_id' => $this->db->lastInsertId(),
                'tracking_id' => $trackingId
            ];
            
        } catch (Exception $e) {
            error_log("Email queue failed: " . $e->getMessage());
            throw new EmailException("Failed to queue email: " . $e->getMessage());
        }
    }
    
    /**
     * Process email queue
     */
    public function processQueue($limit = 50) {
        try {
            $stmt = $this->db->prepare("
                SELECT * FROM email_queue 
                WHERE status = ? 
                AND (scheduled_at IS NULL OR scheduled_at <= NOW())
                AND (next_retry_at IS NULL OR next_retry_at <= NOW())
                ORDER BY created_at ASC 
                LIMIT ?
            ");
            $stmt->execute([self::STATUS_PENDING, $limit]);
            $emails = $stmt->fetchAll();
            
            $processed = 0;
            $failed = 0;
            
            foreach ($emails as $email) {
                try {
                    // Mark as sending
                    $this->updateQueueStatus($email['id'], self::STATUS_SENDING);
                    
                    // Prepare email data
                    $emailData = [
                        'to' => [
                            'email' => $email['recipient_email'],
                            'name' => $email['recipient_name']
                        ],
                        'subject' => $email['subject'],
                        'html_body' => $email['html_body'],
                        'text_body' => $email['text_body'],
                        'attachments' => json_decode($email['attachments'], true) ?: [],
                        'tracking_id' => $email['tracking_id'],
                        'options' => json_decode($email['options'], true) ?: []
                    ];
                    
                    // Send email
                    $result = $this->provider->send($emailData);
                    
                    if ($result['success']) {
                        $this->updateQueueStatus($email['id'], self::STATUS_SENT, $result);
                        $processed++;
                    } else {
                        throw new Exception($result['error'] ?? 'Unknown error');
                    }
                    
                } catch (Exception $e) {
                    error_log("Queue email failed: " . $e->getMessage());
                    $this->handleQueueFailure($email, $e->getMessage());
                    $failed++;
                }
                
                // Small delay between emails to avoid rate limits
                usleep(100000); // 0.1 second
            }
            
            return [
                'processed' => $processed,
                'failed' => $failed,
                'total' => count($emails)
            ];
            
        } catch (Exception $e) {
            error_log("Queue processing failed: " . $e->getMessage());
            return ['error' => $e->getMessage()];
        }
    }
    
    /**
     * Send report via email
     */
    public function sendReport($reportId, $recipients, $message = '', $attachPdf = true) {
        try {
            // Get report data
            $stmt = $this->db->prepare("
                SELECT r.*, c.business_name, c.primary_color, c.secondary_color, c.accent_color,
                       c.logo_url, c.tagline, c.website, c.email as client_email
                FROM reports r 
                LEFT JOIN clients c ON r.client_id = c.id 
                WHERE r.id = ?
            ");
            $stmt->execute([$reportId]);
            $report = $stmt->fetch();
            
            if (!$report) {
                throw new EmailException("Report not found");
            }
            
            // Load email template
            $templatePath = INCLUDES_PATH . '/email-templates/report-delivery.php';
            if (!file_exists($templatePath)) {
                throw new EmailException("Email template not found");
            }
            
            include $templatePath;
            $htmlBody = getReportDeliveryTemplate($report, $message);
            
            $attachments = [];
            if ($attachPdf) {
                // Generate PDF attachment
                require_once 'PDFGenerator.php';
                $pdfGenerator = new PDFGenerator();
                $pdfPath = $pdfGenerator->generateReport($reportId);
                
                if ($pdfPath && file_exists($pdfPath)) {
                    $attachments[] = [
                        'path' => $pdfPath,
                        'name' => $report['business_name'] . ' - Report - ' . date('Y-m-d') . '.pdf',
                        'type' => 'application/pdf'
                    ];
                }
            }
            
            $results = [];
            foreach ($recipients as $recipient) {
                try {
                    if ($this->config['email_queue_enabled']) {
                        $result = $this->queueEmail(
                            $recipient,
                            "Your Social Media Report - " . ($report['business_name'] ?: 'ghst_'),
                            $htmlBody,
                            null,
                            $attachments,
                            ['report_id' => $reportId]
                        );
                    } else {
                        $result = $this->sendEmail(
                            $recipient,
                            "Your Social Media Report - " . ($report['business_name'] ?: 'ghst_'),
                            $htmlBody,
                            null,
                            $attachments,
                            ['report_id' => $reportId]
                        );
                    }
                    $results[] = ['recipient' => $recipient, 'success' => true, 'result' => $result];
                } catch (Exception $e) {
                    $results[] = ['recipient' => $recipient, 'success' => false, 'error' => $e->getMessage()];
                }
            }
            
            return $results;
            
        } catch (Exception $e) {
            error_log("Report email failed: " . $e->getMessage());
            throw new EmailException("Failed to send report: " . $e->getMessage());
        }
    }
    
    /**
     * Test email configuration
     */
    public function testConfiguration($testEmail) {
        try {
            $subject = "Test Email from " . ($this->config['email_from_name'] ?: 'ghst_');
            $htmlBody = $this->getTestEmailTemplate();
            
            $result = $this->sendEmail($testEmail, $subject, $htmlBody);
            
            return [
                'success' => true,
                'message' => 'Test email sent successfully',
                'result' => $result
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Test email failed: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Track email open
     */
    public function trackOpen($trackingId) {
        try {
            $stmt = $this->db->prepare("
                UPDATE email_tracking 
                SET opened_at = NOW(), open_count = open_count + 1 
                WHERE tracking_id = ? AND opened_at IS NULL
            ");
            $stmt->execute([$trackingId]);
            
            // Also update queue status if applicable
            $stmt = $this->db->prepare("
                UPDATE email_queue 
                SET status = ? 
                WHERE tracking_id = ? AND status = ?
            ");
            $stmt->execute([self::STATUS_OPENED, $trackingId, self::STATUS_SENT]);
            
            return true;
        } catch (Exception $e) {
            error_log("Email tracking failed: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Track email click
     */
    public function trackClick($trackingId, $url) {
        try {
            // Update tracking record
            $stmt = $this->db->prepare("
                INSERT INTO email_clicks (tracking_id, url, clicked_at)
                VALUES (?, ?, NOW())
            ");
            $stmt->execute([$trackingId, $url]);
            
            // Update main tracking
            $stmt = $this->db->prepare("
                UPDATE email_tracking 
                SET clicked_at = NOW(), click_count = click_count + 1,
                    status = ?
                WHERE tracking_id = ?
            ");
            $stmt->execute([self::STATUS_CLICKED, $trackingId]);
            
            return true;
        } catch (Exception $e) {
            error_log("Click tracking failed: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get email statistics
     */
    public function getEmailStats($dateFrom = null, $dateTo = null) {
        try {
            $whereClause = "WHERE 1=1";
            $params = [];
            
            if ($dateFrom) {
                $whereClause .= " AND created_at >= ?";
                $params[] = $dateFrom;
            }
            
            if ($dateTo) {
                $whereClause .= " AND created_at <= ?";
                $params[] = $dateTo;
            }
            
            $stmt = $this->db->prepare("
                SELECT 
                    COUNT(*) as total_sent,
                    SUM(CASE WHEN status IN (?, ?) THEN 1 ELSE 0 END) as delivered,
                    SUM(CASE WHEN opened_at IS NOT NULL THEN 1 ELSE 0 END) as opened,
                    SUM(CASE WHEN clicked_at IS NOT NULL THEN 1 ELSE 0 END) as clicked,
                    SUM(open_count) as total_opens,
                    SUM(click_count) as total_clicks
                FROM email_tracking 
                {$whereClause}
            ");
            
            $stmt->execute(array_merge([self::STATUS_DELIVERED, self::STATUS_OPENED], $params));
            $stats = $stmt->fetch();
            
            // Calculate rates
            $stats['delivery_rate'] = $stats['total_sent'] > 0 ? ($stats['delivered'] / $stats['total_sent']) * 100 : 0;
            $stats['open_rate'] = $stats['delivered'] > 0 ? ($stats['opened'] / $stats['delivered']) * 100 : 0;
            $stats['click_rate'] = $stats['opened'] > 0 ? ($stats['clicked'] / $stats['opened']) * 100 : 0;
            
            return $stats;
            
        } catch (Exception $e) {
            error_log("Email stats failed: " . $e->getMessage());
            return null;
        }
    }
    
    // Private helper methods
    
    private function generateTrackingId() {
        return uniqid('email_', true) . '_' . time();
    }
    
    private function isTrackingEnabled() {
        return !empty($this->config['email_tracking_enabled']) && $this->config['email_tracking_enabled'] === '1';
    }
    
    private function insertTrackingPixel($htmlBody, $trackingId) {
        $trackingUrl = APP_URL . "/api/email/tracking.php?t={$trackingId}&a=open";
        $pixel = '<img src="' . $trackingUrl . '" width="1" height="1" style="display:none;" alt="" />';
        
        // Insert before closing body tag
        if (strpos($htmlBody, '</body>') !== false) {
            return str_replace('</body>', $pixel . '</body>', $htmlBody);
        } else {
            return $htmlBody . $pixel;
        }
    }
    
    private function processLinkTracking($htmlBody, $trackingId) {
        return preg_replace_callback(
            '/<a\s+href="([^"]+)"([^>]*)>/i',
            function($matches) use ($trackingId) {
                $originalUrl = $matches[1];
                
                // Skip tracking URLs and relative URLs
                if (strpos($originalUrl, 'mailto:') === 0 || 
                    strpos($originalUrl, '#') === 0 || 
                    strpos($originalUrl, '/api/email/tracking') !== false) {
                    return $matches[0];
                }
                
                $trackingUrl = APP_URL . "/api/email/tracking.php?t={$trackingId}&a=click&u=" . urlencode($originalUrl);
                return '<a href="' . $trackingUrl . '"' . $matches[2] . '>';
            },
            $htmlBody
        );
    }
    
    private function htmlToText($html) {
        // Basic HTML to text conversion
        $text = strip_tags($html);
        $text = html_entity_decode($text, ENT_QUOTES, 'UTF-8');
        $text = preg_replace('/\s+/', ' ', $text);
        return trim($text);
    }
    
    private function logEmail($emailData, $result) {
        try {
            $stmt = $this->db->prepare("
                INSERT INTO email_tracking 
                (tracking_id, recipient_email, subject, status, provider_id, 
                 provider_response, created_at)
                VALUES (?, ?, ?, ?, ?, ?, NOW())
            ");
            
            $recipientEmail = is_array($emailData['to']) ? $emailData['to']['email'] : $emailData['to'];
            $status = $result['success'] ? self::STATUS_SENT : self::STATUS_FAILED;
            
            $stmt->execute([
                $emailData['tracking_id'],
                $recipientEmail,
                $emailData['subject'],
                $status,
                $result['message_id'] ?? null,
                json_encode($result)
            ]);
            
        } catch (Exception $e) {
            error_log("Email logging failed: " . $e->getMessage());
        }
    }
    
    private function updateQueueStatus($queueId, $status, $result = null) {
        try {
            $stmt = $this->db->prepare("
                UPDATE email_queue 
                SET status = ?, sent_at = ?, provider_response = ?
                WHERE id = ?
            ");
            
            $sentAt = in_array($status, [self::STATUS_SENT, self::STATUS_DELIVERED]) ? date('Y-m-d H:i:s') : null;
            $stmt->execute([
                $status,
                $sentAt,
                $result ? json_encode($result) : null,
                $queueId
            ]);
        } catch (Exception $e) {
            error_log("Queue status update failed: " . $e->getMessage());
        }
    }
    
    private function handleQueueFailure($email, $error) {
        try {
            $retries = $email['retry_count'] + 1;
            $maxRetries = (int)($this->config['email_max_retries'] ?? 3);
            
            if ($retries >= $maxRetries) {
                // Max retries reached, mark as failed
                $this->updateQueueStatus($email['id'], self::STATUS_FAILED, ['error' => $error]);
            } else {
                // Schedule retry
                $retryDelay = (int)($this->config['email_retry_delay'] ?? 300);
                $nextRetry = date('Y-m-d H:i:s', time() + $retryDelay);
                
                $stmt = $this->db->prepare("
                    UPDATE email_queue 
                    SET status = ?, retry_count = ?, next_retry_at = ?, last_error = ?
                    WHERE id = ?
                ");
                $stmt->execute([
                    self::STATUS_PENDING,
                    $retries,
                    $nextRetry,
                    $error,
                    $email['id']
                ]);
            }
        } catch (Exception $e) {
            error_log("Queue failure handling failed: " . $e->getMessage());
        }
    }
    
    private function getTestEmailTemplate() {
        return '
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <title>Test Email</title>
        </head>
        <body style="font-family: Arial, sans-serif; line-height: 1.6; color: #333;">
            <div style="max-width: 600px; margin: 0 auto; padding: 20px;">
                <h2 style="color: #2563eb;">Email Configuration Test</h2>
                <p>Congratulations! Your email configuration is working correctly.</p>
                <p>This test email was sent from your ghst_ social media management tool.</p>
                <hr style="border: 1px solid #e2e8f0; margin: 20px 0;">
                <p style="font-size: 14px; color: #64748b;">
                    Test sent at: ' . date('Y-m-d H:i:s T') . '<br>
                    Provider: ' . ($this->config['email_provider'] ?? 'SMTP') . '<br>
                    From: ' . ($this->config['email_from_email'] ?? 'Unknown') . '
                </p>
            </div>
        </body>
        </html>';
    }
}

/**
 * SMTP Email Provider
 */
class SMTPProvider {
    private $config;
    
    public function __construct($config) {
        $this->config = $config;
    }
    
    public function send($emailData) {
        // Use PHPMailer for SMTP
        if (!class_exists('PHPMailer\PHPMailer\PHPMailer')) {
            require_once INCLUDES_PATH . '/vendor/phpmailer/PHPMailer.php';
            require_once INCLUDES_PATH . '/vendor/phpmailer/SMTP.php';
            require_once INCLUDES_PATH . '/vendor/phpmailer/Exception.php';
        }
        
        try {
            $mail = new \PHPMailer\PHPMailer\PHPMailer(true);
            
            // Server settings
            $mail->isSMTP();
            $mail->Host = $this->config['email_smtp_host'];
            $mail->SMTPAuth = true;
            $mail->Username = $this->config['email_smtp_user'];
            $mail->Password = $this->config['email_smtp_pass'];
            $mail->SMTPSecure = $this->config['email_smtp_encryption'] ?? 'tls';
            $mail->Port = $this->config['email_smtp_port'];
            
            // Recipients
            $mail->setFrom(
                $this->config['email_from_email'],
                $this->config['email_from_name']
            );
            
            if (is_array($emailData['to'])) {
                $mail->addAddress($emailData['to']['email'], $emailData['to']['name'] ?? '');
            } else {
                $mail->addAddress($emailData['to']);
            }
            
            if (!empty($this->config['email_reply_to'])) {
                $mail->addReplyTo($this->config['email_reply_to']);
            }
            
            // Content
            $mail->isHTML(true);
            $mail->Subject = $emailData['subject'];
            $mail->Body = $emailData['html_body'];
            $mail->AltBody = $emailData['text_body'];
            
            // Attachments
            if (!empty($emailData['attachments'])) {
                foreach ($emailData['attachments'] as $attachment) {
                    $mail->addAttachment(
                        $attachment['path'],
                        $attachment['name'] ?? '',
                        'base64',
                        $attachment['type'] ?? ''
                    );
                }
            }
            
            $mail->send();
            
            return [
                'success' => true,
                'message_id' => $mail->getLastMessageID(),
                'provider' => 'smtp'
            ];
            
        } catch (\PHPMailer\PHPMailer\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
                'provider' => 'smtp'
            ];
        }
    }
}

/**
 * SendGrid Email Provider
 */
class SendGridProvider {
    private $config;
    private $apiKey;
    
    public function __construct($config) {
        $this->config = $config;
        $this->apiKey = $config['email_sendgrid_api_key'] ?? '';
    }
    
    public function send($emailData) {
        if (empty($this->apiKey)) {
            return [
                'success' => false,
                'error' => 'SendGrid API key not configured',
                'provider' => 'sendgrid'
            ];
        }
        
        try {
            $data = [
                'personalizations' => [[
                    'to' => [
                        is_array($emailData['to']) ? $emailData['to'] : ['email' => $emailData['to']]
                    ]
                ]],
                'from' => [
                    'email' => $this->config['email_from_email'],
                    'name' => $this->config['email_from_name']
                ],
                'subject' => $emailData['subject'],
                'content' => [
                    [
                        'type' => 'text/html',
                        'value' => $emailData['html_body']
                    ],
                    [
                        'type' => 'text/plain',
                        'value' => $emailData['text_body']
                    ]
                ]
            ];
            
            // Add attachments
            if (!empty($emailData['attachments'])) {
                $data['attachments'] = [];
                foreach ($emailData['attachments'] as $attachment) {
                    $data['attachments'][] = [
                        'content' => base64_encode(file_get_contents($attachment['path'])),
                        'type' => $attachment['type'] ?? 'application/octet-stream',
                        'filename' => $attachment['name'] ?? basename($attachment['path'])
                    ];
                }
            }
            
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, 'https://api.sendgrid.com/v3/mail/send');
            curl_setopt($ch, CURLOPT_POST, 1);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Authorization: Bearer ' . $this->apiKey,
                'Content-Type: application/json'
            ]);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            
            if ($httpCode === 202) {
                return [
                    'success' => true,
                    'message_id' => uniqid('sg_'),
                    'provider' => 'sendgrid'
                ];
            } else {
                return [
                    'success' => false,
                    'error' => "SendGrid API error: HTTP {$httpCode}",
                    'provider' => 'sendgrid'
                ];
            }
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
                'provider' => 'sendgrid'
            ];
        }
    }
}

/**
 * Amazon SES Email Provider
 */
class SESProvider {
    private $config;
    
    public function __construct($config) {
        $this->config = $config;
    }
    
    public function send($emailData) {
        // SES implementation would go here
        // For now, return not implemented
        return [
            'success' => false,
            'error' => 'SES provider not yet implemented',
            'provider' => 'ses'
        ];
    }
}