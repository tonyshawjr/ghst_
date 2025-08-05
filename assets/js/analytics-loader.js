/**
 * Analytics Loader - Progressive Enhancement Script
 * Lazy loads analytics modules only when needed for optimal performance
 */

class AnalyticsLoader {
    constructor() {
        this.scriptsLoaded = false;
        this.chartsInitialized = false;
        this.loadPromise = null;
        
        this.init();
    }
    
    init() {
        // Use Intersection Observer to detect when charts become visible
        this.setupIntersectionObserver();
        
        // Listen for user interactions that might need charts
        this.setupInteractionListeners();
        
        // Preload scripts if user is likely to interact (hover, focus)
        this.setupPreloadTriggers();
    }
    
    setupIntersectionObserver() {
        if (!('IntersectionObserver' in window)) {
            // Fallback for older browsers
            this.loadAnalyticsScripts();
            return;
        }
        
        const observer = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    this.loadAnalyticsScripts();
                    observer.disconnect(); // Only load once
                }
            });
        }, {
            threshold: 0.1,
            rootMargin: '50px 0px' // Start loading before fully visible
        });
        
        // Observe key analytics containers
        const targets = [
            '#metricsCards',
            '#engagementChart', 
            '.mobile-chart',
            '[data-metric]'
        ];
        
        targets.forEach(selector => {
            const element = document.querySelector(selector);
            if (element) {
                observer.observe(element);
            }
        });
    }
    
    setupInteractionListeners() {
        // Load when user interacts with filters
        const filterElements = document.querySelectorAll('#periodFilter, #platformFilter, .chart-tab');
        filterElements.forEach(element => {
            element.addEventListener('mouseenter', () => this.preloadScripts(), { once: true });
            element.addEventListener('focus', () => this.preloadScripts(), { once: true });
        });
    }
    
    setupPreloadTriggers() {
        // Preload on any user interaction with the page
        const preloadEvents = ['mouseover', 'touchstart', 'keydown'];
        preloadEvents.forEach(event => {
            document.addEventListener(event, () => this.preloadScripts(), { 
                once: true, 
                passive: true 
            });
        });
        
        // Preload after page has been idle for a moment
        if ('requestIdleCallback' in window) {
            requestIdleCallback(() => {
                setTimeout(() => this.preloadScripts(), 2000);
            });
        }
    }
    
    preloadScripts() {
        if (this.loadPromise) return this.loadPromise;
        
        // Start preloading but don't initialize yet
        this.loadPromise = this.loadScripts();
        return this.loadPromise;
    }
    
    async loadAnalyticsScripts() {
        if (this.chartsInitialized) return;
        
        try {
            // Show loading indicators
            this.showLoadingStates();
            
            // Load scripts if not already loading
            if (!this.loadPromise) {
                this.loadPromise = this.loadScripts();
            }
            
            await this.loadPromise;
            
            // Initialize analytics
            this.initializeAnalytics();
            
        } catch (error) {
            console.error('Error loading analytics:', error);
            this.showErrorStates();
        }
    }
    
    async loadScripts() {
        if (this.scriptsLoaded) return;
        
        const scripts = [
            'https://cdn.jsdelivr.net/npm/chart.js',
            'https://cdn.jsdelivr.net/npm/chartjs-adapter-date-fns/dist/chartjs-adapter-date-fns.bundle.min.js',
            '/assets/js/analytics-charts.js',
            '/assets/js/mobile-analytics.js'
        ];
        
        // Load Chart.js first
        await this.loadScript(scripts[0]);
        await this.loadScript(scripts[1]);
        
        // Configure Chart.js defaults
        if (window.Chart) {
            Chart.defaults.color = '#9CA3AF';
            Chart.defaults.borderColor = '#374151';
            Chart.defaults.backgroundColor = 'rgba(139, 92, 246, 0.1)';
        }
        
        // Load our analytics modules
        await Promise.all([
            this.loadScript(scripts[2]),
            this.loadScript(scripts[3])
        ]);
        
        this.scriptsLoaded = true;
    }
    
    loadScript(src) {
        return new Promise((resolve, reject) => {
            // Check if already loaded
            if (document.querySelector(`script[src="${src}"]`)) {
                resolve();
                return;
            }
            
            const script = document.createElement('script');
            script.src = src;
            script.onload = resolve;
            script.onerror = () => reject(new Error(`Failed to load ${src}`));
            
            // Add to head for faster loading
            document.head.appendChild(script);
        });
    }
    
    initializeAnalytics() {
        if (this.chartsInitialized || !window.AnalyticsCharts) return;
        
        try {
            // Initialize main analytics
            window.analyticsCharts = new window.AnalyticsCharts();
            
            // Set current filters from URL params
            const urlParams = new URLSearchParams(window.location.search);
            window.analyticsCharts.currentPeriod = urlParams.get('period') || '30d';
            window.analyticsCharts.currentPlatform = urlParams.get('platform') || 'all';
            
            // Initialize charts with staggered loading for better UX
            this.staggeredChartInit();
            
            this.chartsInitialized = true;
            this.hideLoadingStates();
            
        } catch (error) {
            console.error('Error initializing analytics:', error);
            this.showErrorStates();
        }
    }
    
    async staggeredChartInit() {
        const initSteps = [
            () => window.analyticsCharts.initEngagementChart(),
            () => window.analyticsCharts.initPlatformCharts(),
            () => window.analyticsCharts.loadTopPosts(),
            () => window.analyticsCharts.initDemographicCharts(),
            () => window.analyticsCharts.loadActivityFeed(),
            () => window.analyticsCharts.loadHashtagCloud(),
            () => window.analyticsCharts.loadPostingTimesHeatmap()
        ];
        
        // Initialize with small delays for smooth experience
        for (let i = 0; i < initSteps.length; i++) {
            try {
                await initSteps[i]();
                if (i < initSteps.length - 1) {
                    await new Promise(resolve => setTimeout(resolve, 100));
                }
            } catch (error) {
                console.warn(`Error in chart init step ${i}:`, error);
            }
        }
        
        // Show first mobile chart
        if (window.mobileAnalytics) {
            window.mobileAnalytics.showChart('engagement');
        }
    }
    
    showLoadingStates() {
        const loadingElements = document.querySelectorAll('[id$="Loading"]');
        loadingElements.forEach(el => {
            if (el) el.style.display = 'flex';
        });
        
        // Add loading class to metric cards
        const metricCards = document.querySelectorAll('[data-metric]');
        metricCards.forEach(card => {
            card.classList.add('animate-pulse');
        });
    }
    
    hideLoadingStates() {
        const loadingElements = document.querySelectorAll('[id$="Loading"]');
        loadingElements.forEach(el => {
            if (el) el.style.display = 'none';
        });
        
        // Remove loading class from metric cards
        const metricCards = document.querySelectorAll('[data-metric]');
        metricCards.forEach(card => {
            card.classList.remove('animate-pulse');
        });
    }
    
    showErrorStates() {
        const loadingElements = document.querySelectorAll('[id$="Loading"]');
        loadingElements.forEach(el => {
            if (el) {
                el.style.display = 'flex';
                el.innerHTML = `
                    <div class="text-red-500 text-center">
                        <svg class="w-8 h-8 mx-auto mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.664-.833-2.464 0L3.34 16.5c-.77.833.192 2.5 1.732 2.5z"></path>
                        </svg>
                        <p class="text-sm">Error loading charts</p>
                        <button onclick="location.reload()" class="text-xs text-purple-400 hover:text-purple-300 mt-1">
                            Refresh page
                        </button>
                    </div>
                `;
            }
        });
    }
}

// Global functions for backward compatibility
window.analyticsLoader = {
    showMobileChart: (chartType) => {
        if (window.mobileAnalytics) {
            window.mobileAnalytics.showChart(chartType);
        }
    },
    
    updateFilters: function() {
        const periodSelect = document.getElementById('periodFilter');
        const platformSelect = document.getElementById('platformFilter');
        
        if (periodSelect?.value === 'custom') {
            const alpineComponent = document.querySelector('[x-data]');
            if (alpineComponent && alpineComponent.__x) {
                alpineComponent.__x.$data.showDatePicker = true;
            }
            return;
        }
        
        const currentPeriod = periodSelect?.value || '30d';
        const currentPlatform = platformSelect?.value || 'all';
        
        const url = new URL(window.location);
        url.searchParams.set('period', currentPeriod);
        url.searchParams.set('platform', currentPlatform);
        window.history.pushState({}, '', url);
        
        if (window.analyticsCharts) {
            window.analyticsCharts.updatePeriod(currentPeriod);
            window.analyticsCharts.updatePlatform(currentPlatform);
        }
    },
    
    applyCustomRange: function() {
        const dateFrom = document.getElementById('dateFrom')?.value;
        const dateTo = document.getElementById('dateTo')?.value;
        
        if (dateFrom && dateTo) {
            const currentPeriod = `${dateFrom}:${dateTo}`;
            const alpineComponent = document.querySelector('[x-data]');
            if (alpineComponent && alpineComponent.__x) {
                alpineComponent.__x.$data.showDatePicker = false;
            }
            
            const url = new URL(window.location);
            url.searchParams.set('period', currentPeriod);
            window.history.pushState({}, '', url);
            
            if (window.analyticsCharts) {
                window.analyticsCharts.updatePeriod(currentPeriod);
            }
        }
    },
    
    refreshData: function() {
        if (window.analyticsCharts) {
            window.analyticsCharts.refreshAllData();
        }
    }
};

// Make functions globally available for HTML onclick handlers
window.showMobileChart = window.analyticsLoader.showMobileChart;
window.updateFilters = window.analyticsLoader.updateFilters;
window.applyCustomRange = window.analyticsLoader.applyCustomRange;
window.refreshData = window.analyticsLoader.refreshData;

// Initialize loader when DOM is ready
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => {
        new AnalyticsLoader();
    });
} else {
    new AnalyticsLoader();
}