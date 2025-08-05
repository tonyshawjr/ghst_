<?php
/**
 * Branded Email Template Example
 * Shows how to integrate branding into email communications
 */

function getBrandedEmailTemplate($clientId, $subject, $content, $recipientName = '') {
    require_once 'BrandingHelper.php';
    
    $brandingHelper = BrandingHelper::getInstance();
    $branding = $brandingHelper->getBranding($clientId);
    
    $businessName = $branding['business_name'] ?: 'ghst_ Social';
    $logoHtml = $brandingHelper->getLogoHtml($clientId, 'height: 60px; width: auto;', $businessName);
    $signature = $brandingHelper->getEmailSignature($clientId);
    
    $template = "
    <!DOCTYPE html>
    <html lang=\"en\">
    <head>
        <meta charset=\"UTF-8\">
        <meta name=\"viewport\" content=\"width=device-width, initial-scale=1.0\">
        <title>{$subject}</title>
        <style>
            body {
                font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
                line-height: 1.6;
                color: #333;
                margin: 0;
                padding: 0;
                background-color: #f8fafc;
            }
            .email-container {
                max-width: 600px;
                margin: 0 auto;
                background-color: #ffffff;
                box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            }
            .header {
                background: linear-gradient(135deg, {$branding['primary_color']}, {$branding['secondary_color']});
                padding: 30px;
                text-align: center;
                color: white;
            }
            .content {
                padding: 30px;
            }
            .footer {
                background-color: #f8fafc;
                padding: 20px 30px;
                border-top: 1px solid #e2e8f0;
                font-size: 14px;
                color: #64748b;
            }
            .signature {
                white-space: pre-line;
                margin-top: 20px;
                padding-top: 20px;
                border-top: 1px solid #e2e8f0;
                color: #64748b;
            }
            .cta-button {
                display: inline-block;
                background-color: {$branding['accent_color']};
                color: white;
                padding: 12px 24px;
                text-decoration: none;
                border-radius: 6px;
                margin: 20px 0;
                font-weight: 600;
            }
            .brand-colors {
                background: linear-gradient(90deg, {$branding['primary_color']}, {$branding['secondary_color']}, {$branding['accent_color']});
                height: 4px;
            }
        </style>
    </head>
    <body>
        <div class=\"email-container\">
            <div class=\"brand-colors\"></div>
            <div class=\"header\">
                {$logoHtml}
                <h1 style=\"margin: 15px 0 0 0; font-size: 24px; font-weight: 600;\">{$businessName}</h1>
                " . ($branding['tagline'] ? "<p style=\"margin: 5px 0 0 0; opacity: 0.9; font-size: 16px;\">{$branding['tagline']}</p>" : "") . "
            </div>
            
            <div class=\"content\">
                " . ($recipientName ? "<p>Hi {$recipientName},</p>" : "") . "
                
                {$content}
                
                <div class=\"signature\">
                    {$signature}
                </div>
            </div>
            
            <div class=\"footer\">
                <p style=\"margin: 0; text-align: center;\">
                    " . ($branding['website'] ? "<a href=\"{$branding['website']}\" style=\"color: {$branding['primary_color']}; text-decoration: none;\">Visit our website</a> | " : "") . "
                    " . ($branding['email'] ? "<a href=\"mailto:{$branding['email']}\" style=\"color: {$branding['primary_color']}; text-decoration: none;\">{$branding['email']}</a>" : "") . "
                </p>
                <p style=\"margin: 10px 0 0 0; text-align: center; font-size: 12px;\">
                    You're receiving this email because you're a valued client of {$businessName}.
                </p>
            </div>
        </div>
    </body>
    </html>
    ";
    
    return $template;
}

/**
 * Generate a branded social media report email
 */
function getBrandedReportEmail($clientId, $reportData, $clientName = '') {
    require_once 'BrandingHelper.php';
    
    $brandingHelper = BrandingHelper::getInstance();
    $branding = $brandingHelper->getBranding($clientId);
    
    $reportHeader = $brandingHelper->getReportHeader($clientId, 'Monthly Social Media Report', $reportData['period'] ?? date('F Y'));
    $reportFooter = $brandingHelper->getReportFooter($clientId);
    
    $content = "
        <div style='background-color: #f8fafc; padding: 20px; border-radius: 8px; margin: 20px 0;'>
            <h2 style='color: {$branding['primary_color']}; margin-top: 0;'>Your Social Media Performance</h2>
            <div style='white-space: pre-line; color: #4a5568; margin-bottom: 20px;'>{$reportHeader}</div>
        </div>
        
        <p>We're excited to share your latest social media performance metrics and insights!</p>
        
        <div style='background: linear-gradient(135deg, {$branding['primary_color']}15, {$branding['secondary_color']}15); padding: 20px; border-radius: 8px; margin: 20px 0;'>
            <h3 style='margin-top: 0; color: {$branding['primary_color']};'>Key Highlights</h3>
            <ul>
                <li><strong>Total Reach:</strong> " . number_format($reportData['reach'] ?? 0) . " people</li>
                <li><strong>Engagement Rate:</strong> " . ($reportData['engagement_rate'] ?? '0') . "%</li>
                <li><strong>New Followers:</strong> " . number_format($reportData['new_followers'] ?? 0) . "</li>
                <li><strong>Posts Published:</strong> " . ($reportData['posts_count'] ?? 0) . "</li>
            </ul>
        </div>
        
        <p>Your social media presence continues to grow! We've attached the detailed report with platform-specific metrics, top-performing content, and recommendations for next month.</p>
        
        <a href='#' class='cta-button'>View Full Report Dashboard</a>
        
        <p>Have questions about your results? We'd love to discuss your strategy and how we can continue driving growth for your brand.</p>
        
        <div style='background-color: #f8fafc; padding: 15px; border-radius: 8px; margin: 20px 0; border-left: 4px solid {$branding['accent_color']};'>
            <div style='white-space: pre-line; color: #4a5568; font-size: 14px;'>{$reportFooter}</div>
        </div>
    ";
    
    return getBrandedEmailTemplate(
        $clientId,
        'Your Monthly Social Media Report is Ready!',
        $content,
        $clientName
    );
}
?>