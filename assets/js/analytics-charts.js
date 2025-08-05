/**
 * Analytics Charts Module
 * Handles all chart initialization and data loading for the analytics dashboard
 */

class AnalyticsCharts {
    constructor() {
        this.charts = {};
        this.currentPeriod = '30d';
        this.currentPlatform = 'all';
        this.refreshInterval = null;
        this.isVisible = true;
        
        this.initEventListeners();
        this.setupVisibilityHandling();
    }

    // Initialize all event listeners
    initEventListeners() {
        document.addEventListener('visibilitychange', () => {
            this.isVisible = !document.hidden;
            if (this.isVisible) {
                this.refreshAllData();
            }
        });

        // Touch swipe support for mobile charts
        this.setupTouchSwipe();
    }

    // Setup touch swipe navigation for mobile charts
    setupTouchSwipe() {
        let touchStartX = 0;
        let touchEndX = 0;

        document.addEventListener('touchstart', (e) => {
            touchStartX = e.changedTouches[0].screenX;
        }, { passive: true });

        document.addEventListener('touchend', (e) => {
            touchEndX = e.changedTouches[0].screenX;
            this.handleSwipe(touchStartX, touchEndX);
        }, { passive: true });
    }

    // Handle swipe gestures
    handleSwipe(startX, endX) {
        const swipeThreshold = 50;
        const diff = startX - endX;

        if (Math.abs(diff) > swipeThreshold) {
            const charts = ['engagement', 'platforms', 'posting-times', 'hashtags'];
            const currentIndex = charts.findIndex(chart => 
                document.getElementById(`chart-${chart}`)?.style.display !== 'none'
            );

            let nextIndex;
            if (diff > 0) { // Swipe left - next chart
                nextIndex = (currentIndex + 1) % charts.length;
            } else { // Swipe right - previous chart
                nextIndex = (currentIndex - 1 + charts.length) % charts.length;
            }

            this.showMobileChart(charts[nextIndex]);
        }
    }

    // Mobile chart switching with haptic feedback
    showMobileChart(chartType) {
        // Trigger haptic feedback
        if ('vibrate' in navigator) {
            navigator.vibrate(10);
        }

        // Hide all charts
        document.querySelectorAll('.mobile-chart').forEach(chart => {
            chart.style.display = 'none';
        });

        // Show selected chart
        const targetChart = document.getElementById(`chart-${chartType}`);
        if (targetChart) {
            targetChart.style.display = 'block';
        }

        // Update tab styles
        document.querySelectorAll('.chart-tab').forEach(tab => {
            tab.classList.remove('active', 'bg-purple-600', 'text-white');
            tab.classList.add('bg-gray-800', 'text-gray-300');
        });

        // Find and activate the correct tab
        const activeTab = Array.from(document.querySelectorAll('.chart-tab'))
            .find(tab => tab.textContent.toLowerCase().includes(chartType.replace('-', ' ')));
        
        if (activeTab) {
            activeTab.classList.add('active', 'bg-purple-600', 'text-white');
            activeTab.classList.remove('bg-gray-800', 'text-gray-300');
        }

        // Load chart data if needed
        this.loadChartData(chartType);
    }

    // Setup page visibility handling
    setupVisibilityHandling() {
        document.addEventListener('visibilitychange', () => {
            this.isVisible = !document.hidden;
            if (this.isVisible) {
                this.refreshAllData();
            }
        });
    }

    // Initialize all charts
    async initializeCharts() {
        try {
            await this.initEngagementChart();
            await this.initPlatformCharts();
            await this.initDemographicCharts();
            
            // Load initial data
            this.loadTopPosts();
            this.loadActivityFeed();
            this.loadHashtagCloud();
            this.loadPostingTimesHeatmap();
            
            // Show first mobile chart
            this.showMobileChart('engagement');
            
        } catch (error) {
            console.error('Error initializing charts:', error);
        }
    }

    // Engagement rate trend chart
    async initEngagementChart() {
        const ctx = document.getElementById('engagementChart')?.getContext('2d');
        if (!ctx) return;

        this.charts.engagement = new Chart(ctx, {
            type: 'line',
            data: {
                labels: [],
                datasets: [{
                    label: 'Engagement Rate',
                    data: [],
                    borderColor: 'rgba(139, 92, 246, 1)',
                    backgroundColor: 'rgba(139, 92, 246, 0.1)',
                    borderWidth: 2,
                    fill: true,
                    tension: 0.4,
                    pointBackgroundColor: 'rgba(139, 92, 246, 1)',
                    pointBorderColor: '#ffffff',
                    pointBorderWidth: 2,
                    pointRadius: 4,
                    pointHoverRadius: 6
                }]
            },
            options: this.getBaseChartOptions({
                scales: {
                    x: {
                        type: 'time',
                        time: {
                            unit: 'day',
                            displayFormats: {
                                day: 'MMM dd'
                            }
                        },
                        grid: {
                            color: 'rgba(55, 65, 81, 0.5)'
                        },
                        ticks: {
                            maxTicksLimit: 7
                        }
                    },
                    y: {
                        beginAtZero: true,
                        grid: {
                            color: 'rgba(55, 65, 81, 0.5)'
                        },
                        ticks: {
                            callback: function(value) {
                                return value + '%';
                            }
                        }
                    }
                }
            })
        });

        this.loadEngagementData();
    }

    // Platform comparison charts (mobile and desktop)
    async initPlatformCharts() {
        const platforms = [
            { name: 'facebook', color: 'rgba(59, 130, 246, 0.8)' },
            { name: 'instagram', color: 'rgba(236, 72, 153, 0.8)' },
            { name: 'twitter', color: 'rgba(29, 161, 242, 0.8)' },
            { name: 'linkedin', color: 'rgba(10, 102, 194, 0.8)' }
        ];

        const chartConfig = {
            type: 'bar',
            data: {
                labels: [],
                datasets: [{
                    label: 'Engagement Rate',
                    data: [],
                    backgroundColor: platforms.map(p => p.color),
                    borderColor: platforms.map(p => p.color.replace('0.8', '1')),
                    borderWidth: 1,
                    borderRadius: 6,
                    borderSkipped: false
                }]
            },
            options: this.getBaseChartOptions({
                scales: {
                    x: {
                        grid: { display: false }
                    },
                    y: {
                        beginAtZero: true,
                        grid: {
                            color: 'rgba(55, 65, 81, 0.5)'
                        },
                        ticks: {
                            callback: function(value) {
                                return value + '%';
                            }
                        }
                    }
                }
            })
        };

        // Mobile chart
        const mobileCtx = document.getElementById('platformChart')?.getContext('2d');
        if (mobileCtx) {
            this.charts.platformMobile = new Chart(mobileCtx, chartConfig);
        }

        // Desktop chart
        const desktopCtx = document.getElementById('platformChartDesktop')?.getContext('2d');
        if (desktopCtx) {
            this.charts.platformDesktop = new Chart(desktopCtx, chartConfig);
        }

        this.loadPlatformData();
    }

    // Demographic charts
    async initDemographicCharts() {
        // Age distribution
        const ageCtx = document.getElementById('ageChart')?.getContext('2d');
        if (ageCtx) {
            this.charts.age = new Chart(ageCtx, {
                type: 'doughnut',
                data: {
                    labels: ['18-24', '25-34', '35-44', '45-54', '55+'],
                    datasets: [{
                        data: [25, 35, 20, 15, 5],
                        backgroundColor: [
                            'rgba(139, 92, 246, 0.8)',
                            'rgba(59, 130, 246, 0.8)',
                            'rgba(16, 185, 129, 0.8)',
                            'rgba(245, 158, 11, 0.8)',
                            'rgba(239, 68, 68, 0.8)'
                        ],
                        borderWidth: 0
                    }]
                },
                options: this.getDoughnutChartOptions()
            });
        }

        // Gender distribution
        const genderCtx = document.getElementById('genderChart')?.getContext('2d');
        if (genderCtx) {
            this.charts.gender = new Chart(genderCtx, {
                type: 'doughnut',
                data: {
                    labels: ['Female', 'Male', 'Other'],
                    datasets: [{
                        data: [52, 46, 2],
                        backgroundColor: [
                            'rgba(236, 72, 153, 0.8)',
                            'rgba(59, 130, 246, 0.8)',
                            'rgba(107, 114, 128, 0.8)'
                        ],
                        borderWidth: 0
                    }]
                },
                options: this.getDoughnutChartOptions()
            });
        }

        this.loadDemographicData();
    }

    // Get base chart options
    getBaseChartOptions(customOptions = {}) {
        const baseOptions = {
            responsive: true,
            maintainAspectRatio: false,
            interaction: {
                intersect: false,
                mode: 'index'
            },
            plugins: {
                legend: {
                    display: false
                },
                tooltip: {
                    backgroundColor: 'rgba(17, 24, 39, 0.9)',
                    titleColor: '#ffffff',
                    bodyColor: '#ffffff',
                    borderColor: 'rgba(139, 92, 246, 1)',
                    borderWidth: 1
                }
            }
        };

        return this.deepMerge(baseOptions, customOptions);
    }

    // Get doughnut chart options
    getDoughnutChartOptions() {
        return {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'bottom',
                    labels: {
                        usePointStyle: true,
                        padding: 15,
                        font: {
                            size: 11
                        }
                    }
                },
                tooltip: {
                    backgroundColor: 'rgba(17, 24, 39, 0.9)',
                    titleColor: '#ffffff',
                    bodyColor: '#ffffff',
                    borderColor: 'rgba(139, 92, 246, 1)',
                    borderWidth: 1
                }
            }
        };
    }

    // Data loading methods
    async loadEngagementData() {
        try {
            const response = await fetch(`/api/analytics/get.php?type=engagement&period=${this.currentPeriod}&platform=${this.currentPlatform}`);
            const data = await response.json();

            if (data.success && this.charts.engagement) {
                const chartData = data.data.map(item => ({
                    x: new Date(item.date),
                    y: parseFloat(item.avg_engagement_rate) || 0
                }));

                this.charts.engagement.data.datasets[0].data = chartData;
                this.charts.engagement.update('none');

                this.hideLoading('engagementLoading');
            }
        } catch (error) {
            console.error('Error loading engagement data:', error);
            this.hideLoading('engagementLoading');
        }
    }

    async loadPlatformData() {
        try {
            const response = await fetch(`/api/analytics/get.php?type=platform-comparison&period=${this.currentPeriod}`);
            const data = await response.json();

            if (data.success) {
                const labels = data.data.map(item => 
                    item.platform.charAt(0).toUpperCase() + item.platform.slice(1)
                );
                const engagementRates = data.data.map(item => 
                    parseFloat(item.avg_engagement_rate) || 0
                );

                // Update both charts
                [this.charts.platformMobile, this.charts.platformDesktop].forEach(chart => {
                    if (chart) {
                        chart.data.labels = labels;
                        chart.data.datasets[0].data = engagementRates;
                        chart.update('none');
                    }
                });

                this.hideLoading('platformLoading');
            }
        } catch (error) {
            console.error('Error loading platform data:', error);
            this.hideLoading('platformLoading');
        }
    }

    async loadTopPosts() {
        try {
            const response = await fetch(`/api/analytics/get.php?type=posts&period=${this.currentPeriod}&platform=${this.currentPlatform}`);
            const data = await response.json();

            if (data.success) {
                this.renderTopPosts(data.data);
            }
        } catch (error) {
            console.error('Error loading top posts:', error);
        }
    }

    async loadActivityFeed() {
        try {
            const response = await fetch(`/api/analytics/get.php?type=activity&period=7d`);
            const data = await response.json();

            if (data.success) {
                this.renderActivityFeed(data.data);
            }
        } catch (error) {
            console.error('Error loading activity feed:', error);
        }
    }

    async loadHashtagCloud() {
        try {
            const response = await fetch(`/api/analytics/get.php?type=hashtags&period=${this.currentPeriod}&platform=${this.currentPlatform}`);
            const data = await response.json();

            if (data.success) {
                this.renderHashtagCloud(data.data, 'hashtagCloud');
                this.renderHashtagCloud(data.data, 'hashtagCloudDesktop');
            }
        } catch (error) {
            console.error('Error loading hashtag data:', error);
        }
    }

    async loadPostingTimesHeatmap() {
        try {
            const response = await fetch(`/api/analytics/get.php?type=best-times&platform=${this.currentPlatform}`);
            const data = await response.json();

            if (data.success) {
                this.renderPostingTimesHeatmap(data.data, 'postingTimesHeatmap');
                this.renderPostingTimesHeatmap(data.data, 'postingTimesHeatmapDesktop');
                this.hideLoading('heatmapLoading');
            }
        } catch (error) {
            console.error('Error loading posting times:', error);
            this.hideLoading('heatmapLoading');
        }
    }

    async loadDemographicData() {
        // Mock demographic data - replace with real API call when available
        const locations = [
            { name: 'United States', percentage: 35 },
            { name: 'United Kingdom', percentage: 15 },
            { name: 'Canada', percentage: 12 },
            { name: 'Australia', percentage: 8 },
            { name: 'Germany', percentage: 6 }
        ];

        this.renderLocationData(locations);
    }

    // Rendering methods
    renderTopPosts(posts) {
        const container = document.getElementById('topPostsContainer');
        if (!container) return;

        container.innerHTML = '';

        posts.slice(0, 5).forEach(post => {
            const postElement = document.createElement('div');
            postElement.className = 'p-3 bg-gray-800 rounded-lg hover:bg-gray-750 transition-colors cursor-pointer';

            const content = post.content.length > 100 ? 
                post.content.substring(0, 100) + '...' : post.content;
            
            const platformColors = {
                'facebook': 'text-blue-400',
                'instagram': 'text-pink-400',
                'twitter': 'text-blue-300',
                'linkedin': 'text-blue-600'
            };

            postElement.innerHTML = `
                <div class="flex items-start justify-between mb-2">
                    <span class="text-xs ${platformColors[post.platform] || 'text-gray-400'} font-medium uppercase">
                        ${post.platform}
                    </span>
                    <span class="text-xs text-gray-500">
                        ${new Date(post.published_at).toLocaleDateString()}
                    </span>
                </div>
                <p class="text-sm text-gray-300 mb-2">${content}</p>
                <div class="flex items-center justify-between text-xs">
                    <div class="flex items-center space-x-3">
                        <span class="text-pink-400">❤ ${this.formatNumber(post.likes || 0)}</span>
                        <span class="text-blue-400">💬 ${this.formatNumber(post.comments || 0)}</span>
                        <span class="text-green-400">🔄 ${this.formatNumber(post.shares || 0)}</span>
                    </div>
                    <span class="text-purple-400 font-medium">${post.engagement_rate}%</span>
                </div>
            `;

            container.appendChild(postElement);
        });
    }

    renderActivityFeed(activities) {
        const container = document.getElementById('activityFeed');
        if (!container) return;

        container.innerHTML = '';

        activities.forEach(activity => {
            const activityElement = document.createElement('div');
            activityElement.className = 'flex items-center space-x-3 p-3 bg-gray-800 rounded-lg hover:bg-gray-750 transition-colors';

            const icon = activity.type === 'post_published' ? '📝' : '📊';
            const timeAgo = this.getTimeAgo(new Date(activity.timestamp));

            activityElement.innerHTML = `
                <div class="text-lg">${icon}</div>
                <div class="flex-1 min-w-0">
                    <p class="text-sm text-gray-300 truncate">${activity.message}</p>
                    <p class="text-xs text-gray-500">${activity.platform} • ${timeAgo}</p>
                </div>
            `;

            container.appendChild(activityElement);
        });
    }

    renderHashtagCloud(hashtags, containerId) {
        const container = document.getElementById(containerId);
        if (!container) return;

        if (hashtags.length === 0) {
            container.innerHTML = '<p class="text-gray-500 text-center">No hashtag data available</p>';
            return;
        }

        const maxEngagement = Math.max(...hashtags.map(h => parseFloat(h.avg_engagement_rate)));

        container.innerHTML = '';
        container.className = 'flex flex-wrap gap-2 p-4';

        hashtags.slice(0, 30).forEach(hashtag => {
            const size = Math.max(12, (parseFloat(hashtag.avg_engagement_rate) / maxEngagement) * 24);
            const opacity = 0.6 + (parseFloat(hashtag.avg_engagement_rate) / maxEngagement) * 0.4;

            const hashtagElement = document.createElement('span');
            hashtagElement.className = 'inline-block px-2 py-1 bg-purple-600 rounded-full text-white font-medium hover:bg-purple-500 transition-colors cursor-pointer';
            hashtagElement.style.fontSize = `${size}px`;
            hashtagElement.style.opacity = opacity;
            hashtagElement.textContent = `#${hashtag.hashtag}`;
            hashtagElement.title = `Used ${hashtag.usage_count} times • ${hashtag.avg_engagement_rate}% avg engagement`;

            container.appendChild(hashtagElement);
        });
    }

    renderPostingTimesHeatmap(data, containerId) {
        const container = document.getElementById(containerId);
        if (!container) return;

        const days = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];
        const hours = Array.from({length: 24}, (_, i) => i);

        // Convert data to heatmap matrix
        const heatmapData = {};
        let maxEngagement = 0;

        data.forEach(item => {
            const key = `${item.day_of_week}-${item.hour_of_day}`;
            heatmapData[key] = parseFloat(item.avg_engagement_rate) || 0;
            maxEngagement = Math.max(maxEngagement, heatmapData[key]);
        });

        container.innerHTML = '';

        const heatmapContainer = document.createElement('div');
        heatmapContainer.className = 'grid gap-1 text-xs';
        heatmapContainer.style.gridTemplateColumns = 'auto repeat(24, 1fr)';

        // Header row with hours
        heatmapContainer.appendChild(document.createElement('div')); // Empty cell
        hours.forEach(hour => {
            const hourCell = document.createElement('div');
            hourCell.className = 'text-center text-gray-400 py-1';
            hourCell.textContent = hour.toString().padStart(2, '0');
            heatmapContainer.appendChild(hourCell);
        });

        // Data rows
        days.forEach((day, dayIndex) => {
            const dayCell = document.createElement('div');
            dayCell.className = 'text-right text-gray-400 pr-2 py-1';
            dayCell.textContent = day;
            heatmapContainer.appendChild(dayCell);

            hours.forEach(hour => {
                const key = `${dayIndex}-${hour}`;
                const value = heatmapData[key] || 0;
                const intensity = maxEngagement > 0 ? value / maxEngagement : 0;

                const cell = document.createElement('div');
                cell.className = 'aspect-square rounded border border-gray-700 cursor-pointer hover:border-purple-400 transition-colors';
                cell.style.backgroundColor = `rgba(139, 92, 246, ${intensity})`;
                cell.title = `${day} ${hour}:00 - ${value.toFixed(1)}% engagement`;

                heatmapContainer.appendChild(cell);
            });
        });

        container.appendChild(heatmapContainer);
    }

    renderLocationData(locations) {
        const container = document.getElementById('locationsContainer');
        if (!container) return;

        container.innerHTML = '';

        locations.forEach(location => {
            const locationElement = document.createElement('div');
            locationElement.className = 'flex items-center justify-between py-1';
            locationElement.innerHTML = `
                <span class="text-sm text-gray-300">${location.name}</span>
                <span class="text-sm text-purple-400 font-medium">${location.percentage}%</span>
            `;
            container.appendChild(locationElement);
        });
    }

    // Utility methods
    formatNumber(num) {
        if (num >= 1000000) {
            return (num / 1000000).toFixed(1) + 'M';
        } else if (num >= 1000) {
            return (num / 1000).toFixed(1) + 'K';
        }
        return num.toString();
    }

    getTimeAgo(date) {
        const now = new Date();
        const diff = now - date;
        const minutes = Math.floor(diff / 60000);
        const hours = Math.floor(diff / 3600000);
        const days = Math.floor(diff / 86400000);

        if (days > 0) return `${days}d ago`;
        if (hours > 0) return `${hours}h ago`;
        if (minutes > 0) return `${minutes}m ago`;
        return 'Just now';
    }

    hideLoading(elementId) {
        const element = document.getElementById(elementId);
        if (element) {
            element.style.display = 'none';
        }
    }

    deepMerge(target, source) {
        for (const key in source) {
            if (source[key] && typeof source[key] === 'object' && !Array.isArray(source[key])) {
                target[key] = target[key] || {};
                this.deepMerge(target[key], source[key]);
            } else {
                target[key] = source[key];
            }
        }
        return target;
    }

    // Public methods for filter updates
    updatePeriod(period) {
        this.currentPeriod = period;
        this.refreshAllData();
    }

    updatePlatform(platform) {
        this.currentPlatform = platform;
        this.refreshAllData();
    }

    refreshAllData() {
        // Show loading states
        ['engagementLoading', 'platformLoading', 'heatmapLoading'].forEach(id => {
            const element = document.getElementById(id);
            if (element) element.style.display = 'flex';
        });

        // Reload all data
        this.loadEngagementData();
        this.loadPlatformData();
        this.loadTopPosts();
        this.loadActivityFeed();
        this.loadHashtagCloud();
        this.loadPostingTimesHeatmap();
    }

    loadChartData(chartType) {
        switch(chartType) {
            case 'platforms':
                this.loadPlatformData();
                break;
            case 'posting-times':
                this.loadPostingTimesHeatmap();
                break;
            case 'hashtags':
                this.loadHashtagCloud();
                break;
            case 'engagement':
                this.loadEngagementData();
                break;
        }
    }
}

// Export for use in other modules
window.AnalyticsCharts = AnalyticsCharts;