<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($data['client_info']['business_name'] ?: $data['client_info']['name']) ?> - Executive Summary</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: <?= $branding['font_family'] ?? 'Arial, sans-serif' ?>;
            line-height: 1.6;
            color: #333;
            background: #fff;
        }
        
        .container {
            max-width: 800px;
            margin: 0 auto;
            padding: 40px 20px;
        }
        
        .header {
            text-align: center;
            margin-bottom: 40px;
            border-bottom: 3px solid <?= $branding['primary_color'] ?? '#8B5CF6' ?>;
            padding-bottom: 20px;
        }
        
        .logo {
            max-height: 80px;
            margin-bottom: 20px;
        }
        
        .report-title {
            font-size: 28px;
            font-weight: bold;
            color: <?= $branding['primary_color'] ?? '#8B5CF6' ?>;
            margin-bottom: 10px;
        }
        
        .client-name {
            font-size: 20px;
            color: #666;
            margin-bottom: 5px;
        }
        
        .date-range {
            font-size: 16px;
            color: #888;
        }
        
        .summary-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin: 40px 0;
        }
        
        .metric-card {
            background: #f8f9fa;
            border-left: 4px solid <?= $branding['primary_color'] ?? '#8B5CF6' ?>;
            padding: 20px;
            border-radius: 8px;
            text-align: center;
        }
        
        .metric-value {
            font-size: 32px;
            font-weight: bold;
            color: <?= $branding['primary_color'] ?? '#8B5CF6' ?>;
            margin-bottom: 5px;
        }
        
        .metric-label {
            font-size: 14px;
            color: #666;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .metric-growth {
            font-size: 12px;
            margin-top: 5px;
        }
        
        .growth-positive {
            color: #10b981;
        }
        
        .growth-negative {
            color: #ef4444;
        }
        
        .section {
            margin: 40px 0;
        }
        
        .section-title {
            font-size: 20px;
            font-weight: bold;
            color: <?= $branding['primary_color'] ?? '#8B5CF6' ?>;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 2px solid #eee;
        }
        
        .platform-performance {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
        }
        
        .platform-card {
            background: #fff;
            border: 1px solid #ddd;
            border-radius: 8px;
            padding: 20px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        
        .platform-name {
            font-size: 18px;
            font-weight: bold;
            text-transform: capitalize;
            margin-bottom: 15px;
            color: <?= $branding['primary_color'] ?? '#8B5CF6' ?>;
        }
        
        .platform-metrics {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 10px;
        }
        
        .platform-metric {
            text-align: center;
        }
        
        .platform-metric-value {
            font-size: 18px;
            font-weight: bold;
            color: #333;
        }
        
        .platform-metric-label {
            font-size: 12px;
            color: #666;
        }
        
        .top-posts {
            margin: 20px 0;
        }
        
        .post-item {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 15px;
            border-left: 4px solid <?= $branding['accent_color'] ?? '#10b981' ?>;
        }
        
        .post-content {
            font-size: 14px;
            color: #333;
            margin-bottom: 10px;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }
        
        .post-metrics {
            display: flex;
            justify-content: space-between;
            font-size: 12px;
            color: #666;
        }
        
        .recommendations {
            background: linear-gradient(135deg, <?= $branding['primary_color'] ?? '#8B5CF6' ?>, <?= $branding['secondary_color'] ?? '#06b6d4' ?>);
            color: white;
            padding: 30px;
            border-radius: 12px;
            margin: 40px 0;
        }
        
        .recommendations-title {
            font-size: 22px;
            font-weight: bold;
            margin-bottom: 20px;
        }
        
        .recommendation-item {
            background: rgba(255,255,255,0.1);
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 15px;
        }
        
        .recommendation-title {
            font-weight: bold;
            margin-bottom: 5px;
        }
        
        .recommendation-description {
            font-size: 14px;
            opacity: 0.9;
        }
        
        .footer {
            text-align: center;
            margin-top: 60px;
            padding-top: 30px;
            border-top: 2px solid #eee;
            color: #666;
        }
        
        .footer-branding {
            margin-bottom: 20px;
        }
        
        .footer-contact {
            font-size: 14px;
        }
        
        @media print {
            .container {
                max-width: none;
                padding: 20px;
            }
            
            .summary-grid {
                grid-template-columns: repeat(4, 1fr);
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- Header -->
        <div class="header">
            <?php if ($branding['logo_url']): ?>
                <img src="<?= htmlspecialchars($branding['logo_url']) ?>" alt="Logo" class="logo">
            <?php endif; ?>
            
            <h1 class="report-title">Executive Summary</h1>
            <div class="client-name"><?= htmlspecialchars($data['client_info']['business_name'] ?: $data['client_info']['name']) ?></div>
            <div class="date-range"><?= htmlspecialchars($data['date_range']['formatted']) ?></div>
        </div>

        <!-- Key Metrics Summary -->
        <div class="summary-grid">
            <div class="metric-card">
                <div class="metric-value"><?= number_format($data['overview']['total_posts']) ?></div>
                <div class="metric-label">Total Posts</div>
                <?php if ($data['overview']['total_posts_growth'] != 0): ?>
                    <div class="metric-growth <?= $data['overview']['total_posts_growth'] > 0 ? 'growth-positive' : 'growth-negative' ?>">
                        <?= $data['overview']['total_posts_growth'] > 0 ? '+' : '' ?><?= number_format($data['overview']['total_posts_growth'], 1) ?>%
                    </div>
                <?php endif; ?>
            </div>
            
            <div class="metric-card">
                <div class="metric-value"><?= number_format($data['overview']['total_reach']) ?></div>
                <div class="metric-label">Total Reach</div>
                <?php if ($data['overview']['total_reach_growth'] != 0): ?>
                    <div class="metric-growth <?= $data['overview']['total_reach_growth'] > 0 ? 'growth-positive' : 'growth-negative' ?>">
                        <?= $data['overview']['total_reach_growth'] > 0 ? '+' : '' ?><?= number_format($data['overview']['total_reach_growth'], 1) ?>%
                    </div>
                <?php endif; ?>
            </div>
            
            <div class="metric-card">
                <div class="metric-value"><?= number_format($data['overview']['avg_engagement_rate'], 2) ?>%</div>
                <div class="metric-label">Avg Engagement Rate</div>
                <?php if ($data['overview']['avg_engagement_rate_growth'] != 0): ?>
                    <div class="metric-growth <?= $data['overview']['avg_engagement_rate_growth'] > 0 ? 'growth-positive' : 'growth-negative' ?>">
                        <?= $data['overview']['avg_engagement_rate_growth'] > 0 ? '+' : '' ?><?= number_format($data['overview']['avg_engagement_rate_growth'], 1) ?>%
                    </div>
                <?php endif; ?>
            </div>
            
            <div class="metric-card">
                <div class="metric-value"><?= number_format($data['overview']['total_engagement']) ?></div>
                <div class="metric-label">Total Engagement</div>
                <?php if ($data['overview']['total_engagement_growth'] != 0): ?>
                    <div class="metric-growth <?= $data['overview']['total_engagement_growth'] > 0 ? 'growth-positive' : 'growth-negative' ?>">
                        <?= $data['overview']['total_engagement_growth'] > 0 ? '+' : '' ?><?= number_format($data['overview']['total_engagement_growth'], 1) ?>%
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Platform Performance -->
        <div class="section">
            <h2 class="section-title">Platform Performance</h2>
            <div class="platform-performance">
                <?php foreach ($data['platform_performance'] as $platform): ?>
                    <?php if ($platform['post_count'] > 0): ?>
                        <div class="platform-card">
                            <div class="platform-name"><?= htmlspecialchars($platform['platform']) ?></div>
                            <div class="platform-metrics">
                                <div class="platform-metric">
                                    <div class="platform-metric-value"><?= number_format($platform['post_count']) ?></div>
                                    <div class="platform-metric-label">Posts</div>
                                </div>
                                <div class="platform-metric">
                                    <div class="platform-metric-value"><?= number_format($platform['avg_engagement_rate'], 2) ?>%</div>
                                    <div class="platform-metric-label">Engagement</div>
                                </div>
                                <div class="platform-metric">
                                    <div class="platform-metric-value"><?= number_format($platform['total_reach']) ?></div>
                                    <div class="platform-metric-label">Reach</div>
                                </div>
                                <div class="platform-metric">
                                    <div class="platform-metric-value"><?= number_format($platform['current_followers']) ?></div>
                                    <div class="platform-metric-label">Followers</div>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Top Performing Posts -->
        <?php if (!empty($data['top_posts'])): ?>
            <div class="section">
                <h2 class="section-title">Top Performing Posts</h2>
                <div class="top-posts">
                    <?php foreach (array_slice($data['top_posts'], 0, 5) as $post): ?>
                        <div class="post-item">
                            <div class="post-content"><?= htmlspecialchars($post['content']) ?></div>
                            <div class="post-metrics">
                                <span><?= ucfirst($post['platform']) ?></span>
                                <span><?= number_format($post['engagement_rate'], 2) ?>% Engagement</span>
                                <span><?= number_format($post['total_engagement']) ?> Total Interactions</span>
                                <span><?= date('M j, Y', strtotime($post['published_at'])) ?></span>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>

        <!-- Recommendations -->
        <?php if (!empty($data['recommendations'])): ?>
            <div class="recommendations">
                <h2 class="recommendations-title">Key Recommendations</h2>
                <?php foreach (array_slice($data['recommendations'], 0, 3) as $rec): ?>
                    <div class="recommendation-item">
                        <div class="recommendation-title"><?= htmlspecialchars($rec['title']) ?></div>
                        <div class="recommendation-description"><?= htmlspecialchars($rec['description']) ?></div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <!-- Footer -->
        <div class="footer">
            <div class="footer-branding">
                <?php if ($branding['business_name']): ?>
                    <strong><?= htmlspecialchars($branding['business_name']) ?></strong>
                <?php endif; ?>
            </div>
            <div class="footer-contact">
                <?php if ($branding['contact_email']): ?>
                    Email: <?= htmlspecialchars($branding['contact_email']) ?>
                <?php endif; ?>
                <?php if ($branding['phone_number']): ?>
                    | Phone: <?= htmlspecialchars($branding['phone_number']) ?>
                <?php endif; ?>
                <?php if ($branding['website_url']): ?>
                    | Website: <?= htmlspecialchars($branding['website_url']) ?>
                <?php endif; ?>
            </div>
            <div style="margin-top: 20px; font-size: 12px; color: #999;">
                Report generated on <?= date('F j, Y \a\t g:i A') ?>
            </div>
        </div>
    </div>
</body>
</html>