<?php
/**
 * Campaign Strategy Engine
 * 
 * Generates intelligent 12-week campaign strategies using AI and analytics data
 * Extends AIContentSuggestions for AI capabilities and integrates with analytics
 * 
 * Features:
 * - Full 12-week campaign generation using Claude/ChatGPT
 * - Analytics-driven content recommendations
 * - Week-by-week strategy plans with platform-specific content
 * - Single week regeneration without affecting other weeks
 * - Performance-based strategy evolution
 * - Structured data export for storage and sharing
 * 
 * @package GHST_WRTR
 * @version 1.0.0
 * @author Tony Shaw Jr.
 */

require_once __DIR__ . '/AIContentSuggestions.php';
require_once __DIR__ . '/Database.php';

class CampaignStrategyEngine extends AIContentSuggestions {
    
    private $campaignId;
    private $analyticsData;
    private $wizardData;
    
    // Strategy generation constants
    const DEFAULT_WEEKS = 12;
    const MAX_WEEKS = 24;
    const MIN_POSTS_PER_WEEK = 3;
    const MAX_POSTS_PER_WEEK = 7;
    
    // Content performance thresholds
    const HIGH_PERFORMANCE_THRESHOLD = 3.5; // Engagement rate %
    const LOW_PERFORMANCE_THRESHOLD = 1.0;   // Engagement rate %
    
    // AI model settings for strategy generation
    const STRATEGY_MAX_TOKENS = 4000;
    const STRATEGY_TEMPERATURE = 0.75;
    const WEEK_MAX_TOKENS = 2000;
    const WEEK_TEMPERATURE = 0.70;
    
    public function __construct($clientId, $campaignId = null) {
        parent::__construct($clientId);
        $this->campaignId = $campaignId;
        
        if ($campaignId) {
            $this->loadCampaignData();
        }
    }
    
    /**
     * Generate a complete 12-week campaign strategy
     * 
     * @param array $params Campaign parameters from wizard
     * @param array $analyticsJson Optional analytics data for insights
     * @return array Generated strategy with weeks and posts
     * @throws Exception On generation failure
     */
    public function generateFullStrategy(array $params, array $analyticsJson = null) {
        try {
            $this->db->beginTransaction();
            
            // Store wizard data
            $campaignId = $this->storeCampaignWizardData($params);
            $this->campaignId = $campaignId;
            
            // Parse analytics if provided
            if ($analyticsJson) {
                $this->analyticsData = $this->parseAnalytics($analyticsJson);
            }
            
            // Generate master campaign prompt
            $prompt = $this->buildFullCampaignPrompt($params, $this->analyticsData);
            
            // Call AI with extended parameters for strategy generation
            $aiParams = array_merge($params, [
                'max_tokens' => self::STRATEGY_MAX_TOKENS,
                'temperature' => self::STRATEGY_TEMPERATURE,
                'model' => $params['ai_model'] ?? null
            ]);
            
            $this->logActivity('Generating full campaign strategy', [
                'campaign_id' => $campaignId,
                'weeks' => $params['total_weeks'] ?? self::DEFAULT_WEEKS,
                'ai_model' => $aiParams['model'] ?? 'default'
            ]);
            
            // Generate strategy using parent AI capabilities
            $aiResponse = $this->generateSuggestions($aiParams, $prompt);
            
            // Parse and structure the AI response
            $strategy = $this->parseStrategyResponse($aiResponse, $params);
            
            // Store structured strategy in database
            $this->storeFullStrategy($campaignId, $strategy);
            
            // Update campaign status
            $this->updateCampaignStatus($campaignId, 'active');
            
            $this->db->commit();
            
            $this->logActivity('Campaign strategy generated successfully', [
                'campaign_id' => $campaignId,
                'weeks_generated' => count($strategy['weeks']),
                'total_posts' => array_sum(array_map(fn($week) => count($week['posts']), $strategy['weeks']))
            ]);
            
            return [
                'success' => true,
                'campaign_id' => $campaignId,
                'strategy' => $strategy,
                'weeks_generated' => count($strategy['weeks']),
                'total_posts' => array_sum(array_map(fn($week) => count($week['posts']), $strategy['weeks']))
            ];
            
        } catch (Exception $e) {
            $this->db->rollBack();
            $this->logError('Campaign strategy generation failed', $e, [
                'campaign_id' => $campaignId ?? null,
                'params' => $params
            ]);
            throw $e;
        }
    }
    
    /**
     * Regenerate a single week without affecting others
     * 
     * @param int $weekNumber Week to regenerate (1-12)
     * @param array $options Regeneration options and feedback
     * @return array Regenerated week data
     */
    public function regenerateWeek(int $weekNumber, array $options = []) {
        if (!$this->campaignId) {
            throw new Exception("Campaign ID required for week regeneration");
        }
        
        try {
            $this->db->beginTransaction();
            
            // Get current week data and campaign context
            $currentWeek = $this->getWeekData($weekNumber);
            $campaignContext = $this->getCampaignContext();
            $adjacentWeeks = $this->getAdjacentWeeksContext($weekNumber);
            
            // Build regeneration prompt with context
            $prompt = $this->buildWeekRegenerationPrompt(
                $weekNumber, 
                $currentWeek, 
                $campaignContext, 
                $adjacentWeeks, 
                $options
            );
            
            // Set AI parameters for week generation
            $aiParams = [
                'max_tokens' => self::WEEK_MAX_TOKENS,
                'temperature' => self::WEEK_TEMPERATURE,
                'provider' => $options['ai_model'] ?? null
            ];
            
            $this->logActivity('Regenerating campaign week', [
                'campaign_id' => $this->campaignId,
                'week_number' => $weekNumber,
                'reason' => $options['regeneration_reason'] ?? 'user_request',
                'feedback' => $options['user_feedback'] ?? null
            ]);
            
            // Generate new week content
            $aiResponse = $this->generateSuggestions($aiParams, $prompt);
            $newWeekData = $this->parseWeekResponse($aiResponse, $weekNumber, $campaignContext);
            
            // Store regeneration history before applying changes
            $this->storeRegenerationHistory($weekNumber, $currentWeek, $newWeekData, $options);
            
            // Update week data
            $this->updateWeekData($weekNumber, $newWeekData);
            
            $this->db->commit();
            
            $this->logActivity('Week regenerated successfully', [
                'campaign_id' => $this->campaignId,
                'week_number' => $weekNumber,
                'posts_generated' => count($newWeekData['posts'])
            ]);
            
            return [
                'success' => true,
                'week_number' => $weekNumber,
                'week_data' => $newWeekData,
                'posts_count' => count($newWeekData['posts'])
            ];
            
        } catch (Exception $e) {
            $this->db->rollBack();
            $this->logError('Week regeneration failed', $e, [
                'campaign_id' => $this->campaignId,
                'week_number' => $weekNumber
            ]);
            throw $e;
        }
    }
    
    /**
     * Parse analytics JSON and extract actionable insights
     * 
     * @param array $analyticsJson Raw analytics data
     * @return array Structured insights for AI prompting
     */
    public function parseAnalytics(array $analyticsJson): array {
        $insights = [
            'high_performing_content' => [],
            'low_performing_content' => [],
            'optimal_posting_times' => [],
            'top_hashtags' => [],
            'audience_preferences' => [],
            'engagement_patterns' => [],
            'content_type_performance' => [],
            'platform_specific_insights' => []
        ];
        
        try {
            // Extract post performance data
            if (isset($analyticsJson['posts']) && is_array($analyticsJson['posts'])) {
                foreach ($analyticsJson['posts'] as $post) {
                    $engagementRate = $this->calculateEngagementRate($post);
                    
                    $postData = [
                        'content' => $post['content'] ?? '',
                        'platform' => $post['platform'] ?? 'unknown',
                        'engagement_rate' => $engagementRate,
                        'total_engagement' => $post['total_engagement'] ?? 0,
                        'post_type' => $post['post_type'] ?? 'unknown',
                        'posted_at' => $post['posted_at'] ?? null,
                        'hashtags' => $post['hashtags'] ?? []
                    ];
                    
                    // Categorize by performance
                    if ($engagementRate >= self::HIGH_PERFORMANCE_THRESHOLD) {
                        $insights['high_performing_content'][] = $postData;
                    } elseif ($engagementRate <= self::LOW_PERFORMANCE_THRESHOLD) {
                        $insights['low_performing_content'][] = $postData;
                    }
                }
            }
            
            // Extract posting time patterns
            $insights['optimal_posting_times'] = $this->extractPostingTimePatterns($analyticsJson);
            
            // Extract hashtag performance
            $insights['top_hashtags'] = $this->extractHashtagPerformance($analyticsJson);
            
            // Extract audience engagement patterns
            $insights['engagement_patterns'] = $this->extractEngagementPatterns($analyticsJson);
            
            // Extract content type performance
            $insights['content_type_performance'] = $this->extractContentTypePerformance($analyticsJson);
            
            // Platform-specific insights
            $insights['platform_specific_insights'] = $this->extractPlatformInsights($analyticsJson);
            
            $this->logActivity('Analytics parsed successfully', [
                'campaign_id' => $this->campaignId,
                'high_performing_posts' => count($insights['high_performing_content']),
                'low_performing_posts' => count($insights['low_performing_content']),
                'platforms_analyzed' => count($insights['platform_specific_insights'])
            ]);
            
        } catch (Exception $e) {
            $this->logError('Analytics parsing failed', $e);
            // Return basic structure even on failure
        }
        
        return $insights;
    }
    
    /**
     * Evolve strategy based on new analytics data
     * 
     * @param array $newAnalyticsJson Fresh analytics data
     * @param array $evolutionOptions Evolution preferences
     * @return array Evolution results and recommendations
     */
    public function evolveStrategy(array $newAnalyticsJson, array $evolutionOptions = []): array {
        if (!$this->campaignId) {
            throw new Exception("Campaign ID required for strategy evolution");
        }
        
        try {
            // Parse new analytics
            $newInsights = $this->parseAnalytics($newAnalyticsJson);
            
            // Compare with previous performance
            $performanceComparison = $this->comparePerformance($newInsights);
            
            // Generate evolution recommendations
            $recommendations = $this->generateEvolutionRecommendations(
                $performanceComparison, 
                $newInsights, 
                $evolutionOptions
            );
            
            // Apply approved recommendations
            $appliedChanges = [];
            if ($evolutionOptions['auto_apply'] ?? false) {
                $appliedChanges = $this->applyEvolutionChanges($recommendations);
            }
            
            // Store evolution data
            $this->storeEvolutionData($newInsights, $recommendations, $appliedChanges);
            
            $this->logActivity('Strategy evolution completed', [
                'campaign_id' => $this->campaignId,
                'recommendations' => count($recommendations),
                'changes_applied' => count($appliedChanges)
            ]);
            
            return [
                'success' => true,
                'insights' => $newInsights,
                'performance_comparison' => $performanceComparison,
                'recommendations' => $recommendations,
                'applied_changes' => $appliedChanges
            ];
            
        } catch (Exception $e) {
            $this->logError('Strategy evolution failed', $e);
            throw $e;
        }
    }
    
    /**
     * Get detailed plan for a specific week
     * 
     * @param int $weekNumber Week number (1-12)
     * @return array Week plan with posts and metadata
     */
    public function getWeeklyPlan(int $weekNumber): array {
        if (!$this->campaignId) {
            throw new Exception("Campaign ID required");
        }
        
        $stmt = $this->db->prepare("
            SELECT cw.*, cwp.id as post_id, cwp.platform, cwp.post_type, 
                   cwp.content, cwp.hashtags, cwp.scheduled_datetime,
                   cwp.call_to_action, cwp.content_pillar, cwp.status as post_status
            FROM campaign_weeks cw
            LEFT JOIN campaign_week_posts cwp ON cw.id = cwp.campaign_week_id
            WHERE cw.campaign_id = ? AND cw.week_number = ?
            ORDER BY cwp.post_order ASC
        ");
        
        $stmt->execute([$this->campaignId, $weekNumber]);
        $results = $stmt->fetchAll();
        
        if (empty($results)) {
            throw new Exception("Week {$weekNumber} not found for campaign {$this->campaignId}");
        }
        
        // Structure the week data
        $week = [
            'id' => $results[0]['id'],
            'week_number' => $results[0]['week_number'],
            'week_start_date' => $results[0]['week_start_date'],
            'week_end_date' => $results[0]['week_end_date'],
            'week_theme' => $results[0]['week_theme'],
            'objectives' => json_decode($results[0]['objectives'], true),
            'key_messages' => json_decode($results[0]['key_messages'], true),
            'content_strategy' => json_decode($results[0]['content_strategy'], true),
            'posting_schedule' => json_decode($results[0]['posting_schedule'], true),
            'kpi_targets' => json_decode($results[0]['kpi_targets'], true),
            'status' => $results[0]['status'],
            'completion_percentage' => $results[0]['completion_percentage'],
            'performance_score' => $results[0]['performance_score'],
            'posts' => []
        ];
        
        // Add posts
        foreach ($results as $row) {
            if ($row['post_id']) {
                $week['posts'][] = [
                    'id' => $row['post_id'],
                    'platform' => $row['platform'],
                    'post_type' => $row['post_type'],
                    'content' => $row['content'],
                    'hashtags' => $row['hashtags'],
                    'scheduled_datetime' => $row['scheduled_datetime'],
                    'call_to_action' => $row['call_to_action'],
                    'content_pillar' => $row['content_pillar'],
                    'status' => $row['post_status']
                ];
            }
        }
        
        return $week;
    }
    
    /**
     * Export complete strategy to structured JSON
     * 
     * @param array $exportOptions Export configuration
     * @return array Complete strategy data for export
     */
    public function exportToJSON(array $exportOptions = []): array {
        if (!$this->campaignId) {
            throw new Exception("Campaign ID required for export");
        }
        
        try {
            // Get campaign overview
            $campaign = $this->getCampaignOverview();
            
            // Get all weeks
            $weeks = [];
            $totalWeeks = $campaign['total_weeks'] ?? self::DEFAULT_WEEKS;
            
            for ($i = 1; $i <= $totalWeeks; $i++) {
                try {
                    $weeks[] = $this->getWeeklyPlan($i);
                } catch (Exception $e) {
                    // Week might not exist yet
                    $this->logActivity('Week not found during export', [
                        'campaign_id' => $this->campaignId,
                        'week_number' => $i
                    ]);
                }
            }
            
            // Get analytics if requested
            $analytics = [];
            if ($exportOptions['include_analytics'] ?? false) {
                $analytics = $this->getCampaignAnalytics();
            }
            
            // Build export structure
            $export = [
                'export_timestamp' => date('c'),
                'export_version' => '1.0',
                'campaign' => $campaign,
                'weeks' => $weeks,
                'total_posts' => array_sum(array_map(fn($week) => count($week['posts']), $weeks)),
                'export_options' => $exportOptions
            ];
            
            if (!empty($analytics)) {
                $export['analytics'] = $analytics;
            }
            
            $this->logActivity('Strategy exported successfully', [
                'campaign_id' => $this->campaignId,
                'weeks_exported' => count($weeks),
                'include_analytics' => $exportOptions['include_analytics'] ?? false
            ]);
            
            return $export;
            
        } catch (Exception $e) {
            $this->logError('Strategy export failed', $e);
            throw $e;
        }
    }
    
    /**
     * Build comprehensive prompt for full campaign generation
     */
    private function buildFullCampaignPrompt(array $params, ?array $analytics): string {
        $prompt = "Generate a comprehensive 12-week social media strategy as a detailed JSON structure.\n\n";
        
        // Campaign context
        $prompt .= "CAMPAIGN CONTEXT:\n";
        $prompt .= "- Business Type: " . ($params['business_type'] ?? 'General Business') . "\n";
        $prompt .= "- Primary Goal: " . ($params['primary_goal'] ?? 'Brand Awareness') . "\n";
        $prompt .= "- Target Audience: " . ($params['target_audience'] ?? 'General Audience') . "\n";
        $prompt .= "- Primary Offer: " . ($params['primary_offer'] ?? 'Service/Product') . "\n";
        $prompt .= "- Brand Voice: " . ($params['brand_voice'] ?? 'Professional') . "\n";
        $prompt .= "- Campaign Type: " . ($params['campaign_type'] ?? 'Brand Awareness') . "\n";
        $prompt .= "- Platforms: " . implode(', ', $params['platforms'] ?? ['Instagram', 'Facebook']) . "\n";
        $prompt .= "- Posts per Week: " . ($params['posts_per_week'] ?? 5) . "\n\n";
        
        // Analytics insights if available
        if ($analytics && !empty($analytics['high_performing_content'])) {
            $prompt .= "PERFORMANCE INSIGHTS:\n";
            $prompt .= "High-performing content patterns:\n";
            foreach (array_slice($analytics['high_performing_content'], 0, 3) as $content) {
                $prompt .= "- " . substr($content['content'], 0, 100) . "... (Engagement: {$content['engagement_rate']}%)\n";
            }
            
            if (!empty($analytics['optimal_posting_times'])) {
                $prompt .= "\nOptimal posting times: " . implode(', ', array_slice($analytics['optimal_posting_times'], 0, 3)) . "\n";
            }
            
            if (!empty($analytics['top_hashtags'])) {
                $prompt .= "Top performing hashtags: " . implode(', ', array_slice($analytics['top_hashtags'], 0, 5)) . "\n\n";
            }
        }
        
        // Key dates if provided
        if (!empty($params['key_dates'])) {
            $prompt .= "KEY DATES TO INCORPORATE:\n";
            foreach ($params['key_dates'] as $date) {
                $prompt .= "- {$date['title']} ({$date['date_value']}): {$date['description']}\n";
            }
            $prompt .= "\n";
        }
        
        // Generation instructions
        $prompt .= "GENERATION REQUIREMENTS:\n";
        $prompt .= "Generate a strategic 12-week campaign with week-by-week progression. Each week should build upon the previous weeks and work toward the overall campaign goal.\n\n";
        
        $prompt .= "Required JSON structure:\n";
        $prompt .= "{\n";
        $prompt .= "  \"campaign_overview\": {\n";
        $prompt .= "    \"strategy_summary\": \"Brief strategy overview\",\n";
        $prompt .= "    \"success_metrics\": [\"metric1\", \"metric2\"],\n";
        $prompt .= "    \"content_pillars\": [\"pillar1\", \"pillar2\", \"pillar3\"]\n";
        $prompt .= "  },\n";
        $prompt .= "  \"weeks\": [\n";
        $prompt .= "    {\n";
        $prompt .= "      \"week_number\": 1,\n";
        $prompt .= "      \"theme\": \"Week theme\",\n";
        $prompt .= "      \"objectives\": [\"objective1\", \"objective2\"],\n";
        $prompt .= "      \"key_messages\": [\"message1\", \"message2\"],\n";
        $prompt .= "      \"posts\": [\n";
        $prompt .= "        {\n";
        $prompt .= "          \"platform\": \"instagram\",\n";
        $prompt .= "          \"post_type\": \"feed\",\n";
        $prompt .= "          \"content\": \"Engaging post content\",\n";
        $prompt .= "          \"hashtags\": \"#hashtag1 #hashtag2\",\n";
        $prompt .= "          \"call_to_action\": \"CTA text\",\n";
        $prompt .= "          \"content_pillar\": \"pillar_name\",\n";
        $prompt .= "          \"visual_requirements\": \"Image/video description\"\n";
        $prompt .= "        }\n";
        $prompt .= "      ]\n";
        $prompt .= "    }\n";
        $prompt .= "  ]\n";
        $prompt .= "}\n\n";
        
        $prompt .= "Ensure content variety, strategic progression, and alignment with the brand voice and objectives.";
        
        return $prompt;
    }
    
    /**
     * Parse AI response into structured strategy data
     */
    private function parseStrategyResponse(array $aiResponse, array $params): array {
        $strategy = [
            'campaign_overview' => [],
            'weeks' => []
        ];
        
        try {
            // Try to parse JSON from AI response
            $content = is_array($aiResponse) ? implode("\n", $aiResponse) : $aiResponse;
            
            // Look for JSON in the response
            if (preg_match('/\{.*\}/s', $content, $matches)) {
                $jsonData = json_decode($matches[0], true);
                
                if ($jsonData && isset($jsonData['weeks'])) {
                    $strategy = $jsonData;
                }
            }
            
            // If JSON parsing failed, parse as text
            if (empty($strategy['weeks'])) {
                $strategy = $this->parseTextStrategyResponse($content, $params);
            }
            
            // Validate and enhance strategy
            $strategy = $this->validateAndEnhanceStrategy($strategy, $params);
            
        } catch (Exception $e) {
            $this->logError('Strategy response parsing failed', $e);
            // Return basic structure
            $strategy = $this->generateFallbackStrategy($params);
        }
        
        return $strategy;
    }
    
    /**
     * Store complete strategy in database
     */
    private function storeFullStrategy(int $campaignId, array $strategy): void {
        // Store campaign overview updates
        if (!empty($strategy['campaign_overview'])) {
            $this->updateCampaignOverview($campaignId, $strategy['campaign_overview']);
        }
        
        // Store each week
        foreach ($strategy['weeks'] as $weekData) {
            $this->storeWeekStrategy($campaignId, $weekData);
        }
    }
    
    /**
     * Store individual week strategy
     */
    private function storeWeekStrategy(int $campaignId, array $weekData): void {
        $weekNumber = $weekData['week_number'];
        $startDate = $this->calculateWeekStartDate($campaignId, $weekNumber);
        $endDate = date('Y-m-d', strtotime($startDate . ' +6 days'));
        
        // Insert or update week record
        $stmt = $this->db->prepare("
            INSERT INTO campaign_weeks (
                campaign_id, week_number, week_start_date, week_end_date,
                week_theme, objectives, key_messages, content_strategy,
                generated_by_ai, generation_timestamp, status
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 1, NOW(), 'draft')
            ON DUPLICATE KEY UPDATE
                week_theme = VALUES(week_theme),
                objectives = VALUES(objectives),
                key_messages = VALUES(key_messages),
                content_strategy = VALUES(content_strategy),
                generation_timestamp = NOW()
        ");
        
        $stmt->execute([
            $campaignId,
            $weekNumber,
            $startDate,
            $endDate,
            $weekData['theme'] ?? "Week {$weekNumber}",
            json_encode($weekData['objectives'] ?? []),
            json_encode($weekData['key_messages'] ?? []),
            json_encode([
                'posts_planned' => count($weekData['posts'] ?? []),
                'content_mix' => $this->analyzeContentMix($weekData['posts'] ?? [])
            ])
        ]);
        
        $weekId = $this->db->lastInsertId() ?: $this->getWeekId($campaignId, $weekNumber);
        
        // Store posts for this week
        $this->storeWeekPosts($weekId, $campaignId, $weekData['posts'] ?? []);
    }
    
    /**
     * Store posts for a specific week
     */
    private function storeWeekPosts(int $weekId, int $campaignId, array $posts): void {
        // Clear existing posts
        $stmt = $this->db->prepare("DELETE FROM campaign_week_posts WHERE campaign_week_id = ?");
        $stmt->execute([$weekId]);
        
        $postOrder = 1;
        foreach ($posts as $post) {
            $stmt = $this->db->prepare("
                INSERT INTO campaign_week_posts (
                    campaign_week_id, campaign_id, post_order, platform, post_type,
                    content, hashtags, call_to_action, content_pillar, 
                    media_requirements, generated_by_ai, status
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1, 'planned')
            ");
            
            $stmt->execute([
                $weekId,
                $campaignId,
                $postOrder++,
                $post['platform'] ?? 'instagram',
                $post['post_type'] ?? 'feed',
                $post['content'] ?? '',
                $post['hashtags'] ?? '',
                $post['call_to_action'] ?? '',
                $post['content_pillar'] ?? '',
                json_encode(['description' => $post['visual_requirements'] ?? ''])
            ]);
        }
    }
    
    /**
     * Load campaign data and wizard settings
     */
    private function loadCampaignData(): void {
        if (!$this->campaignId) return;
        
        $stmt = $this->db->prepare("
            SELECT sc.*, cg.primary_goal, cg.target_audience, cg.success_metrics,
                   co.primary_offer, co.offer_type, co.call_to_action,
                   cvt.brand_voice, cvt.writing_style, cvt.tone_attributes,
                   ct.campaign_type, ct.content_pillars, ct.posting_frequency
            FROM strategy_campaigns sc
            LEFT JOIN campaign_goals cg ON sc.id = cg.campaign_id
            LEFT JOIN campaign_offers co ON sc.id = co.campaign_id
            LEFT JOIN campaign_voice_tone cvt ON sc.id = cvt.campaign_id
            LEFT JOIN campaign_types ct ON sc.id = ct.campaign_id
            WHERE sc.id = ?
        ");
        
        $stmt->execute([$this->campaignId]);
        $this->wizardData = $stmt->fetch();
    }
    
    /**
     * Store campaign wizard data and return campaign ID
     */
    private function storeCampaignWizardData(array $params): int {
        // Create main campaign record
        $stmt = $this->db->prepare("
            INSERT INTO strategy_campaigns (
                client_id, title, description, total_weeks, start_date, 
                ai_model, generation_settings, status, created_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, 'draft', NOW())
        ");
        
        $stmt->execute([
            $this->clientId,
            $params['title'] ?? 'AI Generated Campaign',
            $params['description'] ?? 'Generated by AI Strategy Engine',
            $params['total_weeks'] ?? self::DEFAULT_WEEKS,
            $params['start_date'] ?? date('Y-m-d'),
            $params['ai_model'] ?? 'claude',
            json_encode($params['generation_settings'] ?? [])
        ]);
        
        $campaignId = $this->db->lastInsertId();
        
        // Store wizard step data
        $this->storeWizardStepData($campaignId, $params);
        
        return $campaignId;
    }
    
    /**
     * Store wizard step data in respective tables
     */
    private function storeWizardStepData(int $campaignId, array $params): void {
        // Goals and objectives
        if (!empty($params['primary_goal'])) {
            $stmt = $this->db->prepare("
                INSERT INTO campaign_goals (
                    campaign_id, primary_goal, secondary_goals, target_audience,
                    success_metrics, business_objectives, brand_positioning
                ) VALUES (?, ?, ?, ?, ?, ?, ?)
            ");
            
            $stmt->execute([
                $campaignId,
                $params['primary_goal'],
                json_encode($params['secondary_goals'] ?? []),
                $params['target_audience'] ?? '',
                json_encode($params['success_metrics'] ?? []),
                $params['business_objectives'] ?? '',
                $params['brand_positioning'] ?? ''
            ]);
        }
        
        // Offers and value propositions
        if (!empty($params['primary_offer'])) {
            $stmt = $this->db->prepare("
                INSERT INTO campaign_offers (
                    campaign_id, primary_offer, offer_type, price_point,
                    call_to_action, landing_page_url, offer_details
                ) VALUES (?, ?, ?, ?, ?, ?, ?)
            ");
            
            $stmt->execute([
                $campaignId,
                $params['primary_offer'],
                $params['offer_type'] ?? 'service',
                $params['price_point'] ?? null,
                $params['call_to_action'] ?? '',
                $params['landing_page_url'] ?? '',
                json_encode($params['offer_details'] ?? [])
            ]);
        }
        
        // Voice and tone
        if (!empty($params['brand_voice'])) {
            $stmt = $this->db->prepare("
                INSERT INTO campaign_voice_tone (
                    campaign_id, brand_voice, tone_attributes, writing_style,
                    personality_traits, do_use, dont_use, hashtag_strategy
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ");
            
            $stmt->execute([
                $campaignId,
                $params['brand_voice'],
                json_encode($params['tone_attributes'] ?? []),
                $params['writing_style'] ?? 'conversational',
                json_encode($params['personality_traits'] ?? []),
                $params['do_use'] ?? '',
                $params['dont_use'] ?? '',
                $params['hashtag_strategy'] ?? ''
            ]);
        }
        
        // Campaign types and posting frequency
        if (!empty($params['campaign_type'])) {
            $stmt = $this->db->prepare("
                INSERT INTO campaign_types (
                    campaign_id, campaign_type, content_pillars, posting_frequency,
                    platform_focus, content_formats, engagement_strategy
                ) VALUES (?, ?, ?, ?, ?, ?, ?)
            ");
            
            $stmt->execute([
                $campaignId,
                $params['campaign_type'],
                json_encode($params['content_pillars'] ?? []),
                json_encode($params['posting_frequency'] ?? []),
                json_encode($params['platforms'] ?? []),
                json_encode($params['content_formats'] ?? []),
                $params['engagement_strategy'] ?? ''
            ]);
        }
        
        // Key dates
        if (!empty($params['key_dates'])) {
            $stmt = $this->db->prepare("
                INSERT INTO campaign_key_dates (
                    campaign_id, date_type, date_value, title, description, importance
                ) VALUES (?, ?, ?, ?, ?, ?)
            ");
            
            foreach ($params['key_dates'] as $date) {
                $stmt->execute([
                    $campaignId,
                    $date['date_type'] ?? 'event',
                    $date['date_value'],
                    $date['title'],
                    $date['description'] ?? '',
                    $date['importance'] ?? 'medium'
                ]);
            }
        }
    }
    
    /**
     * Helper methods for analytics processing
     */
    private function calculateEngagementRate(array $post): float {
        $impressions = $post['impressions'] ?? $post['reach'] ?? 1;
        $engagement = ($post['likes'] ?? 0) + ($post['comments'] ?? 0) + 
                     ($post['shares'] ?? 0) + ($post['saves'] ?? 0);
        
        return $impressions > 0 ? round(($engagement / $impressions) * 100, 2) : 0;
    }
    
    private function extractPostingTimePatterns(array $analytics): array {
        $times = [];
        
        if (isset($analytics['posts'])) {
            foreach ($analytics['posts'] as $post) {
                if (!empty($post['posted_at'])) {
                    $hour = date('H:i', strtotime($post['posted_at']));
                    $engagement = $this->calculateEngagementRate($post);
                    $times[$hour] = ($times[$hour] ?? 0) + $engagement;
                }
            }
        }
        
        arsort($times);
        return array_keys(array_slice($times, 0, 5));
    }
    
    private function extractHashtagPerformance(array $analytics): array {
        $hashtags = [];
        
        if (isset($analytics['posts'])) {
            foreach ($analytics['posts'] as $post) {
                if (!empty($post['hashtags'])) {
                    $postHashtags = explode(' ', $post['hashtags']);
                    $engagement = $this->calculateEngagementRate($post);
                    
                    foreach ($postHashtags as $tag) {
                        $tag = trim($tag);
                        if (strpos($tag, '#') === 0) {
                            $hashtags[$tag] = ($hashtags[$tag] ?? 0) + $engagement;
                        }
                    }
                }
            }
        }
        
        arsort($hashtags);
        return array_keys(array_slice($hashtags, 0, 10));
    }
    
    private function extractEngagementPatterns(array $analytics): array {
        // Extract day-of-week patterns, content type patterns, etc.
        return []; // Implement as needed
    }
    
    private function extractContentTypePerformance(array $analytics): array {
        $types = [];
        
        if (isset($analytics['posts'])) {
            foreach ($analytics['posts'] as $post) {
                $type = $post['post_type'] ?? 'unknown';
                $engagement = $this->calculateEngagementRate($post);
                
                if (!isset($types[$type])) {
                    $types[$type] = ['count' => 0, 'total_engagement' => 0];
                }
                
                $types[$type]['count']++;
                $types[$type]['total_engagement'] += $engagement;
            }
        }
        
        // Calculate averages
        foreach ($types as $type => $data) {
            $types[$type]['avg_engagement'] = $data['total_engagement'] / $data['count'];
        }
        
        return $types;
    }
    
    private function extractPlatformInsights(array $analytics): array {
        $platforms = [];
        
        if (isset($analytics['posts'])) {
            foreach ($analytics['posts'] as $post) {
                $platform = $post['platform'] ?? 'unknown';
                
                if (!isset($platforms[$platform])) {
                    $platforms[$platform] = [
                        'post_count' => 0,
                        'total_engagement' => 0,
                        'avg_engagement' => 0
                    ];
                }
                
                $platforms[$platform]['post_count']++;
                $platforms[$platform]['total_engagement'] += $post['total_engagement'] ?? 0;
            }
        }
        
        // Calculate averages
        foreach ($platforms as $platform => $data) {
            if ($data['post_count'] > 0) {
                $platforms[$platform]['avg_engagement'] = $data['total_engagement'] / $data['post_count'];
            }
        }
        
        return $platforms;
    }
    
    /**
     * Utility methods
     */
    private function updateCampaignStatus(int $campaignId, string $status): void {
        $stmt = $this->db->prepare("UPDATE strategy_campaigns SET status = ? WHERE id = ?");
        $stmt->execute([$status, $campaignId]);
    }
    
    private function calculateWeekStartDate(int $campaignId, int $weekNumber): string {
        $stmt = $this->db->prepare("SELECT start_date FROM strategy_campaigns WHERE id = ?");
        $stmt->execute([$campaignId]);
        $campaign = $stmt->fetch();
        
        $startDate = $campaign['start_date'] ?? date('Y-m-d');
        return date('Y-m-d', strtotime($startDate . ' +' . (($weekNumber - 1) * 7) . ' days'));
    }
    
    private function getWeekId(int $campaignId, int $weekNumber): int {
        $stmt = $this->db->prepare("SELECT id FROM campaign_weeks WHERE campaign_id = ? AND week_number = ?");
        $stmt->execute([$campaignId, $weekNumber]);
        $result = $stmt->fetch();
        return $result ? $result['id'] : 0;
    }
    
    private function analyzeContentMix(array $posts): array {
        $mix = [];
        foreach ($posts as $post) {
            $type = $post['post_type'] ?? 'unknown';
            $mix[$type] = ($mix[$type] ?? 0) + 1;
        }
        return $mix;
    }
    
    private function logActivity(string $message, array $context = []): void {
        error_log("[CampaignStrategyEngine] {$message}: " . json_encode($context));
    }
    
    private function logError(string $message, Exception $e, array $context = []): void {
        error_log("[CampaignStrategyEngine ERROR] {$message}: " . $e->getMessage() . " | Context: " . json_encode($context));
    }
    
    // Additional methods would be implemented here:
    // - buildWeekRegenerationPrompt()
    // - parseWeekResponse()
    // - getWeekData()
    // - getCampaignContext()
    // - getAdjacentWeeksContext()
    // - storeRegenerationHistory()
    // - updateWeekData()
    // - comparePerformance()
    // - generateEvolutionRecommendations()
    // - applyEvolutionChanges()
    // - storeEvolutionData()
    // - getCampaignOverview()
    // - getCampaignAnalytics()
    // - parseTextStrategyResponse()
    // - validateAndEnhanceStrategy()
    // - generateFallbackStrategy()
    // - updateCampaignOverview()
    
    // These would follow the same patterns established above
}