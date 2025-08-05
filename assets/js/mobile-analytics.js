/**
 * Mobile Analytics Enhancements
 * Handles mobile-specific interactions, gestures, and responsive features
 */

class MobileAnalytics {
    constructor() {
        this.isTouch = ('ontouchstart' in window) || (navigator.maxTouchPoints > 0);
        this.currentSlide = 0;
        this.chartSlides = ['engagement', 'platforms', 'posting-times', 'hashtags'];
        this.swipeThreshold = 50;
        this.touchStartX = 0;
        this.touchStartY = 0;
        this.pullToRefreshThreshold = 100;
        this.pullDistance = 0;
        this.isRefreshing = false;
        
        this.init();
    }

    init() {
        if (this.isTouch) {
            this.setupTouchInteractions();
            this.setupPullToRefresh();
            this.setupHapticFeedback();
            this.setupResponsiveCharts();
        }
        
        this.setupKeyboardNavigation();
        this.setupIntersectionObserver();
    }

    // Setup touch interactions
    setupTouchInteractions() {
        let touchStartTime = 0;
        let touchStartTarget = null;

        document.addEventListener('touchstart', (e) => {
            this.touchStartX = e.touches[0].clientX;
            this.touchStartY = e.touches[0].clientY;
            touchStartTime = Date.now();
            touchStartTarget = e.target;
        }, { passive: true });

        document.addEventListener('touchmove', (e) => {
            this.handleTouchMove(e);
        }, { passive: false });

        document.addEventListener('touchend', (e) => {
            this.handleTouchEnd(e, touchStartTime, touchStartTarget);
        }, { passive: true });

        // Long press for additional actions
        this.setupLongPress();
    }

    // Handle touch move events
    handleTouchMove(e) {
        const touchX = e.touches[0].clientX;
        const touchY = e.touches[0].clientY;
        const deltaX = touchX - this.touchStartX;
        const deltaY = touchY - this.touchStartY;

        // Check if we're swiping horizontally on charts
        if (this.isOnChart(e.target) && Math.abs(deltaX) > Math.abs(deltaY)) {
            e.preventDefault(); // Prevent scrolling
        }

        // Handle pull to refresh
        if (window.scrollY === 0 && deltaY > 0) {
            this.handlePullToRefresh(deltaY);
        }
    }

    // Handle touch end events
    handleTouchEnd(e, startTime, startTarget) {
        const touchEndX = e.changedTouches[0].clientX;
        const touchEndY = e.changedTouches[0].clientY;
        const deltaX = touchEndX - this.touchStartX;
        const deltaY = touchEndY - this.touchStartY;
        const touchDuration = Date.now() - startTime;

        // Handle swipe gestures
        if (Math.abs(deltaX) > this.swipeThreshold && Math.abs(deltaX) > Math.abs(deltaY)) {
            if (this.isOnChart(startTarget)) {
                this.handleChartSwipe(deltaX);
            }
        }

        // Handle tap gestures
        if (Math.abs(deltaX) < 10 && Math.abs(deltaY) < 10 && touchDuration < 300) {
            this.handleTap(startTarget);
        }

        // Reset pull to refresh
        this.resetPullToRefresh();
    }

    // Check if touch is on a chart area
    isOnChart(element) {
        return element.closest('.mobile-chart') || element.closest('canvas');
    }

    // Handle chart swiping
    handleChartSwipe(deltaX) {
        if (deltaX > 0) {
            // Swipe right - previous chart
            this.previousChart();
        } else {
            // Swipe left - next chart
            this.nextChart();
        }
    }

    // Navigate to next chart
    nextChart() {
        this.currentSlide = (this.currentSlide + 1) % this.chartSlides.length;
        this.showChart(this.chartSlides[this.currentSlide]);
        this.triggerHaptic('medium');
    }

    // Navigate to previous chart
    previousChart() {
        this.currentSlide = (this.currentSlide - 1 + this.chartSlides.length) % this.chartSlides.length;
        this.showChart(this.chartSlides[this.currentSlide]);
        this.triggerHaptic('medium');
    }

    // Show specific chart
    showChart(chartType) {
        // Update active tab
        document.querySelectorAll('.chart-tab').forEach((tab, index) => {
            tab.classList.toggle('active', index === this.currentSlide);
            tab.classList.toggle('bg-purple-600', index === this.currentSlide);
            tab.classList.toggle('text-white', index === this.currentSlide);
            tab.classList.toggle('bg-gray-800', index !== this.currentSlide);
            tab.classList.toggle('text-gray-300', index !== this.currentSlide);
        });

        // Show/hide chart containers
        document.querySelectorAll('.mobile-chart').forEach(chart => {
            chart.style.display = 'none';
        });

        const targetChart = document.getElementById(`chart-${chartType}`);
        if (targetChart) {
            targetChart.style.display = 'block';
            
            // Add slide animation
            targetChart.style.transform = 'translateX(100%)';
            targetChart.style.opacity = '0';
            
            requestAnimationFrame(() => {
                targetChart.style.transition = 'transform 0.3s ease-out, opacity 0.3s ease-out';
                targetChart.style.transform = 'translateX(0)';
                targetChart.style.opacity = '1';
            });
        }

        // Trigger chart data load if needed
        if (window.analyticsCharts) {
            window.analyticsCharts.loadChartData(chartType);
        }
    }

    // Handle tap gestures
    handleTap(target) {
        // Add ripple effect to touchable elements
        if (target.classList.contains('touch-target') || target.closest('.touch-target')) {
            this.createRippleEffect(target);
        }

        // Handle metric card taps
        if (target.closest('[data-metric]')) {
            this.handleMetricCardTap(target.closest('[data-metric]'));
        }
    }

    // Create ripple effect on tap
    createRippleEffect(element) {
        const ripple = document.createElement('div');
        ripple.className = 'ripple-effect';
        
        const rect = element.getBoundingClientRect();
        const size = Math.max(rect.width, rect.height);
        const x = this.touchStartX - rect.left - size / 2;
        const y = this.touchStartY - rect.top - size / 2;
        
        ripple.style.cssText = `
            position: absolute;
            border-radius: 50%;
            background: rgba(139, 92, 246, 0.3);
            transform: scale(0);
            animation: ripple 0.6s linear;
            left: ${x}px;
            top: ${y}px;
            width: ${size}px;
            height: ${size}px;
            pointer-events: none;
            z-index: 1000;
        `;
        
        element.style.position = 'relative';
        element.appendChild(ripple);
        
        setTimeout(() => {
            ripple.remove();
        }, 600);
    }

    // Handle metric card taps
    handleMetricCardTap(card) {
        const metric = card.dataset.metric;
        
        // Show detailed breakdown modal or navigate to detailed view
        this.showMetricDetails(metric);
        this.triggerHaptic('light');
    }

    // Show metric details
    showMetricDetails(metric) {
        // Create modal or slide-in panel with detailed metrics
        const modal = this.createMetricModal(metric);
        document.body.appendChild(modal);
        
        // Animate in
        requestAnimationFrame(() => {
            modal.classList.add('active');
        });
    }

    // Create metric detail modal
    createMetricModal(metric) {
        const modal = document.createElement('div');
        modal.className = 'fixed inset-0 bg-black bg-opacity-75 flex items-end lg:items-center justify-center z-50 metric-modal';
        
        modal.innerHTML = `
            <div class="bg-gray-900 w-full lg:w-96 lg:rounded-lg border-t lg:border border-gray-800 transform translate-y-full lg:translate-y-0 lg:scale-95 transition-transform duration-300">
                <div class="p-6">
                    <div class="flex items-center justify-between mb-4">
                        <h3 class="text-lg font-semibold capitalize">${metric.replace('_', ' ')} Details</h3>
                        <button class="close-modal text-gray-400 hover:text-white">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                            </svg>
                        </button>
                    </div>
                    
                    <div class="space-y-4">
                        <div class="p-4 bg-gray-800 rounded-lg">
                            <p class="text-sm text-gray-400 mb-1">Current Value</p>
                            <p class="text-2xl font-bold" id="modal-current-${metric}">Loading...</p>
                        </div>
                        
                        <div class="grid grid-cols-2 gap-4">
                            <div class="p-4 bg-gray-800 rounded-lg">
                                <p class="text-sm text-gray-400 mb-1">Change</p>
                                <p class="text-lg font-medium" id="modal-change-${metric}">Loading...</p>
                            </div>
                            <div class="p-4 bg-gray-800 rounded-lg">
                                <p class="text-sm text-gray-400 mb-1">Trend</p>
                                <p class="text-lg font-medium" id="modal-trend-${metric}">Loading...</p>
                            </div>
                        </div>
                        
                        <div class="pt-4">
                            <canvas id="modal-chart-${metric}" style="height: 200px;"></canvas>
                        </div>
                    </div>
                </div>
            </div>
        `;
        
        // Add event listeners
        modal.querySelector('.close-modal').addEventListener('click', () => {
            this.closeMetricModal(modal);
        });
        
        modal.addEventListener('click', (e) => {
            if (e.target === modal) {
                this.closeMetricModal(modal);
            }
        });
        
        // Make active class trigger the animation
        setTimeout(() => {
            modal.classList.add('active');
        }, 10);
        
        return modal;
    }

    // Close metric modal
    closeMetricModal(modal) {
        modal.classList.remove('active');
        setTimeout(() => {
            modal.remove();
        }, 300);
    }

    // Setup pull to refresh
    setupPullToRefresh() {
        const refreshIndicator = document.createElement('div');
        refreshIndicator.className = 'fixed top-0 left-0 right-0 h-16 bg-gray-900 border-b border-gray-800 flex items-center justify-center transform -translate-y-full transition-transform duration-300 z-40';
        refreshIndicator.innerHTML = `
            <div class="flex items-center space-x-2 text-purple-400">
                <svg class="w-5 h-5 animate-spin" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path>
                </svg>
                <span class="text-sm font-medium">Refreshing...</span>
            </div>
        `;
        
        document.body.appendChild(refreshIndicator);
        this.refreshIndicator = refreshIndicator;
    }

    // Handle pull to refresh
    handlePullToRefresh(deltaY) {
        if (this.isRefreshing) return;
        
        this.pullDistance = Math.min(deltaY, this.pullToRefreshThreshold * 1.5);
        const progress = this.pullDistance / this.pullToRefreshThreshold;
        
        if (progress > 1) {
            this.triggerRefresh();
        } else {
            // Update pull indicator
            this.refreshIndicator.style.transform = `translateY(${-100 + (progress * 100)}%)`;
        }
    }

    // Trigger refresh
    triggerRefresh() {
        if (this.isRefreshing) return;
        
        this.isRefreshing = true;
        this.refreshIndicator.style.transform = 'translateY(0)';
        this.triggerHaptic('success');
        
        // Refresh data
        if (window.analyticsCharts) {
            window.analyticsCharts.refreshAllData();
        }
        
        // Hide indicator after delay
        setTimeout(() => {
            this.resetPullToRefresh();
        }, 2000);
    }

    // Reset pull to refresh
    resetPullToRefresh() {
        this.pullDistance = 0;
        this.isRefreshing = false;
        this.refreshIndicator.style.transform = 'translateY(-100%)';
    }

    // Setup haptic feedback
    setupHapticFeedback() {
        this.hapticPatterns = {
            light: 10,
            medium: 20,
            heavy: 50,
            success: [10, 30, 10, 30, 10],
            error: [50, 100, 50],
            notification: [20, 50, 20]
        };
    }

    // Trigger haptic feedback
    triggerHaptic(type = 'light') {
        if (!('vibrate' in navigator)) return;
        
        const pattern = this.hapticPatterns[type] || type;
        navigator.vibrate(pattern);
    }

    // Setup long press
    setupLongPress() {
        let longPressTimer;
        let isLongPress = false;
        
        document.addEventListener('touchstart', (e) => {
            isLongPress = false;
            longPressTimer = setTimeout(() => {
                isLongPress = true;
                this.handleLongPress(e.target);
            }, 500);
        });
        
        document.addEventListener('touchend', () => {
            clearTimeout(longPressTimer);
        });
        
        document.addEventListener('touchmove', () => {
            clearTimeout(longPressTimer);
        });
    }

    // Handle long press
    handleLongPress(target) {
        this.triggerHaptic('heavy');
        
        // Show context menu for charts
        if (target.closest('canvas')) {
            this.showChartContextMenu(target);
        }
        
        // Show options for metric cards
        if (target.closest('[data-metric]')) {
            this.showMetricContextMenu(target.closest('[data-metric]'));
        }
    }

    // Show chart context menu
    showChartContextMenu(chartElement) {
        const menu = document.createElement('div');
        menu.className = 'fixed bg-gray-800 rounded-lg shadow-lg border border-gray-700 p-2 z-50 context-menu';
        
        menu.innerHTML = `
            <button class="block w-full text-left px-4 py-2 hover:bg-gray-700 rounded text-sm">
                Share Chart
            </button>
            <button class="block w-full text-left px-4 py-2 hover:bg-gray-700 rounded text-sm">
                Export Data
            </button>
            <button class="block w-full text-left px-4 py-2 hover:bg-gray-700 rounded text-sm">
                Full Screen
            </button>
        `;
        
        // Position menu
        const rect = chartElement.getBoundingClientRect();
        menu.style.left = `${this.touchStartX}px`;
        menu.style.top = `${this.touchStartY}px`;
        
        document.body.appendChild(menu);
        
        // Close on outside click
        setTimeout(() => {
            document.addEventListener('click', () => {
                menu.remove();
            }, { once: true });
        }, 10);
    }

    // Setup responsive charts
    setupResponsiveCharts() {
        // Handle orientation change
        window.addEventListener('orientationchange', () => {
            setTimeout(() => {
                if (window.analyticsCharts) {
                    Object.values(window.analyticsCharts.charts).forEach(chart => {
                        if (chart && typeof chart.resize === 'function') {
                            chart.resize();
                        }
                    });
                }
            }, 100);
        });
        
        // Handle resize
        let resizeTimeout;
        window.addEventListener('resize', () => {
            clearTimeout(resizeTimeout);
            resizeTimeout = setTimeout(() => {
                this.handleResize();
            }, 250);
        });
    }

    // Handle resize
    handleResize() {
        // Update chart dimensions
        if (window.analyticsCharts) {
            Object.values(window.analyticsCharts.charts).forEach(chart => {
                if (chart && typeof chart.resize === 'function') {
                    chart.resize();
                }
            });
        }
        
        // Update mobile layout
        this.updateMobileLayout();
    }

    // Update mobile layout
    updateMobileLayout() {
        const isMobile = window.innerWidth < 768;
        
        // Adjust chart heights for mobile
        document.querySelectorAll('canvas').forEach(canvas => {
            if (isMobile) {
                canvas.style.height = '250px';
            } else {
                canvas.style.height = '300px';
            }
        });
    }

    // Setup keyboard navigation
    setupKeyboardNavigation() {
        document.addEventListener('keydown', (e) => {
            if (e.target.matches('input, textarea, select')) return;
            
            switch(e.key) {
                case 'ArrowLeft':
                    e.preventDefault();
                    this.previousChart();
                    break;
                case 'ArrowRight':
                    e.preventDefault();
                    this.nextChart();
                    break;
                case 'r':
                case 'R':
                    if (e.ctrlKey || e.metaKey) {
                        e.preventDefault();
                        this.triggerRefresh();
                    }
                    break;
            }
        });
    }

    // Setup intersection observer for lazy loading
    setupIntersectionObserver() {
        const observer = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    const chartType = entry.target.id.replace('chart-', '');
                    if (window.analyticsCharts) {
                        window.analyticsCharts.loadChartData(chartType);
                    }
                }
            });
        }, {
            threshold: 0.1,
            rootMargin: '50px'
        });
        
        // Observe all chart containers
        document.querySelectorAll('[id^="chart-"]').forEach(chart => {
            observer.observe(chart);
        });
    }
}

// Add CSS for animations
const style = document.createElement('style');
style.textContent = `
    @keyframes ripple {
        to {
            transform: scale(4);
            opacity: 0;
        }
    }
    
    .metric-modal.active .bg-gray-900 {
        transform: translateY(0) !important;
        transform: scale(1) !important;
    }
    
    @media (max-width: 768px) {
        .metric-modal.active .bg-gray-900 {
            transform: translateY(0) !important;
        }
    }
    
    .context-menu {
        animation: contextMenuAppear 0.2s ease-out;
    }
    
    @keyframes contextMenuAppear {
        from {
            opacity: 0;
            transform: scale(0.9);
        }
        to {
            opacity: 1;
            transform: scale(1);
        }
    }
`;
document.head.appendChild(style);

// Initialize mobile analytics
window.addEventListener('DOMContentLoaded', () => {
    window.mobileAnalytics = new MobileAnalytics();
});

// Export for use in other modules
window.MobileAnalytics = MobileAnalytics;