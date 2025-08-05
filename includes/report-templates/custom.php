<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($data['client_info']['business_name'] ?: $data['client_info']['name']) ?> - Custom Report</title>
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
        
        .section {
            margin: 50px 0;
        }
        
        .section-title {
            font-size: 22px;
            font-weight: bold;
            color: <?= $branding['primary_color'] ?? '#8B5CF6' ?>;
            margin-bottom: 25px;
            padding-bottom: 10px;
            border-bottom: 2px solid #eee;
        }
        
        .metrics-container {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin: 30px 0;
        }
        
        .metric-card {
            background: #fff;
            border: 1px solid #e5e7eb;
            border-left: 4px solid <?= $branding['primary_color'] ?? '#8B5CF6' ?>;
            border-radius: 8px;
            padding: 25px;
            text-align: center;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        
        .metric-value {
            font-size: 32px;
            font-weight: bold;
            color: <?= $branding['primary_color'] ?? '#8B5CF6' ?>;
            margin-bottom: 8px;
        }
        
        .metric-label {
            font-size: 14px;
            color: #666;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .custom-section {
            background: #f8f9fa;
            border-radius: 12px;
            padding: 30px;
            margin: 40px 0;
        }
        
        .custom-section-title {
            font-size: 20px;
            font-weight: bold;
            color: <?= $branding['primary_color'] ?? '#8B5CF6' ?>;
            margin-bottom: 20px;
        }
        
        .data-table {
            width: 100%;
            border-collapse: collapse;
            margin: 20px 0;
            background: #fff;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        
        .data-table th {
            background: <?= $branding['primary_color'] ?? '#8B5CF6' ?>;
            color: white;
            padding: 15px;
            text-align: left;
            font-weight: bold;
        }
        
        .data-table td {
            padding: 12px 15px;
            border-bottom: 1px solid #eee;
        }
        
        .data-table tr:hover {
            background: #f8f9fa;
        }
        
        .chart-container {
            background: #fff;
            border: 1px solid #e5e7eb;
            border-radius: 12px;
            padding: 30px;
            margin: 30px 0;
            text-align: center;
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
        }
        
        .insight-box {
            background: linear-gradient(135deg, <?= $branding['primary_color'] ?? '#8B5CF6' ?>, <?= $branding['secondary_color'] ?? '#06b6d4' ?>);
            color: white;
            padding: 30px;
            border-radius: 12px;
            margin: 30px 0;
        }
        
        .insight-title {
            font-size: 20px;
            font-weight: bold;
            margin-bottom: 15px;
        }
        
        .insight-content {
            font-size: 14px;
            line-height: 1.6;
            opacity: 0.95;
        }
        
        .highlight-stat {
            background: <?= $branding['accent_color'] ?? '#10b981' ?>;
            color: white;
            padding: 20px;
            border-radius: 8px;
            text-align: center;
            margin: 20px 0;
        }
        
        .highlight-value {
            font-size: 36px;
            font-weight: bold;
            margin-bottom: 5px;
        }
        
        .highlight-description {
            font-size: 14px;
            opacity: 0.9;
        }
        
        .comparison-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 30px;
            margin: 30px 0;
        }
        
        .comparison-card {
            background: #fff;
            border: 1px solid #e5e7eb;
            border-radius: 8px;
            padding: 25px;
        }
        
        .comparison-title {
            font-size: 16px;
            font-weight: bold;
            color: <?= $branding['primary_color'] ?? '#8B5CF6' ?>;
            margin-bottom: 15px;
        }
        
        .comparison-metrics {
            display: grid;
            gap: 10px;
        }
        
        .comparison-metric {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 8px 0;
            border-bottom: 1px solid #f3f4f6;
        }
        
        .comparison-metric:last-child {
            border-bottom: none;
        }
        
        .metric-name {
            font-size: 14px;
            color: #666;
        }
        
        .metric-value-compare {
            font-weight: bold;
            color: #333;
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
            
            .comparison-grid {
                grid-template-columns: 1fr;
                gap: 20px;
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
            
            <h1 class="report-title">Custom Analytics Report</h1>
            <div class="client-name"><?= htmlspecialchars($data['client_info']['business_name'] ?: $data['client_info']['name']) ?></div>
            <div class="date-range"><?= htmlspecialchars($data['date_range']['formatted']) ?></div>
        </div>

        <!-- Overview Metrics -->
        <div class="section">
            <h2 class="section-title">Key Performance Indicators</h2>
            <div class="metrics-container">
                <div class="metric-card">
                    <div class="metric-value"><?= number_format($data['overview']['total_posts']) ?></div>
                    <div class="metric-label">Posts Published</div>
                </div>
                <div class="metric-card">
                    <div class="metric-value"><?= number_format($data['overview']['total_reach']) ?></div>
                    <div class="metric-label">Total Reach</div>
                </div>
                <div class="metric-card">
                    <div class="metric-value"><?= number_format($data['overview']['avg_engagement_rate'], 2) ?>%</div>
                    <div class="metric-label">Engagement Rate</div>
                </div>
                <div class="metric-card">
                    <div class="metric-value"><?= number_format($data['overview']['total_engagement']) ?></div>
                    <div class="metric-label">Total Engagement</div>
                </div>
            </div>

            <!-- Highlighted Performance Metric -->
            <?php 
            $bestMetric = 'engagement_rate';
            $bestValue = $data['overview']['avg_engagement_rate'];
            $bestLabel = 'Average Engagement Rate';
            
            if ($data['overview']['total_reach'] > 10000) {
                $bestMetric = 'reach';
                $bestValue = number_format($data['overview']['total_reach']);
                $bestLabel = 'Impressive Total Reach';
            }
            ?>
            <div class="highlight-stat">
                <div class="highlight-value"><?= $bestValue ?><?= $bestMetric === 'engagement_rate' ? '%' : '' ?></div>
                <div class="highlight-description"><?= $bestLabel ?> - A standout metric for this period</div>
            </div>
        </div>

        <!-- Platform Comparison -->
        <div class="section">
            <h2 class="section-title">Platform Performance Comparison</h2>
            <div class="comparison-grid">
                <?php foreach ($data['platform_performance'] as $platform): ?>
                    <?php if ($platform['post_count'] > 0): ?>
                        <div class="comparison-card">
                            <div class="comparison-title"><?= ucfirst(htmlspecialchars($platform['platform'])) ?></div>
                            <div class="comparison-metrics">
                                <div class="comparison-metric">
                                    <span class="metric-name">Posts</span>
                                    <span class="metric-value-compare"><?= number_format($platform['post_count']) ?></span>
                                </div>
                                <div class="comparison-metric">
                                    <span class="metric-name">Engagement Rate</span>
                                    <span class="metric-value-compare"><?= number_format($platform['avg_engagement_rate'], 2) ?>%</span>
                                </div>
                                <div class="comparison-metric">
                                    <span class="metric-name">Total Reach</span>
                                    <span class="metric-value-compare"><?= number_format($platform['total_reach']) ?></span>
                                </div>
                                <div class="comparison-metric">
                                    <span class="metric-name">Followers</span>
                                    <span class="metric-value-compare"><?= number_format($platform['current_followers']) ?></span>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Top Content Performance -->
        <?php if (!empty($data['top_posts'])): ?>
            <div class="section">
                <h2 class="section-title">Top Performing Content</h2>
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Content Preview</th>
                            <th>Platform</th>
                            <th>Engagement Rate</th>
                            <th>Reach</th>
                            <th>Total Interactions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach (array_slice($data['top_posts'], 0, 10) as $post): ?>
                            <tr>
                                <td style="max-width: 300px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">
                                    <?= htmlspecialchars(substr($post['content'], 0, 100)) ?><?= strlen($post['content']) > 100 ? '...' : '' ?>
                                </td>
                                <td><?= ucfirst(htmlspecialchars($post['platform'])) ?></td>
                                <td><strong><?= number_format($post['engagement_rate'], 2) ?>%</strong></td>
                                <td><?= number_format($post['reach']) ?></td>
                                <td><?= number_format($post['total_engagement']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>

        <!-- Custom Analytics Sections -->
        
        <!-- Engagement Trends -->
        <div class="custom-section">
            <h3 class="custom-section-title">Engagement Trend Analysis</h3>
            <div class="chart-container">
                <div class="chart-placeholder">
                    Engagement Rate Trend Over Time - Chart would be rendered here with actual data visualization library
                </div>
            </div>
            <?php if (!empty($data['engagement_trends'])): ?>
                <p style="color: #666; font-size: 14px; text-align: center; margin-top: 15px;">
                    Based on <?= count($data['engagement_trends']) ?> data points showing daily engagement patterns
                </p>
            <?php endif; ?>
        </div>

        <!-- Content Type Performance -->
        <?php if (!empty($data['content_analysis'])): ?>
            <div class="custom-section">
                <h3 class="custom-section-title">Content Type Effectiveness</h3>
                <div class="metrics-container">
                    <?php foreach ($data['content_analysis'] as $content): ?>
                        <div class="metric-card">
                            <div class="metric-value"><?= number_format($content['avg_engagement_rate'], 1) ?>%</div>
                            <div class="metric-label"><?= ucfirst(htmlspecialchars($content['content_type'])) ?></div>
                            <div style="font-size: 12px; color: #888; margin-top: 5px;">
                                <?= number_format($content['post_count']) ?> posts
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>

        <!-- Hashtag Analysis -->
        <?php if (!empty($data['hashtag_performance'])): ?>
            <div class="custom-section">
                <h3 class="custom-section-title">Top Performing Hashtags</h3>
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Hashtag</th>
                            <th>Platform</th>
                            <th>Usage Count</th>
                            <th>Avg Engagement Rate</th>
                            <th>Total Reach</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach (array_slice($data['hashtag_performance'], 0, 10) as $hashtag): ?>
                            <tr>
                                <td><strong>#<?= htmlspecialchars($hashtag['hashtag']) ?></strong></td>
                                <td><?= ucfirst(htmlspecialchars($hashtag['platform'])) ?></td>
                                <td><?= number_format($hashtag['usage_count']) ?></td>
                                <td><?= number_format($hashtag['avg_engagement_rate'], 2) ?>%</td>
                                <td><?= number_format($hashtag['total_reach']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>

        <!-- Key Insights -->
        <div class="insight-box">
            <div class="insight-title">Custom Report Insights</div>
            <div class="insight-content">
                This custom report has been tailored to highlight the most relevant metrics for your social media strategy. 
                <?php if (!empty($data['top_posts'])): ?>
                    Your top-performing content shows that <?= strtolower($data['top_posts'][0]['platform']) ?> engagement 
                    is particularly strong with <?= number_format($data['top_posts'][0]['engagement_rate'], 2) ?>% engagement rate.
                <?php endif; ?>
                
                <?php if (!empty($data['content_analysis'])): ?>
                    Among content types, <?= $data['content_analysis'][0]['content_type'] ?> content performs best, 
                    suggesting this format resonates well with your audience.
                <?php endif; ?>
                
                Continue monitoring these metrics to optimize your social media strategy and maximize engagement.
            </div>
        </div>

        <!-- Performance Summary -->
        <div class="section">
            <h2 class="section-title">Period Summary</h2>
            <div class="custom-section">
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px;">
                    <div style="text-align: center; padding: 20px;">
                        <div style="font-size: 24px; font-weight: bold; color: <?= $branding['primary_color'] ?? '#8B5CF6' ?>;">
                            <?= number_format(($data['overview']['total_reach'] / max($data['overview']['total_posts'], 1))) ?>
                        </div>
                        <div style="font-size: 14px; color: #666;">Average Reach per Post</div>
                    </div>
                    <div style="text-align: center; padding: 20px;">
                        <div style="font-size: 24px; font-weight: bold; color: <?= $branding['primary_color'] ?? '#8B5CF6' ?>;">
                            <?= number_format(($data['overview']['total_engagement'] / max($data['overview']['total_posts'], 1))) ?>
                        </div>
                        <div style="font-size: 14px; color: #666;">Average Engagement per Post</div>
                    </div>
                    <div style="text-align: center; padding: 20px;">
                        <div style="font-size: 24px; font-weight: bold; color: <?= $branding['primary_color'] ?? '#8B5CF6' ?>;">
                            <?php
                            $activePlatforms = count(array_filter($data['platform_performance'], function($p) { return $p['post_count'] > 0; }));
                            echo $activePlatforms;
                            ?>
                        </div>
                        <div style="font-size: 14px; color: #666;">Active Platforms</div>
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
                Custom analytics report generated on <?= date('F j, Y \a\t g:i A') ?>.<br>
                Report period: <?= htmlspecialchars($data['date_range']['formatted']) ?> | 
                Data points analyzed: <?= count($data['top_posts']) + count($data['platform_performance']) + count($data['engagement_trends']) ?>
            </div>
        </div>
    </div>
</body>
</html>