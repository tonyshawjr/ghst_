<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($data['client_info']['business_name'] ?: $data['client_info']['name']) ?> - Detailed Analytics Report</title>
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
            max-width: 1000px;
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
            font-size: 32px;
            font-weight: bold;
            color: <?= $branding['primary_color'] ?? '#8B5CF6' ?>;
            margin-bottom: 10px;
        }
        
        .client-name {
            font-size: 22px;
            color: #666;
            margin-bottom: 5px;
        }
        
        .date-range {
            font-size: 18px;
            color: #888;
        }
        
        .toc {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 30px;
            margin: 40px 0;
        }
        
        .toc-title {
            font-size: 20px;
            font-weight: bold;
            color: <?= $branding['primary_color'] ?? '#8B5CF6' ?>;
            margin-bottom: 20px;
        }
        
        .toc-list {
            list-style: none;
            columns: 2;
            column-gap: 40px;
        }
        
        .toc-item {
            margin-bottom: 10px;
            padding: 5px 0;
            border-bottom: 1px dotted #ddd;
        }
        
        .toc-item a {
            text-decoration: none;
            color: #666;
            font-size: 14px;
        }
        
        .toc-item a:hover {
            color: <?= $branding['primary_color'] ?? '#8B5CF6' ?>;
        }
        
        .section {
            margin: 60px 0;
            page-break-before: avoid;
        }
        
        .section-title {
            font-size: 24px;
            font-weight: bold;
            color: <?= $branding['primary_color'] ?? '#8B5CF6' ?>;
            margin-bottom: 30px;
            padding-bottom: 15px;
            border-bottom: 2px solid #eee;
        }
        
        .metrics-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin: 30px 0;
        }
        
        .metric-card {
            background: #fff;
            border: 1px solid #ddd;
            border-left: 4px solid <?= $branding['primary_color'] ?? '#8B5CF6' ?>;
            border-radius: 8px;
            padding: 25px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        
        .metric-value {
            font-size: 36px;
            font-weight: bold;
            color: <?= $branding['primary_color'] ?? '#8B5CF6' ?>;
            margin-bottom: 8px;
        }
        
        .metric-label {
            font-size: 14px;
            color: #666;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 10px;
        }
        
        .metric-growth {
            font-size: 14px;
            font-weight: bold;
        }
        
        .growth-positive {
            color: #10b981;
        }
        
        .growth-negative {
            color: #ef4444;
        }
        
        .chart-placeholder {
            background: #f8f9fa;
            border: 2px dashed #ddd;
            border-radius: 8px;
            height: 300px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #666;
            font-style: italic;
            margin: 20px 0;
        }
        
        .platform-section {
            margin: 40px 0;
        }
        
        .platform-card {
            background: #fff;
            border: 1px solid #ddd;
            border-radius: 12px;
            padding: 30px;
            margin-bottom: 30px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        }
        
        .platform-header {
            display: flex;
            align-items: center;
            margin-bottom: 25px;
        }
        
        .platform-name {
            font-size: 24px;
            font-weight: bold;
            text-transform: capitalize;
            color: <?= $branding['primary_color'] ?? '#8B5CF6' ?>;
            margin-right: 20px;
        }
        
        .platform-status {
            background: #10b981;
            color: white;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: bold;
        }
        
        .platform-metrics {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 20px;
        }
        
        .platform-metric {
            text-align: center;
            padding: 15px;
            background: #f8f9fa;
            border-radius: 8px;
        }
        
        .platform-metric-value {
            font-size: 24px;
            font-weight: bold;
            color: #333;
            margin-bottom: 5px;
        }
        
        .platform-metric-label {
            font-size: 12px;
            color: #666;
            text-transform: uppercase;
        }
        
        .posts-table {
            width: 100%;
            border-collapse: collapse;
            margin: 20px 0;
            background: #fff;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        
        .posts-table th {
            background: <?= $branding['primary_color'] ?? '#8B5CF6' ?>;
            color: white;
            padding: 15px 10px;
            text-align: left;
            font-weight: bold;
            font-size: 14px;
        }
        
        .posts-table td {
            padding: 12px 10px;
            border-bottom: 1px solid #eee;
            font-size: 14px;
        }
        
        .posts-table tr:hover {
            background: #f8f9fa;
        }
        
        .post-content {
            max-width: 300px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }
        
        .engagement-badge {
            background: #10b981;
            color: white;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 12px;
            font-weight: bold;
        }
        
        .engagement-badge.high {
            background: #10b981;
        }
        
        .engagement-badge.medium {
            background: #f59e0b;
        }
        
        .engagement-badge.low {
            background: #6b7280;
        }
        
        .recommendations-section {
            background: linear-gradient(135deg, <?= $branding['primary_color'] ?? '#8B5CF6' ?>, <?= $branding['secondary_color'] ?? '#06b6d4' ?>);
            color: white;
            padding: 40px;
            border-radius: 16px;
            margin: 60px 0;
        }
        
        .recommendations-title {
            font-size: 28px;
            font-weight: bold;
            margin-bottom: 30px;
            text-align: center;
        }
        
        .recommendations-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 25px;
        }
        
        .recommendation-card {
            background: rgba(255,255,255,0.1);
            border-radius: 12px;
            padding: 25px;
            backdrop-filter: blur(10px);
        }
        
        .recommendation-priority {
            display: inline-block;
            background: rgba(255,255,255,0.2);
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: bold;
            margin-bottom: 15px;
            text-transform: uppercase;
        }
        
        .recommendation-title {
            font-size: 18px;
            font-weight: bold;
            margin-bottom: 10px;
        }
        
        .recommendation-description {
            font-size: 14px;
            opacity: 0.9;
            line-height: 1.5;
            margin-bottom: 15px;
        }
        
        .recommendation-action {
            font-size: 14px;
            font-weight: bold;
            background: rgba(255,255,255,0.2);
            padding: 10px 15px;
            border-radius: 8px;
            border-left: 4px solid rgba(255,255,255,0.5);
        }
        
        .hashtag-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            gap: 15px;
            margin: 20px 0;
        }
        
        .hashtag-card {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 15px;
            text-align: center;
            border-left: 4px solid <?= $branding['accent_color'] ?? '#10b981' ?>;
        }
        
        .hashtag-name {
            font-weight: bold;
            color: <?= $branding['primary_color'] ?? '#8B5CF6' ?>;
            margin-bottom: 10px;
        }
        
        .hashtag-stats {
            font-size: 12px;
            color: #666;
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
            
            .section {
                page-break-inside: avoid;
            }
            
            .metrics-grid {
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
            
            <h1 class="report-title">Detailed Analytics Report</h1>
            <div class="client-name"><?= htmlspecialchars($data['client_info']['business_name'] ?: $data['client_info']['name']) ?></div>
            <div class="date-range"><?= htmlspecialchars($data['date_range']['formatted']) ?></div>
        </div>

        <!-- Table of Contents -->
        <div class="toc">
            <h2 class="toc-title">Table of Contents</h2>
            <ul class="toc-list">
                <li class="toc-item"><a href="#overview">Executive Overview</a></li>
                <li class="toc-item"><a href="#platform-performance">Platform Performance</a></li>
                <li class="toc-item"><a href="#top-posts">Top Performing Content</a></li>
                <li class="toc-item"><a href="#audience-growth">Audience Growth</a></li>
                <li class="toc-item"><a href="#engagement-trends">Engagement Trends</a></li>
                <li class="toc-item"><a href="#content-analysis">Content Analysis</a></li>
                <li class="toc-item"><a href="#hashtag-performance">Hashtag Performance</a></li>
                <li class="toc-item"><a href="#optimal-timing">Optimal Posting Times</a></li>
                <li class="toc-item"><a href="#competitor-analysis">Competitor Analysis</a></li>
                <li class="toc-item"><a href="#recommendations">Recommendations</a></li>
            </ul>
        </div>

        <!-- Executive Overview -->
        <div class="section" id="overview">
            <h2 class="section-title">Executive Overview</h2>
            <div class="metrics-grid">
                <div class="metric-card">
                    <div class="metric-value"><?= number_format($data['overview']['total_posts']) ?></div>
                    <div class="metric-label">Total Posts Published</div>
                    <?php if ($data['overview']['total_posts_growth'] != 0): ?>
                        <div class="metric-growth <?= $data['overview']['total_posts_growth'] > 0 ? 'growth-positive' : 'growth-negative' ?>">
                            <?= $data['overview']['total_posts_growth'] > 0 ? '+' : '' ?><?= number_format($data['overview']['total_posts_growth'], 1) ?>% vs previous period
                        </div>
                    <?php endif; ?>
                </div>
                
                <div class="metric-card">
                    <div class="metric-value"><?= number_format($data['overview']['total_reach']) ?></div>
                    <div class="metric-label">Total Reach</div>
                    <?php if ($data['overview']['total_reach_growth'] != 0): ?>
                        <div class="metric-growth <?= $data['overview']['total_reach_growth'] > 0 ? 'growth-positive' : 'growth-negative' ?>">
                            <?= $data['overview']['total_reach_growth'] > 0 ? '+' : '' ?><?= number_format($data['overview']['total_reach_growth'], 1) ?>% vs previous period
                        </div>
                    <?php endif; ?>
                </div>
                
                <div class="metric-card">
                    <div class="metric-value"><?= number_format($data['overview']['total_impressions']) ?></div>
                    <div class="metric-label">Total Impressions</div>
                    <?php if ($data['overview']['total_impressions_growth'] != 0): ?>
                        <div class="metric-growth <?= $data['overview']['total_impressions_growth'] > 0 ? 'growth-positive' : 'growth-negative' ?>">
                            <?= $data['overview']['total_impressions_growth'] > 0 ? '+' : '' ?><?= number_format($data['overview']['total_impressions_growth'], 1) ?>% vs previous period
                        </div>
                    <?php endif; ?>
                </div>
                
                <div class="metric-card">
                    <div class="metric-value"><?= number_format($data['overview']['avg_engagement_rate'], 2) ?>%</div>
                    <div class="metric-label">Average Engagement Rate</div>
                    <?php if ($data['overview']['avg_engagement_rate_growth'] != 0): ?>
                        <div class="metric-growth <?= $data['overview']['avg_engagement_rate_growth'] > 0 ? 'growth-positive' : 'growth-negative' ?>">
                            <?= $data['overview']['avg_engagement_rate_growth'] > 0 ? '+' : '' ?><?= number_format($data['overview']['avg_engagement_rate_growth'], 1) ?>% vs previous period
                        </div>
                    <?php endif; ?>
                </div>
                
                <div class="metric-card">
                    <div class="metric-value"><?= number_format($data['overview']['total_engagement']) ?></div>
                    <div class="metric-label">Total Engagement</div>
                    <?php if ($data['overview']['total_engagement_growth'] != 0): ?>
                        <div class="metric-growth <?= $data['overview']['total_engagement_growth'] > 0 ? 'growth-positive' : 'growth-negative' ?>">
                            <?= $data['overview']['total_engagement_growth'] > 0 ? '+' : '' ?><?= number_format($data['overview']['total_engagement_growth'], 1) ?>% vs previous period
                        </div>
                    <?php endif; ?>
                </div>
                
                <div class="metric-card">
                    <div class="metric-value"><?= number_format($data['overview']['total_clicks']) ?></div>
                    <div class="metric-label">Total Clicks</div>
                    <?php if ($data['overview']['total_clicks_growth'] != 0): ?>
                        <div class="metric-growth <?= $data['overview']['total_clicks_growth'] > 0 ? 'growth-positive' : 'growth-negative' ?>">
                            <?= $data['overview']['total_clicks_growth'] > 0 ? '+' : '' ?><?= number_format($data['overview']['total_clicks_growth'], 1) ?>% vs previous period
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <div class="chart-placeholder">
                Engagement Trends Chart - Would be replaced with actual chart library (Chart.js, etc.)
            </div>
        </div>

        <!-- Platform Performance -->
        <div class="section" id="platform-performance">
            <h2 class="section-title">Platform Performance Breakdown</h2>
            <div class="platform-section">
                <?php foreach ($data['platform_performance'] as $platform): ?>
                    <?php if ($platform['post_count'] > 0): ?>
                        <div class="platform-card">
                            <div class="platform-header">
                                <div class="platform-name"><?= ucfirst(htmlspecialchars($platform['platform'])) ?></div>
                                <div class="platform-status">Active</div>
                            </div>
                            <div class="platform-metrics">
                                <div class="platform-metric">
                                    <div class="platform-metric-value"><?= number_format($platform['post_count']) ?></div>
                                    <div class="platform-metric-label">Posts Published</div>
                                </div>
                                <div class="platform-metric">
                                    <div class="platform-metric-value"><?= number_format($platform['avg_engagement_rate'], 2) ?>%</div>
                                    <div class="platform-metric-label">Avg Engagement Rate</div>
                                </div>
                                <div class="platform-metric">
                                    <div class="platform-metric-value"><?= number_format($platform['total_reach']) ?></div>
                                    <div class="platform-metric-label">Total Reach</div>
                                </div>
                                <div class="platform-metric">
                                    <div class="platform-metric-value"><?= number_format($platform['total_impressions']) ?></div>
                                    <div class="platform-metric-label">Total Impressions</div>
                                </div>
                                <div class="platform-metric">
                                    <div class="platform-metric-value"><?= number_format($platform['total_engagement']) ?></div>
                                    <div class="platform-metric-label">Total Engagement</div>
                                </div>
                                <div class="platform-metric">
                                    <div class="platform-metric-value"><?= number_format($platform['current_followers']) ?></div>
                                    <div class="platform-metric-label">Current Followers</div>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Top Performing Posts -->
        <?php if (!empty($data['top_posts'])): ?>
            <div class="section" id="top-posts">
                <h2 class="section-title">Top Performing Content</h2>
                <table class="posts-table">
                    <thead>
                        <tr>
                            <th>Content</th>
                            <th>Platform</th>
                            <th>Published</th>
                            <th>Engagement Rate</th>
                            <th>Reach</th>
                            <th>Total Engagement</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($data['top_posts'] as $post): ?>
                            <tr>
                                <td class="post-content" title="<?= htmlspecialchars($post['content']) ?>">
                                    <?= htmlspecialchars($post['content']) ?>
                                </td>
                                <td><?= ucfirst(htmlspecialchars($post['platform'])) ?></td>
                                <td><?= date('M j, Y', strtotime($post['published_at'])) ?></td>
                                <td>
                                    <span class="engagement-badge <?= $post['engagement_rate'] > 5 ? 'high' : ($post['engagement_rate'] > 2 ? 'medium' : 'low') ?>">
                                        <?= number_format($post['engagement_rate'], 2) ?>%
                                    </span>
                                </td>
                                <td><?= number_format($post['reach']) ?></td>
                                <td><?= number_format($post['total_engagement']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>

        <!-- Content Analysis -->
        <?php if (!empty($data['content_analysis'])): ?>
            <div class="section" id="content-analysis">
                <h2 class="section-title">Content Type Performance</h2>
                <div class="metrics-grid">
                    <?php foreach ($data['content_analysis'] as $content): ?>
                        <div class="metric-card">
                            <div class="metric-value"><?= number_format($content['avg_engagement_rate'], 2) ?>%</div>
                            <div class="metric-label"><?= ucfirst(htmlspecialchars($content['content_type'])) ?> Content</div>
                            <div style="font-size: 12px; color: #666; margin-top: 10px;">
                                <?= number_format($content['post_count']) ?> posts • 
                                <?= number_format($content['avg_reach']) ?> avg reach
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>

        <!-- Hashtag Performance -->
        <?php if (!empty($data['hashtag_performance'])): ?>
            <div class="section" id="hashtag-performance">
                <h2 class="section-title">Hashtag Performance</h2>
                <div class="hashtag-grid">
                    <?php foreach (array_slice($data['hashtag_performance'], 0, 12) as $hashtag): ?>
                        <div class="hashtag-card">
                            <div class="hashtag-name">#<?= htmlspecialchars($hashtag['hashtag']) ?></div>
                            <div class="hashtag-stats">
                                <?= number_format($hashtag['avg_engagement_rate'], 2) ?>% engagement<br>
                                Used <?= $hashtag['usage_count'] ?> times<br>
                                <?= number_format($hashtag['total_reach']) ?> total reach
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>

        <!-- Optimal Timing -->
        <?php if (!empty($data['optimal_timing'])): ?>
            <div class="section" id="optimal-timing">
                <h2 class="section-title">Optimal Posting Times</h2>
                <div class="chart-placeholder">
                    Posting Times Heatmap - Would show best performing days/hours
                </div>
                <div class="metrics-grid">
                    <?php 
                    $dayNames = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
                    foreach (array_slice($data['optimal_timing'], 0, 6) as $timing): 
                    ?>
                        <div class="metric-card">
                            <div class="metric-value"><?= number_format($timing['avg_engagement_rate'], 2) ?>%</div>
                            <div class="metric-label">
                                <?= $dayNames[$timing['day_of_week']] ?> at <?= $timing['hour_of_day'] ?>:00
                            </div>
                            <div style="font-size: 12px; color: #666; margin-top: 10px;">
                                <?= $timing['post_count'] ?> posts • <?= ucfirst($timing['platform']) ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>

        <!-- Competitor Analysis -->
        <?php if (!empty($data['competitor_comparison'])): ?>
            <div class="section" id="competitor-analysis">
                <h2 class="section-title">Competitor Benchmark</h2>
                <table class="posts-table">
                    <thead>
                        <tr>
                            <th>Competitor</th>
                            <th>Platform</th>
                            <th>Followers</th>
                            <th>Engagement Rate</th>
                            <th>Posts/Week</th>
                            <th>Growth</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($data['competitor_comparison'] as $competitor): ?>
                            <tr>
                                <td><?= htmlspecialchars($competitor['competitor_name']) ?></td>
                                <td><?= ucfirst(htmlspecialchars($competitor['platform'])) ?></td>
                                <td><?= number_format($competitor['follower_count']) ?></td>
                                <td><?= number_format($competitor['avg_engagement_rate'], 2) ?>%</td>
                                <td><?= number_format($competitor['avg_posts_per_week'], 1) ?></td>
                                <td>
                                    <?php if ($competitor['follower_growth']): ?>
                                        <span class="<?= $competitor['follower_growth'] > 0 ? 'growth-positive' : 'growth-negative' ?>">
                                            <?= $competitor['follower_growth'] > 0 ? '+' : '' ?><?= number_format($competitor['follower_growth']) ?>
                                        </span>
                                    <?php else: ?>
                                        <span style="color: #666;">N/A</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>

        <!-- Recommendations -->
        <?php if (!empty($data['recommendations'])): ?>
            <div class="recommendations-section" id="recommendations">
                <h2 class="recommendations-title">Strategic Recommendations</h2>
                <div class="recommendations-grid">
                    <?php foreach ($data['recommendations'] as $rec): ?>
                        <div class="recommendation-card">
                            <div class="recommendation-priority"><?= htmlspecialchars($rec['priority']) ?> Priority</div>
                            <div class="recommendation-title"><?= htmlspecialchars($rec['title']) ?></div>
                            <div class="recommendation-description"><?= htmlspecialchars($rec['description']) ?></div>
                            <div class="recommendation-action">
                                <strong>Action:</strong> <?= htmlspecialchars($rec['action']) ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
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
            <div class="footer-note">
                This report was generated on <?= date('F j, Y \a\t g:i A') ?> and contains data from <?= htmlspecialchars($data['date_range']['formatted']) ?>.<br>
                All metrics are calculated based on available platform data and may be subject to platform API limitations.
            </div>
        </div>
    </div>
</body>
</html>