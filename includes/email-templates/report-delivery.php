<?php
/**
 * Report Delivery Email Template
 * Professional email template for sending social media reports to clients
 */

function getReportDeliveryTemplate($report, $customMessage = '') {
    require_once INCLUDES_PATH . '/BrandingHelper.php';
    
    $brandingHelper = BrandingHelper::getInstance();
    $branding = $brandingHelper->getBranding($report['client_id']);
    
    $businessName = $branding['business_name'] ?: 'ghst_ Social';
    $logoHtml = $brandingHelper->getLogoHtml($report['client_id'], 'height: 60px; width: auto;', $businessName);
    $signature = $brandingHelper->getEmailSignature($report['client_id']);
    
    // Format report period
    $reportPeriod = date('F Y', strtotime($report['created_at']));
    
    // Custom message section
    $customMessageHtml = '';
    if (!empty($customMessage)) {
        $customMessageHtml = "
        <div style='background-color: #f8fafc; padding: 20px; border-radius: 8px; margin: 20px 0; border-left: 4px solid {$branding['primary_color']};'>
            <h3 style='margin-top: 0; color: {$branding['primary_color']}; font-size: 18px;'>Personal Message</h3>
            <div style='color: #4a5568; line-height: 1.6;'>" . nl2br(htmlspecialchars($customMessage)) . "</div>
        </div>";
    }
    
    // Report metrics (if available)
    $reportData = json_decode($report['data'] ?? '{}', true);
    $metricsHtml = '';
    
    if (!empty($reportData)) {
        $totalReach = number_format($reportData['total_reach'] ?? 0);
        $totalEngagement = number_format($reportData['total_engagement'] ?? 0);
        $newFollowers = number_format($reportData['new_followers'] ?? 0);
        $postsCount = $reportData['posts_count'] ?? 0;
        $engagementRate = round($reportData['engagement_rate'] ?? 0, 2);
        
        $metricsHtml = "
        <div style='background: linear-gradient(135deg, {$branding['primary_color']}15, {$branding['secondary_color']}15); padding: 25px; border-radius: 12px; margin: 25px 0;'>
            <h3 style='margin-top: 0; color: {$branding['primary_color']}; font-size: 20px; margin-bottom: 20px;'>Key Performance Metrics</h3>
            <div style='display: flex; flex-wrap: wrap; gap: 20px;'>
                <div style='flex: 1; min-width: 200px; background: white; padding: 15px; border-radius: 8px; text-align: center; box-shadow: 0 2px 4px rgba(0,0,0,0.1);'>
                    <div style='font-size: 24px; font-weight: bold; color: {$branding['primary_color']};'>{$totalReach}</div>
                    <div style='color: #64748b; font-size: 14px; margin-top: 5px;'>Total Reach</div>
                </div>
                <div style='flex: 1; min-width: 200px; background: white; padding: 15px; border-radius: 8px; text-align: center; box-shadow: 0 2px 4px rgba(0,0,0,0.1);'>
                    <div style='font-size: 24px; font-weight: bold; color: {$branding['secondary_color']};'>{$totalEngagement}</div>
                    <div style='color: #64748b; font-size: 14px; margin-top: 5px;'>Total Engagement</div>
                </div>
                <div style='flex: 1; min-width: 200px; background: white; padding: 15px; border-radius: 8px; text-align: center; box-shadow: 0 2px 4px rgba(0,0,0,0.1);'>
                    <div style='font-size: 24px; font-weight: bold; color: {$branding['accent_color']};'>{$newFollowers}</div>
                    <div style='color: #64748b; font-size: 14px; margin-top: 5px;'>New Followers</div>
                </div>
                <div style='flex: 1; min-width: 200px; background: white; padding: 15px; border-radius: 8px; text-align: center; box-shadow: 0 2px 4px rgba(0,0,0,0.1);'>
                    <div style='font-size: 24px; font-weight: bold; color: {$branding['primary_color']};'>{$engagementRate}%</div>
                    <div style='color: #64748b; font-size: 14px; margin-top: 5px;'>Engagement Rate</div>
                </div>
            </div>
        </div>";
    }
    
    $template = "
    <!DOCTYPE html>
    <html lang=\"en\">
    <head>
        <meta charset=\"UTF-8\">
        <meta name=\"viewport\" content=\"width=device-width, initial-scale=1.0\">
        <title>Your Social Media Report - {$reportPeriod}</title>
        <style>
            @media only screen and (max-width: 600px) {
                .email-container {
                    width: 100% !important;
                    margin: 0 !important;
                }
                .metrics-flex {
                    flex-direction: column !important;
                }
                .metric-card {
                    margin-bottom: 15px !important;
                }
                .content {
                    padding: 20px !important;
                }
                .header {
                    padding: 20px !important;
                }
            }
            
            body {
                font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
                line-height: 1.6;
                color: #333;
                margin: 0;
                padding: 0;
                background-color: #f8fafc;
            }
            
            .email-container {
                max-width: 700px;
                margin: 20px auto;
                background-color: #ffffff;
                box-shadow: 0 10px 25px rgba(0, 0, 0, 0.1);
                border-radius: 12px;
                overflow: hidden;
            }
            
            .header {
                background: linear-gradient(135deg, {$branding['primary_color']}, {$branding['secondary_color']});
                padding: 40px 30px;
                text-align: center;
                color: white;
                position: relative;
            }
            
            .header::before {
                content: '';
                position: absolute;
                top: 0;
                left: 0;
                right: 0;
                height: 4px;
                background: linear-gradient(90deg, {$branding['primary_color']}, {$branding['secondary_color']}, {$branding['accent_color']});
            }
            
            .content {
                padding: 40px 30px;
            }
            
            .footer {
                background-color: #f8fafc;
                padding: 30px;
                border-top: 1px solid #e2e8f0;
                text-align: center;
            }
            
            .cta-button {
                display: inline-block;
                background: linear-gradient(135deg, {$branding['primary_color']}, {$branding['secondary_color']});
                color: white !important;
                padding: 15px 30px;
                text-decoration: none;
                border-radius: 8px;
                margin: 25px 0;
                font-weight: 600;
                font-size: 16px;
                text-align: center;
                transition: transform 0.2s ease;
            }
            
            .cta-button:hover {
                transform: translateY(-2px);
            }
            
            .signature {
                margin-top: 30px;
                padding-top: 20px;
                border-top: 1px solid #e2e8f0;
                color: #64748b;
                font-size: 14px;
            }
            
            .report-highlight {
                background: linear-gradient(135deg, {$branding['accent_color']}20, {$branding['primary_color']}10);
                padding: 25px;
                border-radius: 12px;
                margin: 25px 0;
                border: 1px solid {$branding['accent_color']}30;
            }
            
            .contact-info {
                background-color: white;
                padding: 20px;
                border-radius: 8px;
                border: 1px solid #e2e8f0;
                margin-top: 20px;
            }
            
            h1, h2, h3 {
                margin-top: 0;
            }
            
            .social-links {
                margin: 20px 0;
            }
            
            .social-links a {
                display: inline-block;
                margin: 0 10px;
                color: {$branding['primary_color']};
                text-decoration: none;
            }
        </style>
    </head>
    <body>
        <div class=\"email-container\">
            <div class=\"header\">
                {$logoHtml}
                <h1 style=\"margin: 15px 0 0 0; font-size: 28px; font-weight: 600;\">{$businessName}</h1>
                " . ($branding['tagline'] ? "<p style=\"margin: 10px 0 0 0; opacity: 0.9; font-size: 16px;\">{$branding['tagline']}</p>" : "") . "
            </div>
            
            <div class=\"content\">
                <h2 style=\"color: {$branding['primary_color']}; font-size: 24px; margin-bottom: 20px;\">
                    📊 Your Social Media Report - {$reportPeriod}
                </h2>
                
                <p style=\"font-size: 16px; margin-bottom: 20px;\">
                    We're excited to share your latest social media performance insights! Your brand's online presence continues to evolve, and we're here to help you understand what's working and where we can optimize further.
                </p>
                
                {$customMessageHtml}
                
                {$metricsHtml}
                
                <div class=\"report-highlight\">
                    <h3 style=\"color: {$branding['primary_color']}; font-size: 20px; margin-bottom: 15px;\">📈 What's Inside This Report</h3>
                    <ul style=\"margin: 0; padding-left: 20px; color: #4a5568;\">
                        <li style=\"margin-bottom: 10px;\"><strong>Platform Performance:</strong> Detailed metrics for each social media channel</li>
                        <li style=\"margin-bottom: 10px;\"><strong>Content Analysis:</strong> Your top-performing posts and engagement insights</li>
                        <li style=\"margin-bottom: 10px;\"><strong>Audience Growth:</strong> Follower demographics and growth trends</li>
                        <li style=\"margin-bottom: 10px;\"><strong>Competitive Analysis:</strong> How you're performing against industry benchmarks</li>
                        <li style=\"margin-bottom: 10px;\"><strong>Strategic Recommendations:</strong> Data-driven suggestions for next month</li>
                    </ul>
                </div>
                
                <div style=\"text-align: center; margin: 30px 0;\">
                    <a href=\"" . APP_URL . "/dashboard/reports.php?id={$report['id']}\" class=\"cta-button\">
                        📱 View Interactive Dashboard
                    </a>
                </div>
                
                <p style=\"font-size: 16px; margin: 25px 0;\">
                    <strong>Questions about your results?</strong> We'd love to discuss your social media strategy and explore new opportunities for growth. Your success is our priority, and we're always here to provide insights and recommendations.
                </p>
                
                <div class=\"contact-info\">
                    <h4 style=\"color: {$branding['primary_color']}; margin-top: 0;\">📞 Let's Connect</h4>
                    <p style=\"margin: 10px 0; color: #4a5568;\">
                        Ready to discuss your results or plan next month's strategy? 
                        <a href=\"mailto:{$branding['email']}\" style=\"color: {$branding['primary_color']}; text-decoration: none;\">
                            Send us a message
                        </a> or schedule a call at your convenience.
                    </p>
                </div>
                
                " . (!empty($signature) ? "<div class=\"signature\">{$signature}</div>" : "") . "
            </div>
            
            <div class=\"footer\">
                <div style=\"margin-bottom: 20px;\">
                    " . ($branding['website'] ? "<a href=\"{$branding['website']}\" style=\"color: {$branding['primary_color']}; text-decoration: none; margin: 0 10px;\">🌐 Website</a>" : "") . "
                    " . ($branding['email'] ? "<a href=\"mailto:{$branding['email']}\" style=\"color: {$branding['primary_color']}; text-decoration: none; margin: 0 10px;\">✉️ Email</a>" : "") . "
                </div>
                
                <p style=\"margin: 0; color: #64748b; font-size: 14px;\">
                    You're receiving this report because you're a valued client of {$businessName}.
                    <br>This report was generated on " . date('F j, Y \a\t g:i A T') . "
                </p>
                
                <p style=\"margin: 15px 0 0 0; color: #94a3b8; font-size: 12px;\">
                    Powered by ghst_ Social Media Management Platform
                </p>
            </div>
        </div>
    </body>
    </html>";
    
    return $template;
}