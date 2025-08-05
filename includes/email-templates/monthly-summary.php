<?php
/**
 * Monthly Summary Email Template
 * Comprehensive monthly performance summary for clients
 */

function getMonthlySummaryTemplate($clientId, $summaryData, $recipientName = '') {
    require_once INCLUDES_PATH . '/BrandingHelper.php';
    
    $brandingHelper = BrandingHelper::getInstance();
    $branding = $brandingHelper->getBranding($clientId);
    
    $businessName = $branding['business_name'] ?: 'ghst_ Social';
    $logoHtml = $brandingHelper->getLogoHtml($clientId, 'height: 60px; width: auto;', $businessName);
    $signature = $brandingHelper->getEmailSignature($clientId);
    
    // Extract summary data
    $currentMonth = $summaryData['period'] ?? date('F Y');
    $totalReach = number_format($summaryData['total_reach'] ?? 0);
    $totalEngagement = number_format($summaryData['total_engagement'] ?? 0);
    $newFollowers = number_format($summaryData['new_followers'] ?? 0);
    $postsPublished = $summaryData['posts_published'] ?? 0;
    $engagementRate = round($summaryData['engagement_rate'] ?? 0, 2);
    $topPlatform = $summaryData['top_platform'] ?? 'Instagram';
    $bestPost = $summaryData['best_post'] ?? null;
    
    // Growth indicators
    $reachGrowth = $summaryData['reach_growth'] ?? 0;
    $engagementGrowth = $summaryData['engagement_growth'] ?? 0;
    $followerGrowth = $summaryData['follower_growth'] ?? 0;
    
    $reachTrend = $reachGrowth >= 0 ? '📈 +' . round($reachGrowth, 1) . '%' : '📉 ' . round($reachGrowth, 1) . '%';
    $engagementTrend = $engagementGrowth >= 0 ? '📈 +' . round($engagementGrowth, 1) . '%' : '📉 ' . round($engagementGrowth, 1) . '%';
    $followerTrend = $followerGrowth >= 0 ? '📈 +' . round($followerGrowth, 1) . '%' : '📉 ' . round($followerGrowth, 1) . '%';
    
    // Platform breakdown
    $platformData = $summaryData['platforms'] ?? [];
    $platformsHtml = '';
    
    if (!empty($platformData)) {
        foreach ($platformData as $platform => $data) {
            $platformIcon = getPlatformIcon($platform);
            $platformsHtml .= "
            <div style='background: white; padding: 20px; border-radius: 8px; margin-bottom: 15px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);'>
                <div style='display: flex; align-items: center; margin-bottom: 15px;'>
                    <span style='font-size: 24px; margin-right: 10px;'>{$platformIcon}</span>
                    <h4 style='margin: 0; color: {$branding['primary_color']}; text-transform: capitalize;'>{$platform}</h4>
                </div>
                <div style='display: flex; justify-content: space-between; flex-wrap: wrap;'>
                    <div style='text-align: center; flex: 1; min-width: 80px;'>
                        <div style='font-weight: bold; color: {$branding['primary_color']};'>" . number_format($data['reach'] ?? 0) . "</div>
                        <div style='font-size: 12px; color: #64748b;'>Reach</div>
                    </div>
                    <div style='text-align: center; flex: 1; min-width: 80px;'>
                        <div style='font-weight: bold; color: {$branding['secondary_color']};'>" . number_format($data['engagement'] ?? 0) . "</div>
                        <div style='font-size: 12px; color: #64748b;'>Engagement</div>
                    </div>
                    <div style='text-align: center; flex: 1; min-width: 80px;'>
                        <div style='font-weight: bold; color: {$branding['accent_color']};'>" . number_format($data['followers'] ?? 0) . "</div>
                        <div style='font-size: 12px; color: #64748b;'>Followers</div>
                    </div>
                    <div style='text-align: center; flex: 1; min-width: 80px;'>
                        <div style='font-weight: bold; color: {$branding['primary_color']};'>" . ($data['posts'] ?? 0) . "</div>
                        <div style='font-size: 12px; color: #64748b;'>Posts</div>
                    </div>
                </div>
            </div>";
        }
    }
    
    // Best performing post
    $bestPostHtml = '';
    if ($bestPost) {
        $bestPostHtml = "
        <div style='background: linear-gradient(135deg, {$branding['accent_color']}20, {$branding['primary_color']}10); padding: 20px; border-radius: 8px; margin: 20px 0;'>
            <h4 style='color: {$branding['primary_color']}; margin-top: 0;'>🏆 Top Performing Post</h4>
            <div style='background: white; padding: 15px; border-radius: 6px; border-left: 4px solid {$branding['accent_color']};'>
                <p style='margin: 0 0 10px 0; font-style: italic; color: #4a5568;'>\"" . htmlspecialchars(substr($bestPost['content'] ?? '', 0, 100)) . "...\"</p>
                <div style='display: flex; justify-content: space-between; font-size: 14px; color: #64748b;'>
                    <span>👥 " . number_format($bestPost['reach'] ?? 0) . " reach</span>
                    <span>❤️ " . number_format($bestPost['engagement'] ?? 0) . " engagement</span>
                    <span>📱 " . ucfirst($bestPost['platform'] ?? '') . "</span>
                </div>
            </div>
        </div>";
    }
    
    // Key insights
    $insights = $summaryData['insights'] ?? [];
    $insightsHtml = '';
    
    if (!empty($insights)) {
        $insightsHtml = "<h3 style='color: {$branding['primary_color']}; margin-bottom: 15px;'>💡 Key Insights</h3><ul style='color: #4a5568; padding-left: 20px;'>";
        foreach ($insights as $insight) {
            $insightsHtml .= "<li style='margin-bottom: 8px;'>" . htmlspecialchars($insight) . "</li>";
        }
        $insightsHtml .= "</ul>";
    }
    
    // Recommendations
    $recommendations = $summaryData['recommendations'] ?? [];
    $recommendationsHtml = '';
    
    if (!empty($recommendations)) {
        $recommendationsHtml = "<h3 style='color: {$branding['primary_color']}; margin-bottom: 15px;'>🎯 Recommendations for Next Month</h3><ul style='color: #4a5568; padding-left: 20px;'>";
        foreach ($recommendations as $recommendation) {
            $recommendationsHtml .= "<li style='margin-bottom: 8px;'>" . htmlspecialchars($recommendation) . "</li>";
        }
        $recommendationsHtml .= "</ul>";
    }
    
    $template = "
    <!DOCTYPE html>
    <html lang=\"en\">
    <head>
        <meta charset=\"UTF-8\">
        <meta name=\"viewport\" content=\"width=device-width, initial-scale=1.0\">
        <title>Monthly Social Media Summary - {$currentMonth}</title>
        <style>
            @media only screen and (max-width: 600px) {
                .email-container { width: 100% !important; margin: 0 !important; }
                .content, .header { padding: 20px !important; }
                .metrics-grid { flex-direction: column !important; }
                .metric-card { margin-bottom: 15px !important; }
                .platform-stats { flex-direction: column !important; }
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
            
            .content {
                padding: 40px 30px;
            }
            
            .metrics-grid {
                display: flex;
                flex-wrap: wrap;
                gap: 15px;
                margin: 25px 0;
            }
            
            .metric-card {
                flex: 1;
                min-width: 150px;
                background: white;
                padding: 20px;
                border-radius: 8px;
                text-align: center;
                box-shadow: 0 2px 4px rgba(0,0,0,0.1);
                border: 1px solid #e2e8f0;
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
            }
            
            .footer {
                background-color: #f8fafc;
                padding: 30px;
                border-top: 1px solid #e2e8f0;
                text-align: center;
                color: #64748b;
                font-size: 14px;
            }
        </style>
    </head>
    <body>
        <div class=\"email-container\">
            <div class=\"header\">
                {$logoHtml}
                <h1 style=\"margin: 15px 0 0 0; font-size: 28px; font-weight: 600;\">{$businessName}</h1>
                <p style=\"margin: 10px 0 0 0; opacity: 0.9; font-size: 18px;\">Monthly Performance Summary</p>
            </div>
            
            <div class=\"content\">
                " . ($recipientName ? "<p style=\"font-size: 16px;\">Hi {$recipientName},</p>" : "") . "
                
                <h2 style=\"color: {$branding['primary_color']}; font-size: 24px; margin-bottom: 20px;\">
                    📊 Your {$currentMonth} Social Media Performance
                </h2>
                
                <p style=\"font-size: 16px; margin-bottom: 25px;\">
                    Another month of social media success! Here's a comprehensive overview of how your brand performed across all platforms during {$currentMonth}.
                </p>
                
                <div style=\"background: linear-gradient(135deg, {$branding['primary_color']}15, {$branding['secondary_color']}15); padding: 25px; border-radius: 12px; margin: 25px 0;\">
                    <h3 style=\"margin-top: 0; color: {$branding['primary_color']}; font-size: 20px;\">📈 Overall Performance</h3>
                    <div class=\"metrics-grid\">
                        <div class=\"metric-card\">
                            <div style=\"font-size: 28px; font-weight: bold; color: {$branding['primary_color']};\">{$totalReach}</div>
                            <div style=\"color: #64748b; font-size: 14px; margin: 5px 0;\">Total Reach</div>
                            <div style=\"font-size: 12px; color: #10b981;\">{$reachTrend}</div>
                        </div>
                        <div class=\"metric-card\">
                            <div style=\"font-size: 28px; font-weight: bold; color: {$branding['secondary_color']};\">{$totalEngagement}</div>
                            <div style=\"color: #64748b; font-size: 14px; margin: 5px 0;\">Total Engagement</div>
                            <div style=\"font-size: 12px; color: #10b981;\">{$engagementTrend}</div>
                        </div>
                        <div class=\"metric-card\">
                            <div style=\"font-size: 28px; font-weight: bold; color: {$branding['accent_color']};\">{$newFollowers}</div>
                            <div style=\"color: #64748b; font-size: 14px; margin: 5px 0;\">New Followers</div>
                            <div style=\"font-size: 12px; color: #10b981;\">{$followerTrend}</div>
                        </div>
                        <div class=\"metric-card\">
                            <div style=\"font-size: 28px; font-weight: bold; color: {$branding['primary_color']};\">{$engagementRate}%</div>
                            <div style=\"color: #64748b; font-size: 14px; margin: 5px 0;\">Avg. Engagement Rate</div>
                            <div style=\"font-size: 12px; color: #64748b;\">{$postsPublished} posts</div>
                        </div>
                    </div>
                </div>
                
                {$bestPostHtml}
                
                " . (!empty($platformsHtml) ? "
                <div style=\"margin: 30px 0;\">
                    <h3 style=\"color: {$branding['primary_color']}; margin-bottom: 20px;\">📱 Platform Breakdown</h3>
                    {$platformsHtml}
                </div>
                " : "") . "
                
                " . (!empty($insightsHtml) || !empty($recommendationsHtml) ? "
                <div style=\"background-color: #f8fafc; padding: 25px; border-radius: 12px; margin: 30px 0;\">
                    {$insightsHtml}
                    {$recommendationsHtml}
                </div>
                " : "") . "
                
                <div style=\"text-align: center; margin: 30px 0;\">
                    <a href=\"" . APP_URL . "/dashboard/\" class=\"cta-button\">
                        📊 View Detailed Dashboard
                    </a>
                </div>
                
                <div style=\"background: white; padding: 20px; border-radius: 8px; border: 1px solid #e2e8f0; margin: 25px 0;\">
                    <h4 style=\"color: {$branding['primary_color']}; margin-top: 0;\">📞 Let's Discuss Your Results</h4>
                    <p style=\"margin: 10px 0; color: #4a5568;\">
                        Want to dive deeper into these numbers or plan next month's strategy? 
                        <a href=\"mailto:{$branding['email']}\" style=\"color: {$branding['primary_color']};\">Let's schedule a call</a> 
                        to discuss your social media goals and explore new opportunities.
                    </p>
                </div>
                
                " . (!empty($signature) ? "<div style=\"margin-top: 30px; padding-top: 20px; border-top: 1px solid #e2e8f0; color: #64748b; font-size: 14px;\">{$signature}</div>" : "") . "
            </div>
            
            <div class=\"footer\">
                <p style=\"margin: 0 0 15px 0;\">
                    " . ($branding['website'] ? "<a href=\"{$branding['website']}\" style=\"color: {$branding['primary_color']}; text-decoration: none; margin: 0 10px;\">🌐 Website</a>" : "") . "
                    " . ($branding['email'] ? "<a href=\"mailto:{$branding['email']}\" style=\"color: {$branding['primary_color']}; text-decoration: none; margin: 0 10px;\">✉️ Email</a>" : "") . "
                </p>
                <p style=\"margin: 0; font-size: 12px; color: #94a3b8;\">
                    Summary generated on " . date('F j, Y \a\t g:i A T') . " | Powered by ghst_ Social Media Management
                </p>
            </div>
        </div>
    </body>
    </html>";
    
    return $template;
}

/**
 * Get platform icon emoji
 */
function getPlatformIcon($platform) {
    $icons = [
        'instagram' => '📸',
        'facebook' => '📘',
        'twitter' => '🐦',
        'linkedin' => '💼',
        'tiktok' => '🎵',
        'youtube' => '📺',
        'pinterest' => '📌'
    ];
    
    return $icons[strtolower($platform)] ?? '📱';
}
?>