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
        
        @page {
            margin: 20mm;
            size: A4;
            
            @top-left {
                content: "<?= htmlspecialchars($branding['business_name'] ?? 'Social Media Report') ?>";
                font-size: 10px;
                color: #666;
            }
            
            @top-right {
                content: "<?= date('F j, Y') ?>";
                font-size: 10px;
                color: #666;
            }
            
            @bottom-center {
                content: "Page " counter(page) " of " counter(pages);
                font-size: 10px;
                color: #666;
            }
        }
        
        body {
            font-family: 'Helvetica', 'Arial', sans-serif;
            line-height: 1.5;
            color: #333;
            background: #fff;
            font-size: 11px;
        }
        
        .pdf-container {
            width: 100%;
            max-width: none;
            margin: 0;
            padding: 0;
        }
        
        .pdf-header {
            text-align: center;
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 3px solid <?= $branding['primary_color'] ?? '#8B5CF6' ?>;
            page-break-after: avoid;
        }
        
        .pdf-logo {
            max-height: 60px;
            max-width: 200px;
            margin-bottom: 15px;
            display: block;
            margin-left: auto;
            margin-right: auto;
        }
        
        .pdf-report-title {
            font-size: 24px;
            font-weight: bold;
            color: <?= $branding['primary_color'] ?? '#8B5CF6' ?>;
            margin-bottom: 8px;
        }
        
        .pdf-client-name {
            font-size: 18px;
            color: #555;
            margin-bottom: 5px;
        }
        
        .pdf-date-range {
            font-size: 14px;
            color: #777;
        }
        
        .pdf-summary-grid {
            display: table;
            width: 100%;
            margin: 25px 0;
            page-break-inside: avoid;
        }
        
        .pdf-summary-row {
            display: table-row;
        }
        
        .pdf-metric-card {
            display: table-cell;
            width: 25%;
            background: #f8f9fa;
            border-left: 4px solid <?= $branding['primary_color'] ?? '#8B5CF6' ?>;
            padding: 15px;
            margin-right: 10px;
            text-align: center;
            vertical-align: top;
            border-radius: 6px;
        }
        
        .pdf-metric-value {
            font-size: 20px;
            font-weight: bold;
            color: <?= $branding['primary_color'] ?? '#8B5CF6' ?>;
            margin-bottom: 5px;
            display: block;
        }
        
        .pdf-metric-label {
            font-size: 10px;
            color: #666;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 3px;
        }
        
        .pdf-metric-growth {
            font-size: 9px;
            margin-top: 3px;
        }
        
        .pdf-growth-positive {
            color: #10b981;
        }
        
        .pdf-growth-negative {
            color: #ef4444;
        }
        
        .pdf-section {
            margin: 25px 0;
            page-break-inside: avoid;
        }
        
        .pdf-section-title {
            font-size: 16px;
            font-weight: bold;
            color: <?= $branding['primary_color'] ?? '#8B5CF6' ?>;
            margin-bottom: 15px;
            padding-bottom: 8px;
            border-bottom: 2px solid #eee;
            page-break-after: avoid;
        }
        
        .pdf-platform-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
            page-break-inside: auto;
        }
        
        .pdf-platform-table th {
            background-color: <?= $branding['primary_color'] ?? '#8B5CF6' ?>;
            color: white;
            padding: 10px 8px;
            text-align: left;
            font-size: 10px;
            font-weight: bold;
            text-transform: uppercase;
        }
        
        .pdf-platform-table td {
            padding: 8px;
            border-bottom: 1px solid #ddd;
            font-size: 10px;
            page-break-inside: avoid;
        }
        
        .pdf-platform-table tbody tr:nth-child(even) {
            background-color: #f9f9f9;
        }
        
        .pdf-platform-name {
            font-weight: bold;
            color: <?= $branding['primary_color'] ?? '#8B5CF6' ?>;
            text-transform: capitalize;
        }
        
        .pdf-top-posts {
            margin: 15px 0;
        }
        
        .pdf-post-item {
            background: #f8f9fa;
            border-radius: 6px;
            padding: 12px;
            margin-bottom: 10px;
            border-left: 4px solid <?= $branding['accent_color'] ?? '#10b981' ?>;
            page-break-inside: avoid;
        }
        
        .pdf-post-content {
            font-size: 10px;
            color: #333;
            margin-bottom: 8px;
            line-height: 1.4;
            max-height: 40px;
            overflow: hidden;
        }
        
        .pdf-post-metrics {
            display: table;
            width: 100%;
            font-size: 9px;
            color: #666;
        }
        
        .pdf-post-metrics > span {
            display: table-cell;
            padding-right: 15px;
        }
        
        .pdf-recommendations {
            background: linear-gradient(135deg, <?= $branding['primary_color'] ?? '#8B5CF6' ?>, <?= $branding['secondary_color'] ?? '#06b6d4' ?>);
            color: white;
            padding: 20px;
            border-radius: 8px;
            margin: 25px 0;
            page-break-inside: avoid;
        }
        
        .pdf-recommendations-title {
            font-size: 18px;
            font-weight: bold;
            margin-bottom: 15px;
            text-align: center;
        }
        
        .pdf-recommendation-item {
            background: rgba(255,255,255,0.15);
            border-radius: 6px;
            padding: 12px;
            margin-bottom: 10px;
            page-break-inside: avoid;
        }
        
        .pdf-recommendation-title {
            font-weight: bold;
            margin-bottom: 4px;
            font-size: 11px;
        }
        
        .pdf-recommendation-description {
            font-size: 10px;
            opacity: 0.9;
            line-height: 1.4;
        }
        
        .pdf-footer {
            text-align: center;
            margin-top: 40px;
            padding-top: 20px;
            border-top: 2px solid #eee;
            color: #666;
            font-size: 10px;
            page-break-inside: avoid;
        }
        
        .pdf-footer-branding {
            margin-bottom: 10px;
            font-weight: bold;
        }
        
        .pdf-footer-contact {
            font-size: 9px;
            line-height: 1.3;
        }
        
        .pdf-page-break {
            page-break-before: always;
        }
        
        .pdf-avoid-break {
            page-break-inside: avoid;
        }
        
        /* Chart placeholder styling */
        .pdf-chart-placeholder {
            background: #f8f9fa;
            border: 2px dashed #ddd;
            padding: 30px;
            text-align: center;
            margin: 15px 0;
            border-radius: 8px;
            page-break-inside: avoid;
        }
        
        .pdf-chart-placeholder h4 {
            color: #666;
            margin-bottom: 8px;
            font-size: 12px;
        }
        
        .pdf-chart-placeholder p {
            color: #888;
            font-size: 10px;
            margin: 0;
        }
        
        /* Performance optimization for PDF */
        img {
            max-width: 100%;
            height: auto;
        }
        
        table {
            border-collapse: collapse;
        }
        
        /* Print media queries */
        @media print {
            .no-print {
                display: none !important;
            }
            
            body {
                font-size: 10px;
            }
            
            .pdf-summary-grid {
                display: table;
            }
            
            .pdf-metric-card {
                display: table-cell;
            }
        }
    </style>
</head>
<body>
    <div class="pdf-container">
        <!-- Header -->
        <div class="pdf-header pdf-avoid-break">
            <?php if (!empty($branding['logo_url'])): ?>
                <img src="<?= htmlspecialchars($branding['logo_url']) ?>" alt="Logo" class="pdf-logo">
            <?php endif; ?>
            
            <h1 class="pdf-report-title">Executive Summary</h1>
            <div class="pdf-client-name"><?= htmlspecialchars($data['client_info']['business_name'] ?: $data['client_info']['name']) ?></div>
            <div class="pdf-date-range"><?= htmlspecialchars($data['date_range']['formatted']) ?></div>
        </div>

        <!-- Key Metrics Summary -->
        <div class="pdf-summary-grid pdf-avoid-break">
            <div class="pdf-summary-row">
                <div class="pdf-metric-card">
                    <span class="pdf-metric-value"><?= number_format($data['overview']['total_posts']) ?></span>
                    <div class="pdf-metric-label">Total Posts</div>
                    <?php if ($data['overview']['total_posts_growth'] != 0): ?>
                        <div class="pdf-metric-growth <?= $data['overview']['total_posts_growth'] > 0 ? 'pdf-growth-positive' : 'pdf-growth-negative' ?>">
                            <?= $data['overview']['total_posts_growth'] > 0 ? '+' : '' ?><?= number_format($data['overview']['total_posts_growth'], 1) ?>%
                        </div>
                    <?php endif; ?>
                </div>
                
                <div class="pdf-metric-card">
                    <span class="pdf-metric-value"><?= number_format($data['overview']['total_reach']) ?></span>
                    <div class="pdf-metric-label">Total Reach</div>
                    <?php if ($data['overview']['total_reach_growth'] != 0): ?>
                        <div class="pdf-metric-growth <?= $data['overview']['total_reach_growth'] > 0 ? 'pdf-growth-positive' : 'pdf-growth-negative' ?>">
                            <?= $data['overview']['total_reach_growth'] > 0 ? '+' : '' ?><?= number_format($data['overview']['total_reach_growth'], 1) ?>%
                        </div>
                    <?php endif; ?>
                </div>
                
                <div class="pdf-metric-card">
                    <span class="pdf-metric-value"><?= number_format($data['overview']['avg_engagement_rate'], 2) ?>%</span>
                    <div class="pdf-metric-label">Avg Engagement</div>
                    <?php if ($data['overview']['avg_engagement_rate_growth'] != 0): ?>
                        <div class="pdf-metric-growth <?= $data['overview']['avg_engagement_rate_growth'] > 0 ? 'pdf-growth-positive' : 'pdf-growth-negative' ?>">
                            <?= $data['overview']['avg_engagement_rate_growth'] > 0 ? '+' : '' ?><?= number_format($data['overview']['avg_engagement_rate_growth'], 1) ?>%
                        </div>
                    <?php endif; ?>
                </div>
                
                <div class="pdf-metric-card">
                    <span class="pdf-metric-value"><?= number_format($data['overview']['total_engagement']) ?></span>
                    <div class="pdf-metric-label">Total Engagement</div>
                    <?php if ($data['overview']['total_engagement_growth'] != 0): ?>
                        <div class="pdf-metric-growth <?= $data['overview']['total_engagement_growth'] > 0 ? 'pdf-growth-positive' : 'pdf-growth-negative' ?>">
                            <?= $data['overview']['total_engagement_growth'] > 0 ? '+' : '' ?><?= number_format($data['overview']['total_engagement_growth'], 1) ?>%
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Platform Performance -->
        <div class="pdf-section">
            <h2 class="pdf-section-title">Platform Performance</h2>
            
            <?php if (!empty($data['platform_performance'])): ?>
                <table class="pdf-platform-table">
                    <thead>
                        <tr>
                            <th>Platform</th>
                            <th>Posts</th>
                            <th>Engagement Rate</th>
                            <th>Reach</th>
                            <th>Followers</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($data['platform_performance'] as $platform): ?>
                            <?php if ($platform['post_count'] > 0): ?>
                                <tr>
                                    <td class="pdf-platform-name"><?= htmlspecialchars($platform['platform']) ?></td>
                                    <td><?= number_format($platform['post_count']) ?></td>
                                    <td><?= number_format($platform['avg_engagement_rate'], 2) ?>%</td>
                                    <td><?= number_format($platform['total_reach']) ?></td>
                                    <td><?= number_format($platform['current_followers']) ?></td>
                                </tr>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <p>No platform performance data available for this period.</p>
            <?php endif; ?>
        </div>

        <!-- Top Performing Posts -->
        <?php if (!empty($data['top_posts'])): ?>
            <div class="pdf-section">
                <h2 class="pdf-section-title">Top Performing Posts</h2>
                <div class="pdf-top-posts">
                    <?php foreach (array_slice($data['top_posts'], 0, 5) as $index => $post): ?>
                        <div class="pdf-post-item">
                            <div class="pdf-post-content"><?= htmlspecialchars(substr($post['content'], 0, 150)) ?><?= strlen($post['content']) > 150 ? '...' : '' ?></div>
                            <div class="pdf-post-metrics">
                                <span><strong>Platform:</strong> <?= ucfirst($post['platform']) ?></span>
                                <span><strong>Engagement:</strong> <?= number_format($post['engagement_rate'], 2) ?>%</span>
                                <span><strong>Interactions:</strong> <?= number_format($post['total_engagement']) ?></span>
                                <span><strong>Date:</strong> <?= date('M j, Y', strtotime($post['published_at'])) ?></span>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>

        <!-- Performance Charts Placeholder -->
        <div class="pdf-section">
            <h2 class="pdf-section-title">Performance Trends</h2>
            <div class="pdf-chart-placeholder">
                <h4>Engagement Trends Chart</h4>
                <p>Interactive charts are not available in PDF format</p>
                <p>View the online report for detailed trend visualizations</p>
            </div>
        </div>

        <!-- New page for recommendations -->
        <div class="pdf-page-break"></div>

        <!-- Recommendations -->
        <?php if (!empty($data['recommendations'])): ?>
            <div class="pdf-recommendations">
                <h2 class="pdf-recommendations-title">Key Recommendations</h2>
                <?php foreach (array_slice($data['recommendations'], 0, 4) as $rec): ?>
                    <div class="pdf-recommendation-item">
                        <div class="pdf-recommendation-title"><?= htmlspecialchars($rec['title']) ?></div>
                        <div class="pdf-recommendation-description"><?= htmlspecialchars($rec['description']) ?></div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
        
        <!-- Summary Insights -->
        <div class="pdf-section">
            <h2 class="pdf-section-title">Summary Insights</h2>
            <div style="background: #f8f9fa; padding: 15px; border-radius: 6px; border-left: 4px solid <?= $branding['primary_color'] ?? '#8B5CF6' ?>;">
                <ul style="margin: 0; padding-left: 20px; line-height: 1.6;">
                    <?php if ($data['overview']['avg_engagement_rate'] > 3): ?>
                        <li style="margin-bottom: 8px;">Strong engagement rate of <?= number_format($data['overview']['avg_engagement_rate'], 2) ?>% indicates high audience connection</li>
                    <?php elseif ($data['overview']['avg_engagement_rate'] > 1): ?>
                        <li style="margin-bottom: 8px;">Moderate engagement rate of <?= number_format($data['overview']['avg_engagement_rate'], 2) ?>% shows room for improvement</li>
                    <?php else: ?>
                        <li style="margin-bottom: 8px;">Low engagement rate of <?= number_format($data['overview']['avg_engagement_rate'], 2) ?>% requires immediate attention</li>
                    <?php endif; ?>
                    
                    <?php if ($data['overview']['total_posts'] > 0): ?>
                        <li style="margin-bottom: 8px;">Published <?= number_format($data['overview']['total_posts']) ?> posts during this period</li>
                    <?php endif; ?>
                    
                    <?php if ($data['overview']['total_reach'] > 10000): ?>
                        <li style="margin-bottom: 8px;">Achieved significant reach of <?= number_format($data['overview']['total_reach']) ?> people</li>
                    <?php endif; ?>
                    
                    <?php if (!empty($data['platform_performance'])): ?>
                        <?php 
                        $bestPlatform = null;
                        $bestEngagement = 0;
                        foreach ($data['platform_performance'] as $platform) {
                            if ($platform['post_count'] > 0 && $platform['avg_engagement_rate'] > $bestEngagement) {
                                $bestEngagement = $platform['avg_engagement_rate'];
                                $bestPlatform = $platform['platform'];
                            }
                        }
                        ?>
                        <?php if ($bestPlatform): ?>
                            <li style="margin-bottom: 8px;"><?= ucfirst($bestPlatform) ?> shows the highest engagement rate at <?= number_format($bestEngagement, 2) ?>%</li>
                        <?php endif; ?>
                    <?php endif; ?>
                </ul>
            </div>
        </div>

        <!-- Footer -->
        <div class="pdf-footer">
            <div class="pdf-footer-branding">
                <?php if (!empty($branding['business_name'])): ?>
                    <strong><?= htmlspecialchars($branding['business_name']) ?></strong>
                <?php endif; ?>
            </div>
            <div class="pdf-footer-contact">
                <?php if (!empty($branding['contact_email'])): ?>
                    Email: <?= htmlspecialchars($branding['contact_email']) ?>
                <?php endif; ?>
                <?php if (!empty($branding['phone_number'])): ?>
                    <?= !empty($branding['contact_email']) ? ' | ' : '' ?>Phone: <?= htmlspecialchars($branding['phone_number']) ?>
                <?php endif; ?>
                <?php if (!empty($branding['website_url'])): ?>
                    <?= (!empty($branding['contact_email']) || !empty($branding['phone_number'])) ? ' | ' : '' ?>Website: <?= htmlspecialchars($branding['website_url']) ?>
                <?php endif; ?>
            </div>
            <div style="margin-top: 15px; font-size: 9px; color: #999;">
                Report generated on <?= date('F j, Y \a\t g:i A') ?> | Powered by GHST Social Media Management
            </div>
        </div>
    </div>
</body>
</html>