<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($data['client_info']['business_name'] ?: $data['client_info']['name']) ?> - Social Media Performance Report</title>
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
            max-width: 900px;
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
            font-size: 30px;
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
        
        .summary-section {
            background: linear-gradient(135deg, <?= $branding['primary_color'] ?? '#8B5CF6' ?>, <?= $branding['secondary_color'] ?? '#06b6d4' ?>);
            color: white;
            padding: 40px;
            border-radius: 16px;
            margin: 40px 0;
            text-align: center;
        }
        
        .summary-title {
            font-size: 24px;
            font-weight: bold;
            margin-bottom: 20px;
        }
        
        .summary-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 30px;
            margin-top: 30px;
        }
        
        .summary-metric {
            background: rgba(255,255,255,0.1);
            border-radius: 12px;
            padding: 25px;
            backdrop-filter: blur(10px);
        }
        
        .summary-value {
            font-size: 32px;
            font-weight: bold;
            margin-bottom: 8px;
        }
        
        .summary-label {
            font-size: 14px;
            opacity: 0.9;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .platform-showcase {
            margin: 60px 0;
        }
        
        .platform-showcase-title {
            font-size: 28px;
            font-weight: bold;
            color: <?= $branding['primary_color'] ?? '#8B5CF6' ?>;
            text-align: center;
            margin-bottom: 40px;
        }
        
        .platform-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(400px, 1fr));
            gap: 30px;
        }
        
        .platform-showcase-card {
            background: #fff;
            border-radius: 16px;
            padding: 30px;
            box-shadow: 0 8px 25px rgba(0,0,0,0.1);
            border: 1px solid #f0f0f0;
            position: relative;
            overflow: hidden;
        }
        
        .platform-showcase-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: <?= $branding['primary_color'] ?? '#8B5CF6' ?>;
        }
        
        .platform-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 25px;
        }
        
        .platform-name {
            font-size: 22px;
            font-weight: bold;
            color: <?= $branding['primary_color'] ?? '#8B5CF6' ?>;
            text-transform: capitalize;
        }
        
        .platform-icon {
            width: 40px;
            height: 40px;
            border-radius: 8px;
            background: <?= $branding['primary_color'] ?? '#8B5CF6' ?>;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: bold;
        }
        
        .platform-stats {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin-bottom: 25px;
        }
        
        .stat-item {
            text-align: center;
            padding: 15px;
            background: #f8f9fa;
            border-radius: 10px;
        }
        
        .stat-value {
            font-size: 24px;
            font-weight: bold;
            color: #333;
            margin-bottom: 5px;
        }
        
        .stat-label {
            font-size: 12px;
            color: #666;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .platform-highlight {
            background: <?= $branding['accent_color'] ?? '#10b981' ?>;
            color: white;
            padding: 15px;
            border-radius: 10px;
            text-align: center;
            font-size: 14px;
            margin-bottom: 20px;
        }
        
        .platform-highlight strong {
            display: block;
            font-size: 16px;
            margin-bottom: 5px;
        }
        
        .top-content-section {
            margin: 60px 0;
        }
        
        .section-title {
            font-size: 24px;
            font-weight: bold;
            color: <?= $branding['primary_color'] ?? '#8B5CF6' ?>;
            margin-bottom: 30px;
            text-align: center;
        }
        
        .content-grid {
            display: grid;
            gap: 25px;
        }
        
        .content-card {
            background: #fff;
            border: 1px solid #e5e7eb;
            border-radius: 12px;
            padding: 25px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.05);
            position: relative;
        }
        
        .content-platform {
            position: absolute;
            top: 15px;
            right: 15px;
            background: <?= $branding['primary_color'] ?? '#8B5CF6' ?>;
            color: white;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: bold;
            text-transform: uppercase;
        }
        
        .content-text {
            font-size: 16px;
            line-height: 1.5;
            margin-bottom: 20px;
            color: #374151;
        }
        
        .content-metrics {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding-top: 15px;
            border-top: 1px solid #e5e7eb;
        }
        
        .content-metric {
            text-align: center;
        }
        
        .content-metric-value {
            font-size: 18px;
            font-weight: bold;
            color: <?= $branding['primary_color'] ?? '#8B5CF6' ?>;
        }
        
        .content-metric-label {
            font-size: 12px;
            color: #6b7280;
        }
        
        .engagement-trends {
            margin: 60px 0;
            background: #f8f9fa;
            padding: 40px;
            border-radius: 16px;
        }
        
        .trends-title {
            font-size: 24px;
            font-weight: bold;
            color: <?= $branding['primary_color'] ?? '#8B5CF6' ?>;
            text-align: center;
            margin-bottom: 30px;
        }
        
        .chart-placeholder {
            background: #fff;
            border: 2px dashed #ddd;
            border-radius: 12px;
            height: 300px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #666;
            font-style: italic;
            margin: 20px 0;
        }
        
        .insights-section {
            margin: 60px 0;
        }
        
        .insights-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 25px;
        }
        
        .insight-card {
            background: #fff;
            border-left: 5px solid <?= $branding['accent_color'] ?? '#10b981' ?>;
            border-radius: 8px;
            padding: 25px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        }
        
        .insight-title {
            font-size: 18px;
            font-weight: bold;
            color: <?= $branding['primary_color'] ?? '#8B5CF6' ?>;
            margin-bottom: 15px;
        }
        
        .insight-content {
            font-size: 14px;
            color: #374151;
            line-height: 1.6;
        }
        
        .audience-section {
            margin: 60px 0;
            background: linear-gradient(135deg, #f8f9fa, #e5e7eb);
            padding: 40px;
            border-radius: 16px;
        }
        
        .audience-title {
            font-size: 24px;
            font-weight: bold;
            color: <?= $branding['primary_color'] ?? '#8B5CF6' ?>;
            text-align: center;
            margin-bottom: 30px;
        }
        
        .audience-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
        }
        
        .audience-card {
            background: #fff;
            border-radius: 12px;
            padding: 25px;
            text-align: center;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        }
        
        .audience-metric {
            font-size: 28px;
            font-weight: bold;
            color: <?= $branding['primary_color'] ?? '#8B5CF6' ?>;
            margin-bottom: 8px;
        }
        
        .audience-label {
            font-size: 14px;
            color: #666;
            margin-bottom: 15px;
        }
        
        .audience-change {
            font-size: 12px;
            font-weight: bold;
        }
        
        .change-positive {
            color: #10b981;
        }
        
        .change-negative {
            color: #ef4444;
        }
        
        .footer {
            text-align: center;
            margin-top: 80px;
            padding-top: 40px;
            border-top: 2px solid #eee;
            color: #666;
        }
        
        .footer-branding {
            margin-bottom: 20px;
        }
        
        .footer-contact {
            font-size: 14px;
            margin-bottom: 20px;
        }
        
        .footer-note {
            font-size: 12px;
            color: #999;
            font-style: italic;
        }
        
        @media print {
            .container {
                max-width: none;
                padding: 20px;
            }
            
            .platform-grid {
                grid-template-columns: 1fr 1fr;
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
            
            <h1 class="report-title">Social Media Performance Report</h1>
            <div class="client-name"><?= htmlspecialchars($data['client_info']['business_name'] ?: $data['client_info']['name']) ?></div>
            <div class="date-range"><?= htmlspecialchars($data['date_range']['formatted']) ?></div>
        </div>

        <!-- Summary Section -->
        <div class="summary-section">
            <h2 class="summary-title">Performance Highlights</h2>
            <div class="summary-grid">
                <div class="summary-metric">
                    <div class="summary-value"><?= number_format($data['overview']['total_posts']) ?></div>
                    <div class="summary-label">Posts Published</div>
                </div>
                <div class="summary-metric">
                    <div class="summary-value"><?= number_format($data['overview']['total_reach']) ?></div>
                    <div class="summary-label">Total Reach</div>
                </div>
                <div class="summary-metric">
                    <div class="summary-value"><?= number_format($data['overview']['avg_engagement_rate'], 1) ?>%</div>
                    <div class="summary-label">Avg Engagement</div>
                </div>
                <div class="summary-metric">
                    <div class="summary-value"><?= number_format($data['overview']['total_engagement']) ?></div>
                    <div class="summary-label">Total Interactions</div>
                </div>
            </div>
        </div>

        <!-- Platform Showcase -->
        <div class="platform-showcase">
            <h2 class="platform-showcase-title">Platform Performance Breakdown</h2>
            <div class="platform-grid">
                <?php foreach ($data['platform_performance'] as $platform): ?>
                    <?php if ($platform['post_count'] > 0): ?>
                        <div class="platform-showcase-card">
                            <div class="platform-header">
                                <div class="platform-name"><?= htmlspecialchars($platform['platform']) ?></div>
                                <div class="platform-icon">
                                    <?= strtoupper(substr($platform['platform'], 0, 2)) ?>
                                </div>
                            </div>
                            
                            <?php if ($platform['avg_engagement_rate'] > 0): ?>
                                <div class="platform-highlight">
                                    <strong><?= number_format($platform['avg_engagement_rate'], 2) ?>% Engagement Rate</strong>
                                    <?php if ($platform['avg_engagement_rate'] > 3): ?>
                                        Excellent performance!
                                    <?php elseif ($platform['avg_engagement_rate'] > 1.5): ?>
                                        Good engagement
                                    <?php else: ?>
                                        Room for improvement
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>
                            
                            <div class="platform-stats">
                                <div class="stat-item">
                                    <div class="stat-value"><?= number_format($platform['post_count']) ?></div>
                                    <div class="stat-label">Posts</div>
                                </div>
                                <div class="stat-item">
                                    <div class="stat-value"><?= number_format($platform['total_reach']) ?></div>
                                    <div class="stat-label">Reach</div>
                                </div>
                                <div class="stat-item">
                                    <div class="stat-value"><?= number_format($platform['total_impressions']) ?></div>
                                    <div class="stat-label">Impressions</div>
                                </div>
                                <div class="stat-item">
                                    <div class="stat-value"><?= number_format($platform['current_followers']) ?></div>
                                    <div class="stat-label">Followers</div>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Top Content -->
        <?php if (!empty($data['top_posts'])): ?>
            <div class="top-content-section">
                <h2 class="section-title">Top Performing Content</h2>
                <div class="content-grid">
                    <?php foreach (array_slice($data['top_posts'], 0, 6) as $post): ?>
                        <div class="content-card">
                            <div class="content-platform"><?= htmlspecialchars($post['platform']) ?></div>
                            <div class="content-text"><?= htmlspecialchars($post['content']) ?></div>
                            <div class="content-metrics">
                                <div class="content-metric">
                                    <div class="content-metric-value"><?= number_format($post['engagement_rate'], 1) ?>%</div>
                                    <div class="content-metric-label">Engagement</div>
                                </div>
                                <div class="content-metric">
                                    <div class="content-metric-value"><?= number_format($post['reach']) ?></div>
                                    <div class="content-metric-label">Reach</div>
                                </div>
                                <div class="content-metric">
                                    <div class="content-metric-value"><?= number_format($post['likes']) ?></div>
                                    <div class="content-metric-label">Likes</div>
                                </div>
                                <div class="content-metric">
                                    <div class="content-metric-value"><?= number_format($post['comments']) ?></div>
                                    <div class="content-metric-label">Comments</div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>

        <!-- Engagement Trends -->
        <div class="engagement-trends">
            <h2 class="trends-title">Engagement Trends Over Time</h2>
            <div class="chart-placeholder">
                Engagement Rate Trend Chart - Would show daily/weekly engagement patterns
            </div>
        </div>

        <!-- Audience Growth -->
        <?php 
        $totalCurrentFollowers = array_sum(array_column($data['platform_performance'], 'current_followers'));
        $audienceGrowthData = $data['audience_growth'];
        $totalGrowth = 0;
        if (!empty($audienceGrowthData)) {
            $totalGrowth = array_sum(array_column($audienceGrowthData, 'daily_growth'));
        }
        ?>
        <div class="audience-section">
            <h2 class="audience-title">Audience Growth & Demographics</h2>
            <div class="audience-grid">
                <div class="audience-card">
                    <div class="audience-metric"><?= number_format($totalCurrentFollowers) ?></div>
                    <div class="audience-label">Total Followers</div>
                    <?php if ($totalGrowth != 0): ?>
                        <div class="audience-change <?= $totalGrowth > 0 ? 'change-positive' : 'change-negative' ?>">
                            <?= $totalGrowth > 0 ? '+' : '' ?><?= number_format($totalGrowth) ?> this period
                        </div>
                    <?php endif; ?>
                </div>
                
                <?php foreach ($data['platform_performance'] as $platform): ?>
                    <?php if ($platform['current_followers'] > 0): ?>
                        <div class="audience-card">
                            <div class="audience-metric"><?= number_format($platform['current_followers']) ?></div>
                            <div class="audience-label"><?= ucfirst($platform['platform']) ?> Followers</div>
                        </div>
                    <?php endif; ?>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Key Insights -->
        <div class="insights-section">
            <h2 class="section-title">Key Insights & Observations</h2>
            <div class="insights-grid">
                <!-- Best Performing Platform -->
                <?php 
                $bestPlatform = null;
                $bestEngagement = 0;
                foreach ($data['platform_performance'] as $platform) {
                    if ($platform['post_count'] > 0 && $platform['avg_engagement_rate'] > $bestEngagement) {
                        $bestEngagement = $platform['avg_engagement_rate'];
                        $bestPlatform = $platform;
                    }
                }
                ?>
                <?php if ($bestPlatform): ?>
                    <div class="insight-card">
                        <div class="insight-title">Top Performing Platform</div>
                        <div class="insight-content">
                            <strong><?= ucfirst($bestPlatform['platform']) ?></strong> is your best performing platform with 
                            <strong><?= number_format($bestPlatform['avg_engagement_rate'], 2) ?>%</strong> average engagement rate. 
                            This platform generated <strong><?= number_format($bestPlatform['total_reach']) ?></strong> total reach 
                            across <strong><?= $bestPlatform['post_count'] ?></strong> posts.
                        </div>
                    </div>
                <?php endif; ?>

                <!-- Content Performance -->
                <?php if (!empty($data['content_analysis'])): ?>
                    <?php $bestContent = $data['content_analysis'][0]; ?>
                    <div class="insight-card">
                        <div class="insight-title">Best Content Type</div>
                        <div class="insight-content">
                            <strong><?= ucfirst($bestContent['content_type']) ?></strong> content performs best with 
                            <strong><?= number_format($bestContent['avg_engagement_rate'], 2) ?>%</strong> average engagement rate. 
                            Consider creating more of this content type to maximize engagement.
                        </div>
                    </div>
                <?php endif; ?>

                <!-- Growth Analysis -->
                <div class="insight-card">
                    <div class="insight-title">Growth Analysis</div>
                    <div class="insight-content">
                        <?php if ($data['overview']['total_reach_growth'] > 0): ?>
                            Your reach increased by <strong><?= number_format($data['overview']['total_reach_growth'], 1) ?>%</strong> 
                            compared to the previous period, indicating strong content distribution and audience expansion.
                        <?php elseif ($data['overview']['total_reach_growth'] < 0): ?>
                            Your reach decreased by <strong><?= abs(number_format($data['overview']['total_reach_growth'], 1)) ?>%</strong> 
                            compared to the previous period. Consider reviewing content strategy and posting frequency.
                        <?php else: ?>
                            Your reach remained stable compared to the previous period. Consider experimenting with 
                            new content formats or posting times to drive growth.
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Engagement Quality -->
                <div class="insight-card">
                    <div class="insight-title">Engagement Quality</div>
                    <div class="insight-content">
                        <?php 
                        $avgEngagement = $data['overview']['avg_engagement_rate'];
                        if ($avgEngagement > 3): ?>
                            Excellent! Your <strong><?= number_format($avgEngagement, 2) ?>%</strong> engagement rate 
                            exceeds industry benchmarks. Your audience is highly engaged with your content.
                        <?php elseif ($avgEngagement > 1.5): ?>
                            Good engagement at <strong><?= number_format($avgEngagement, 2) ?>%</strong>. 
                            This is within healthy industry ranges. Focus on consistency to maintain this level.
                        <?php else: ?>
                            Your <strong><?= number_format($avgEngagement, 2) ?>%</strong> engagement rate has room for improvement. 
                            Consider more interactive content, questions, and calls-to-action.
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

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
            <div class="footer-note">
                This social media performance report was generated on <?= date('F j, Y \a\t g:i A') ?>.<br>
                Data represents performance metrics from <?= htmlspecialchars($data['date_range']['formatted']) ?>.
            </div>
        </div>
    </div>
</body>
</html>