<?php
/**
 * Custom Email Template
 * Flexible template for custom email communications
 */

function getCustomEmailTemplate($clientId, $subject, $content, $options = []) {
    require_once INCLUDES_PATH . '/BrandingHelper.php';
    
    $brandingHelper = BrandingHelper::getInstance();
    $branding = $brandingHelper->getBranding($clientId);
    
    $businessName = $branding['business_name'] ?: 'ghst_ Social';
    $logoHtml = $brandingHelper->getLogoHtml($clientId, 'height: 60px; width: auto;', $businessName);
    $signature = $brandingHelper->getEmailSignature($clientId);
    
    // Template options
    $recipientName = $options['recipient_name'] ?? '';
    $headerStyle = $options['header_style'] ?? 'gradient'; // gradient, solid, minimal
    $showLogo = $options['show_logo'] ?? true;
    $showSignature = $options['show_signature'] ?? true;
    $showFooter = $options['show_footer'] ?? true;
    $contentStyle = $options['content_style'] ?? 'default'; // default, newsletter, announcement
    $ctaButton = $options['cta_button'] ?? null; // ['text' => 'Button Text', 'url' => 'https://...']
    $customCss = $options['custom_css'] ?? '';
    
    // Header styles
    $headerCss = '';
    switch ($headerStyle) {
        case 'solid':
            $headerCss = "background-color: {$branding['primary_color']};";
            break;
        case 'minimal':
            $headerCss = "background-color: #ffffff; color: {$branding['primary_color']}; border-bottom: 3px solid {$branding['primary_color']};";
            break;
        default: // gradient
            $headerCss = "background: linear-gradient(135deg, {$branding['primary_color']}, {$branding['secondary_color']});";
    }
    
    // Content wrapper styles
    $contentWrapperCss = '';
    switch ($contentStyle) {
        case 'newsletter':
            $contentWrapperCss = "background-color: #f8fafc; padding: 20px;";
            break;
        case 'announcement':
            $contentWrapperCss = "background: linear-gradient(135deg, {$branding['primary_color']}10, {$branding['accent_color']}10); padding: 25px; border-radius: 8px; margin: 20px 0;";
            break;
    }
    
    // CTA Button
    $ctaHtml = '';
    if ($ctaButton && isset($ctaButton['text']) && isset($ctaButton['url'])) {
        $ctaHtml = "
        <div style='text-align: center; margin: 30px 0;'>
            <a href='{$ctaButton['url']}' style='
                display: inline-block;
                background: linear-gradient(135deg, {$branding['primary_color']}, {$branding['secondary_color']});
                color: white !important;
                padding: 15px 30px;
                text-decoration: none;
                border-radius: 8px;
                font-weight: 600;
                font-size: 16px;
                text-transform: uppercase;
                letter-spacing: 0.5px;
                transition: transform 0.2s ease;
            '>{$ctaButton['text']}</a>
        </div>";
    }
    
    // Footer content
    $footerHtml = '';
    if ($showFooter) {
        $footerHtml = "
        <div class=\"footer\" style=\"
            background-color: #f8fafc;
            padding: 30px;
            border-top: 1px solid #e2e8f0;
            text-align: center;
            color: #64748b;
            font-size: 14px;
        \">
            <div style=\"margin-bottom: 20px;\">
                " . ($branding['website'] ? "<a href=\"{$branding['website']}\" style=\"color: {$branding['primary_color']}; text-decoration: none; margin: 0 10px;\">🌐 Website</a>" : "") . "
                " . ($branding['email'] ? "<a href=\"mailto:{$branding['email']}\" style=\"color: {$branding['primary_color']}; text-decoration: none; margin: 0 10px;\">✉️ Email</a>" : "") . "
                " . ($branding['phone'] ? "<a href=\"tel:{$branding['phone']}\" style=\"color: {$branding['primary_color']}; text-decoration: none; margin: 0 10px;\">📞 Phone</a>" : "") . "
            </div>
            
            <p style=\"margin: 0 0 15px 0;\">
                You're receiving this email because you're a valued client of {$businessName}.
            </p>
            
            <p style=\"margin: 0; font-size: 12px; color: #94a3b8;\">
                " . ($branding['address'] ? $branding['address'] . "<br>" : "") . "
                Email sent on " . date('F j, Y \a\t g:i A T') . " | Powered by ghst_
            </p>
        </div>";
    }
    
    $template = "
    <!DOCTYPE html>
    <html lang=\"en\">
    <head>
        <meta charset=\"UTF-8\">
        <meta name=\"viewport\" content=\"width=device-width, initial-scale=1.0\">
        <title>{$subject}</title>
        <style>
            @media only screen and (max-width: 600px) {
                .email-container {
                    width: 100% !important;
                    margin: 0 !important;
                    border-radius: 0 !important;
                }
                .content {
                    padding: 20px !important;
                }
                .header {
                    padding: 20px !important;
                }
                .footer {
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
                {$headerCss}
                padding: 40px 30px;
                text-align: center;
                color: " . ($headerStyle === 'minimal' ? $branding['primary_color'] : 'white') . ";
                position: relative;
            }
            
            .content {
                padding: 40px 30px;
                {$contentWrapperCss}
            }
            
            .signature {
                margin-top: 30px;
                padding-top: 20px;
                border-top: 1px solid #e2e8f0;
                color: #64748b;
                font-size: 14px;
            }
            
            /* Custom styles */
            {$customCss}
            
            /* Content styling */
            .content h1, .content h2, .content h3, .content h4, .content h5, .content h6 {
                color: {$branding['primary_color']};
                margin-top: 0;
            }
            
            .content a {
                color: {$branding['primary_color']};
                text-decoration: none;
            }
            
            .content a:hover {
                text-decoration: underline;
            }
            
            .highlight-box {
                background: linear-gradient(135deg, {$branding['primary_color']}15, {$branding['accent_color']}10);
                padding: 20px;
                border-radius: 8px;
                margin: 20px 0;
                border-left: 4px solid {$branding['accent_color']};
            }
            
            .info-box {
                background-color: #f8fafc;
                padding: 20px;
                border-radius: 8px;
                margin: 20px 0;
                border: 1px solid #e2e8f0;
            }
            
            .success-box {
                background-color: #f0fff4;
                padding: 20px;
                border-radius: 8px;
                margin: 20px 0;
                border-left: 4px solid #10b981;
                color: #065f46;
            }
            
            .warning-box {
                background-color: #fffbeb;
                padding: 20px;
                border-radius: 8px;
                margin: 20px 0;
                border-left: 4px solid #f59e0b;
                color: #92400e;
            }
            
            .quote {
                border-left: 4px solid {$branding['primary_color']};
                padding-left: 20px;
                margin: 20px 0;
                font-style: italic;
                color: #4a5568;
            }
        </style>
    </head>
    <body>
        <div class=\"email-container\">
            " . ($showLogo ? "
            <div class=\"header\">
                {$logoHtml}
                <h1 style=\"margin: 15px 0 0 0; font-size: 28px; font-weight: 600;\">{$businessName}</h1>
                " . ($branding['tagline'] && $headerStyle !== 'minimal' ? "<p style=\"margin: 10px 0 0 0; opacity: 0.9; font-size: 16px;\">{$branding['tagline']}</p>" : "") . "
            </div>
            " : "") . "
            
            <div class=\"content\">
                " . ($recipientName ? "<p style=\"font-size: 16px;\">Hi {$recipientName},</p>" : "") . "
                
                {$content}
                
                {$ctaHtml}
                
                " . ($showSignature && !empty($signature) ? "<div class=\"signature\">{$signature}</div>" : "") . "
            </div>
            
            {$footerHtml}
        </div>
    </body>
    </html>";
    
    return $template;
}

/**
 * Get email template preview with sample content
 */
function getCustomTemplatePreview($clientId, $templateStyle = 'default') {
    $sampleContent = "
    <h2>Welcome to Our Newsletter!</h2>
    <p>This is a sample email template that showcases the various styling options available for your custom emails.</p>
    
    <div class=\"highlight-box\">
        <h3>📢 Important Announcement</h3>
        <p>This is a highlighted content box that draws attention to important information.</p>
    </div>
    
    <div class=\"info-box\">
        <h4>ℹ️ Information Box</h4>
        <p>Use this style for general information or tips that you want to share with your audience.</p>
    </div>
    
    <div class=\"success-box\">
        <h4>✅ Success Message</h4>
        <p>Perfect for celebrating achievements or positive news.</p>
    </div>
    
    <div class=\"warning-box\">
        <h4>⚠️ Warning or Alert</h4>
        <p>Use this style for important warnings or time-sensitive information.</p>
    </div>
    
    <div class=\"quote\">
        \"This is a sample quote or testimonial that you can include in your emails to add credibility and social proof.\"
        <br><em>- Happy Client</em>
    </div>
    
    <p>You can also include regular paragraphs with <a href=\"#\">links</a> and <strong>bold text</strong> or <em>italic text</em> as needed.</p>
    
    <ul>
        <li>Feature one</li>
        <li>Feature two</li>
        <li>Feature three</li>
    </ul>
    
    <p>Thank you for choosing our services. We look forward to continuing our partnership!</p>
    ";
    
    $options = [
        'recipient_name' => 'John Doe',
        'header_style' => $templateStyle,
        'cta_button' => [
            'text' => 'Learn More',
            'url' => '#'
        ]
    ];
    
    return getCustomEmailTemplate($clientId, 'Sample Email Template', $sampleContent, $options);
}
?>