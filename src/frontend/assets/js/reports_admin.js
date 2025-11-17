/**
 * Admin Reports Manager
 * Handles all reporting functionality for administrators
 */

class AdminReportsManager {
    constructor() {
        this.charts = {};
        this.data = {};
        this.refreshInterval = null;
        this.init();
    }

    async init() {
        try {
            await this.loadUserData();
            await this.loadReportsData();
            this.initializeCharts();
            this.setupEventListeners();
            this.startAutoRefresh();
            console.log('Admin Reports Manager initialized successfully');
        } catch (error) {
            console.error('Failed to initialize Admin Reports Manager:', error);
            this.showError('Failed to load reports data');
        }
    }

    async loadUserData() {
        try {
            const userData = localStorage.getItem('user');
            if (userData) {
                this.currentUser = JSON.parse(userData);
                this.updateUserDisplay();
                return;
            }

            // Fallback to API
            const response = await fetch('../backend/api/dashboard.php');
            const data = await response.json();
            
            if (data.success && data.user) {
                this.currentUser = data.user;
                this.updateUserDisplay();
            }
        } catch (error) {
            console.error('Failed to load user data:', error);
        }
    }

    updateUserDisplay() {
        if (this.currentUser) {
            const nameElement = document.getElementById('headerUserName');
            const roleElement = document.getElementById('headerUserRole');
            const avatarElement = document.getElementById('headerUserAvatar');

            if (nameElement) nameElement.textContent = this.currentUser.full_name || this.currentUser.username || 'Administrator';
            if (roleElement) roleElement.textContent = this.currentUser.role || 'Administrator';
            if (avatarElement) avatarElement.textContent = (this.currentUser.full_name || this.currentUser.username || 'A')[0].toUpperCase();
        }
    }

    async loadReportsData() {
        try {
            // Simulate API calls - replace with actual endpoints
            this.data = {
                revenue: await this.fetchRevenueData(),
                users: await this.fetchUsersData(),
                customers: await this.fetchCustomersData(),
                activity: await this.fetchActivityData(),
                systemHealth: await this.fetchSystemHealth()
            };

            this.updateMetricsDisplay();
            this.loadRecentActivity();
        } catch (error) {
            console.error('Failed to load reports data:', error);
            this.loadSampleData();
        }
    }

    async fetchRevenueData() {
        // Simulate API call - replace with actual endpoint
        return {
            total: 125000,
            change: 15.2,
            trend: 'up',
            chartData: {
                labels: ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun'],
                datasets: [{
                    label: 'Revenue',
                    data: [15000, 22000, 18000, 25000, 28000, 32000],
                    borderColor: 'rgb(102, 126, 234)',
                    backgroundColor: 'rgba(102, 126, 234, 0.1)',
                    tension: 0.4
                }]
            }
        };
    }

    async fetchUsersData() {
        // Simulate API call
        return {
            total: 24,
            active: 18,
            change: 8.3,
            trend: 'up',
            breakdown: {
                admin: 3,
                pharmacist: 8,
                cashier: 13
            }
        };
    }

    async fetchCustomersData() {
        // Simulate API call
        return {
            total: 1250,
            change: 12.5,
            trend: 'up',
            newThisMonth: 45
        };
    }

    async fetchActivityData() {
        // Simulate API call
        return [
            { time: '14:30', user: 'John Doe', action: 'User Login', status: 'success', details: 'Admin panel access' },
            { time: '14:25', user: 'Jane Smith', action: 'Customer Added', status: 'success', details: 'New customer registration' },
            { time: '14:20', user: 'Mike Johnson', action: 'Sales Transaction', status: 'success', details: 'KSh 125.50 sale completed' },
            { time: '14:15', user: 'Sarah Wilson', action: 'Inventory Update', status: 'success', details: 'Medicine stock updated' },
            { time: '14:10', user: 'System', action: 'Backup Created', status: 'success', details: 'Daily backup completed' }
        ];
    }

    async fetchSystemHealth() {
        // Simulate system health check
        return {
            status: 'healthy',
            uptime: '99.8%',
            issues: 0
        };
    }

    loadSampleData() {
        // Fallback sample data for demo purposes
        this.data = {
            revenue: {
                total: 125000,
                change: 15.2,
                trend: 'up'
            },
            users: {
                total: 24,
                active: 18,
                change: 8.3,
                trend: 'up'
            },
            customers: {
                total: 1250,
                change: 12.5,
                trend: 'up'
            },
            systemHealth: {
                status: 'healthy',
                uptime: '99.8%'
            }
        };

        this.updateMetricsDisplay();
    }

    updateMetricsDisplay() {
        // Update revenue
        const revenueElement = document.getElementById('totalRevenue');
        const revenueChangeElement = document.getElementById('revenueChange');
        if (revenueElement && this.data.revenue) {
            revenueElement.textContent = `KSh ${this.data.revenue.total.toLocaleString()}`;
            if (revenueChangeElement) {
                const change = this.data.revenue.change || 0;
                const icon = change >= 0 ? 'fa-arrow-up' : 'fa-arrow-down';
                const colorClass = change >= 0 ? 'change-positive' : 'change-negative';
                revenueChangeElement.className = `report-card-change ${colorClass}`;
                revenueChangeElement.innerHTML = `<i class="fas ${icon}"></i><span>+${change}% vs last month</span>`;
            }
        }

        // Update users
        const usersElement = document.getElementById('activeUsers');
        const usersChangeElement = document.getElementById('usersChange');
        if (usersElement && this.data.users) {
            usersElement.textContent = this.data.users.active || this.data.users.total || 0;
            if (usersChangeElement) {
                const change = this.data.users.change || 0;
                const icon = change >= 0 ? 'fa-arrow-up' : 'fa-arrow-down';
                const colorClass = change >= 0 ? 'change-positive' : 'change-negative';
                usersChangeElement.className = `report-card-change ${colorClass}`;
                usersChangeElement.innerHTML = `<i class="fas ${icon}"></i><span>+${change}% active users</span>`;
            }
        }

        // Update customers
        const customersElement = document.getElementById('totalCustomers');
        const customersChangeElement = document.getElementById('customersChange');
        if (customersElement && this.data.customers) {
            customersElement.textContent = this.data.customers.total || 0;
            if (customersChangeElement) {
                const change = this.data.customers.change || 0;
                const icon = change >= 0 ? 'fa-arrow-up' : 'fa-arrow-down';
                const colorClass = change >= 0 ? 'change-positive' : 'change-negative';
                customersChangeElement.className = `report-card-change ${colorClass}`;
                customersChangeElement.innerHTML = `<i class="fas ${icon}"></i><span>+${change}% new customers</span>`;
            }
        }

        // Update system health
        const healthElement = document.getElementById('systemHealth');
        const healthChangeElement = document.getElementById('healthChange');
        if (healthElement && this.data.systemHealth) {
            healthElement.textContent = this.data.systemHealth.uptime || '100%';
            if (healthChangeElement) {
                healthChangeElement.innerHTML = '<i class="fas fa-check-circle"></i><span>All Systems Operational</span>';
            }
        }
    }

    initializeCharts() {
        this.initRevenueChart();
        this.initActivityChart();
    }

    initRevenueChart() {
        const ctx = document.getElementById('revenueChart');
        if (!ctx) return;

        this.charts.revenue = new Chart(ctx, {
            type: 'line',
            data: {
                labels: ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun'],
                datasets: [{
                    label: 'Revenue (KSh)',
                    data: [15000, 22000, 18000, 25000, 28000, 32000],
                    borderColor: 'rgb(102, 126, 234)',
                    backgroundColor: 'rgba(102, 126, 234, 0.1)',
                    tension: 0.4,
                    fill: true
                }, {
                    label: 'Profit (KSh)',
                    data: [8000, 12000, 9000, 15000, 18000, 22000],
                    borderColor: 'rgb(240, 147, 251)',
                    backgroundColor: 'rgba(240, 147, 251, 0.1)',
                    tension: 0.4,
                    fill: true
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'top',
                    },
                    title: {
                        display: false
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            callback: function(value) {
                                return 'KSh ' + value.toLocaleString();
                            }
                        }
                    }
                }
            }
        });
    }

    initActivityChart() {
        const ctx = document.getElementById('activityChart');
        if (!ctx) return;

        this.charts.activity = new Chart(ctx, {
            type: 'doughnut',
            data: {
                labels: ['Admins', 'Pharmacists', 'Cashiers'],
                datasets: [{
                    data: [3, 8, 13],
                    backgroundColor: [
                        'rgba(102, 126, 234, 0.8)',
                        'rgba(240, 147, 251, 0.8)',
                        'rgba(79, 172, 254, 0.8)'
                    ],
                    borderColor: [
                        'rgb(102, 126, 234)',
                        'rgb(240, 147, 251)',
                        'rgb(79, 172, 254)'
                    ],
                    borderWidth: 2
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom',
                    }
                }
            }
        });
    }

    setupEventListeners() {
        // Time range filter
        const timeRangeSelect = document.getElementById('timeRange');
        if (timeRangeSelect) {
            timeRangeSelect.addEventListener('change', this.handleTimeRangeChange.bind(this));
        }

        // Chart type buttons
        document.querySelectorAll('.chart-btn').forEach(btn => {
            btn.addEventListener('click', this.handleChartTypeChange.bind(this));
        });
    }

    handleTimeRangeChange(event) {
        const value = event.target.value;
        const startDate = document.getElementById('startDate');
        const endDate = document.getElementById('endDate');

        if (value === 'custom') {
            if (startDate) startDate.style.display = 'block';
            if (endDate) endDate.style.display = 'block';
        } else {
            if (startDate) startDate.style.display = 'none';
            if (endDate) endDate.style.display = 'none';
        }

        // Reload data for selected time range
        this.loadReportsData();
    }

    handleChartTypeChange(event) {
        const btn = event.target;
        const chartType = btn.dataset.chart;
        
        // Update active state
        btn.parentElement.querySelectorAll('.chart-btn').forEach(b => b.classList.remove('active'));
        btn.classList.add('active');

        // Update chart based on type
        // Implementation would depend on specific chart requirements
        console.log('Chart type changed to:', chartType);
    }

    async loadRecentActivity() {
        try {
            const activityData = await this.fetchActivityData();
            const tbody = document.querySelector('#activityTable tbody');
            
            if (tbody && activityData) {
                tbody.innerHTML = '';
                activityData.forEach(activity => {
                    const row = document.createElement('tr');
                    row.innerHTML = `
                        <td>${activity.time}</td>
                        <td>${activity.user}</td>
                        <td>${activity.action}</td>
                        <td><span class="status-badge status-${activity.status}">${activity.status}</span></td>
                        <td>${activity.details}</td>
                    `;
                    tbody.appendChild(row);
                });
            }
        } catch (error) {
            console.error('Failed to load recent activity:', error);
        }
    }

    startAutoRefresh() {
        // Refresh data every 5 minutes
        this.refreshInterval = setInterval(() => {
            this.refreshData();
        }, 5 * 60 * 1000);
    }

    async refreshData() {
        try {
            await this.loadReportsData();
            this.showSuccess('Data refreshed successfully');
        } catch (error) {
            console.error('Failed to refresh data:', error);
            this.showError('Failed to refresh data');
        }
    }

    exportReport(format) {
        // Simulate export functionality
        this.showSuccess(`Exporting report as ${format.toUpperCase()}...`);
        
        // In a real implementation, this would:
        // 1. Collect all current report data
        // 2. Format it according to the requested format
        // 3. Trigger download or send to server for processing
        
        setTimeout(() => {
            this.showSuccess(`Report exported successfully as ${format.toUpperCase()}`);
        }, 2000);
    }

    scheduleReport() {
        // Simulate report scheduling
        this.showSuccess('Report scheduling feature coming soon!');
        
        // In a real implementation, this would open a modal to configure:
        // - Frequency (daily, weekly, monthly)
        // - Recipients
        // - Report types
        // - Delivery method
    }

    showSuccess(message) {
        // You could integrate with a toast notification system here
        console.log('Success:', message);
        alert(message); // Temporary - replace with proper notification
    }

    showError(message) {
        // You could integrate with a toast notification system here
        console.error('Error:', message);
        alert('Error: ' + message); // Temporary - replace with proper notification
    }

    destroy() {
        // Cleanup
        if (this.refreshInterval) {
            clearInterval(this.refreshInterval);
        }
        
        Object.values(this.charts).forEach(chart => {
            if (chart) chart.destroy();
        });
    }
}

// Global functions for button clicks
window.exportReport = function(format) {
    if (window.reportsManager) {
        window.reportsManager.exportReport(format);
    }
};

window.scheduleReport = function() {
    if (window.reportsManager) {
        window.reportsManager.scheduleReport();
    }
};

window.refreshData = function() {
    if (window.reportsManager) {
        window.reportsManager.refreshData();
    }
};

// Initialize when DOM is loaded
document.addEventListener('DOMContentLoaded', () => {
    window.reportsManager = new AdminReportsManager();
});

// Cleanup on page unload
window.addEventListener('beforeunload', () => {
    if (window.reportsManager) {
        window.reportsManager.destroy();
    }
});
