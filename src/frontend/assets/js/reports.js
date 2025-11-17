// Reports & Analytics JavaScript
let reportData = {};
let currentTab = 'sales';
let charts = {};

// Initialize page
document.addEventListener('DOMContentLoaded', function() {
    initializeDateRange();
    loadReports();
    initializeCharts();
});

// Initialize date range to current month
function initializeDateRange() {
    const today = new Date();
    const firstDay = new Date(today.getFullYear(), today.getMonth(), 1);
    const lastDay = new Date(today.getFullYear(), today.getMonth() + 1, 0);
    
    document.getElementById('startDate').value = firstDay.toISOString().split('T')[0];
    document.getElementById('endDate').value = lastDay.toISOString().split('T')[0];
}

// Update date range based on selection
function updateDateRange() {
    const period = document.getElementById('reportPeriod').value;
    const customRange = document.getElementById('customDateRange');
    const startDate = document.getElementById('startDate');
    const endDate = document.getElementById('endDate');
    
    const today = new Date();
    let start, end;
    
    switch (period) {
        case 'today':
            start = end = today;
            break;
        case 'yesterday':
            start = end = new Date(today.getTime() - 24 * 60 * 60 * 1000);
            break;
        case 'this_week':
            start = new Date(today);
            start.setDate(today.getDate() - today.getDay());
            end = new Date(today);
            end.setDate(start.getDate() + 6);
            break;
        case 'last_week':
            start = new Date(today);
            start.setDate(today.getDate() - today.getDay() - 7);
            end = new Date(start);
            end.setDate(start.getDate() + 6);
            break;
        case 'this_month':
            start = new Date(today.getFullYear(), today.getMonth(), 1);
            end = new Date(today.getFullYear(), today.getMonth() + 1, 0);
            break;
        case 'last_month':
            start = new Date(today.getFullYear(), today.getMonth() - 1, 1);
            end = new Date(today.getFullYear(), today.getMonth(), 0);
            break;
        case 'this_year':
            start = new Date(today.getFullYear(), 0, 1);
            end = new Date(today.getFullYear(), 11, 31);
            break;
        case 'custom':
            customRange.style.display = 'block';
            return;
    }
    
    customRange.style.display = 'none';
    
    if (start && end) {
        startDate.value = start.toISOString().split('T')[0];
        endDate.value = end.toISOString().split('T')[0];
        loadReports();
    }
}

// Load all report data
async function loadReports() {
    try {
        showLoading('Loading reports...');
        
        const startDate = document.getElementById('startDate').value;
        const endDate = document.getElementById('endDate').value;
        
        if (!startDate || !endDate) {
            showError('Please select valid date range');
            return;
        }
        
        // Load all report data in parallel
        const [summary, sales, inventory, customers, financial] = await Promise.all([
            loadSummaryData(startDate, endDate),
            loadSalesData(startDate, endDate),
            loadInventoryData(startDate, endDate),
            loadCustomerData(startDate, endDate),
            loadFinancialData(startDate, endDate)
        ]);
        
        reportData = { summary, sales, inventory, customers, financial };
        
        updateSummaryCards();
        updateCurrentTab();
        
    } catch (error) {
        showError('Error loading reports: ' + error.message);
    } finally {
        hideLoading();
    }
}

// Load summary data
async function loadSummaryData(startDate, endDate) {
    const response = await secureApiCall(`/api/reports.php?action=summary&start=${startDate}&end=${endDate}`);
    return response.success ? response.data : {};
}

// Load sales analytics data
async function loadSalesData(startDate, endDate) {
    const response = await secureApiCall(`/api/reports.php?action=sales&start=${startDate}&end=${endDate}`);
    return response.success ? response.data : {};
}

// Load inventory data
async function loadInventoryData(startDate, endDate) {
    const response = await secureApiCall(`/api/reports.php?action=inventory&start=${startDate}&end=${endDate}`);
    return response.success ? response.data : {};
}

// Load customer analytics data
async function loadCustomerData(startDate, endDate) {
    const response = await secureApiCall(`/api/reports.php?action=customers&start=${startDate}&end=${endDate}`);
    return response.success ? response.data : {};
}

// Load financial data
async function loadFinancialData(startDate, endDate) {
    const response = await secureApiCall(`/api/reports.php?action=financial&start=${startDate}&end=${endDate}`);
    return response.success ? response.data : {};
}

// Update summary cards
function updateSummaryCards() {
    const summary = reportData.summary || {};
    
    document.getElementById('totalRevenue').textContent = formatCurrency(summary.total_revenue || 0);
    document.getElementById('totalSales').textContent = (summary.total_sales || 0).toLocaleString();
    document.getElementById('newCustomers').textContent = (summary.new_customers || 0).toLocaleString();
    document.getElementById('lowStockItems').textContent = (summary.low_stock_items || 0).toLocaleString();
    
    // Update change indicators
    updateChangeIndicator('revenueChange', summary.revenue_change || 0);
    updateChangeIndicator('salesChange', summary.sales_change || 0);
    updateChangeIndicator('customersChange', summary.customers_change || 0);
    
    // Update stock status
    const stockStatus = document.getElementById('stockStatus');
    const lowStockCount = summary.low_stock_items || 0;
    if (lowStockCount === 0) {
        stockStatus.textContent = 'Good';
        stockStatus.className = 'status good';
    } else if (lowStockCount < 10) {
        stockStatus.textContent = 'Warning';
        stockStatus.className = 'status warning';
    } else {
        stockStatus.textContent = 'Critical';
        stockStatus.className = 'status critical';
    }
}

// Update change indicator
function updateChangeIndicator(elementId, change) {
    const element = document.getElementById(elementId);
    const isPositive = change >= 0;
    
    element.textContent = (isPositive ? '+' : '') + change.toFixed(1) + '%';
    element.className = isPositive ? 'change positive' : 'change negative';
}

// Switch between report tabs
function switchTab(tabName) {
    // Update active tab button
    document.querySelectorAll('.tab-button').forEach(btn => btn.classList.remove('active'));
    document.querySelector(`[onclick="switchTab('${tabName}')"]`).classList.add('active');
    
    // Update active tab content
    document.querySelectorAll('.tab-content').forEach(content => content.classList.remove('active'));
    document.getElementById(tabName + 'Tab').classList.add('active');
    
    currentTab = tabName;
    updateCurrentTab();
}

// Update current tab with data
function updateCurrentTab() {
    switch (currentTab) {
        case 'sales':
            updateSalesTab();
            break;
        case 'inventory':
            updateInventoryTab();
            break;
        case 'customers':
            updateCustomersTab();
            break;
        case 'financial':
            updateFinancialTab();
            break;
    }
}

// Update sales analytics tab
function updateSalesTab() {
    const sales = reportData.sales || {};
    
    // Update sales trend chart
    updateSalesTrendChart(sales.trends || []);
    
    // Update top medicines chart
    updateTopMedicinesChart(sales.top_medicines || []);
    
    // Update daily sales table
    updateDailySalesTable(sales.daily_summary || []);
}

// Update inventory reports tab
function updateInventoryTab() {
    const inventory = reportData.inventory || {};
    
    // Update stock levels chart
    updateStockLevelsChart(inventory.stock_levels || []);
    
    // Update categories chart
    updateCategoriesChart(inventory.categories || []);
    
    // Update alerts
    updateInventoryAlerts(inventory.alerts || {});
    
    // Update inventory table
    updateInventoryTable(inventory.items || []);
}

// Update customer analytics tab
function updateCustomersTab() {
    const customers = reportData.customers || {};
    
    // Update customer trends chart
    updateCustomerTrendsChart(customers.trends || []);
    
    // Update customer patterns chart
    updateCustomerPatternsChart(customers.patterns || []);
    
    // Update top customers table
    updateTopCustomersTable(customers.top_customers || []);
}

// Update financial reports tab
function updateFinancialTab() {
    const financial = reportData.financial || {};
    
    // Update financial metrics
    document.getElementById('grossRevenue').textContent = formatCurrency(financial.gross_revenue || 0);
    document.getElementById('costOfGoods').textContent = formatCurrency(financial.cost_of_goods || 0);
    document.getElementById('grossProfit').textContent = formatCurrency(financial.gross_profit || 0);
    document.getElementById('profitMargin').textContent = (financial.profit_margin || 0).toFixed(1) + '%';
    
    // Update charts
    updateRevenueProfitChart(financial.revenue_profit || []);
    updateMonthlyFinancialChart(financial.monthly_overview || []);
}

// Initialize charts
function initializeCharts() {
    const chartOptions = {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: {
                position: 'top',
            }
        }
    };
    
    // Initialize all chart contexts
    charts.salesTrend = new Chart(document.getElementById('salesTrendChart'), {
        type: 'line',
        data: { labels: [], datasets: [] },
        options: chartOptions
    });
    
    charts.topMedicines = new Chart(document.getElementById('topMedicinesChart'), {
        type: 'bar',
        data: { labels: [], datasets: [] },
        options: chartOptions
    });
    
    charts.stockLevels = new Chart(document.getElementById('stockLevelsChart'), {
        type: 'bar',
        data: { labels: [], datasets: [] },
        options: chartOptions
    });
    
    charts.categories = new Chart(document.getElementById('categoriesChart'), {
        type: 'doughnut',
        data: { labels: [], datasets: [] },
        options: chartOptions
    });
    
    charts.customerTrends = new Chart(document.getElementById('customerTrendsChart'), {
        type: 'line',
        data: { labels: [], datasets: [] },
        options: chartOptions
    });
    
    charts.customerPatterns = new Chart(document.getElementById('customerPatternsChart'), {
        type: 'bar',
        data: { labels: [], datasets: [] },
        options: chartOptions
    });
    
    charts.revenueProfit = new Chart(document.getElementById('revenueProfitChart'), {
        type: 'line',
        data: { labels: [], datasets: [] },
        options: chartOptions
    });
    
    charts.monthlyFinancial = new Chart(document.getElementById('monthlyFinancialChart'), {
        type: 'bar',
        data: { labels: [], datasets: [] },
        options: chartOptions
    });
}

// Chart update functions
function updateSalesTrendChart(data) {
    charts.salesTrend.data.labels = data.map(item => item.date);
    charts.salesTrend.data.datasets = [{
        label: 'Daily Sales ($)',
        data: data.map(item => item.revenue),
        borderColor: 'rgb(75, 192, 192)',
        backgroundColor: 'rgba(75, 192, 192, 0.2)',
        tension: 0.1
    }];
    charts.salesTrend.update();
}

function updateTopMedicinesChart(data) {
    charts.topMedicines.data.labels = data.map(item => item.medicine_name);
    charts.topMedicines.data.datasets = [{
        label: 'Quantity Sold',
        data: data.map(item => item.quantity_sold),
        backgroundColor: [
            'rgba(255, 99, 132, 0.8)',
            'rgba(54, 162, 235, 0.8)',
            'rgba(255, 205, 86, 0.8)',
            'rgba(75, 192, 192, 0.8)',
            'rgba(153, 102, 255, 0.8)'
        ]
    }];
    charts.topMedicines.update();
}

function updateStockLevelsChart(data) {
    charts.stockLevels.data.labels = data.map(item => item.medicine_name);
    charts.stockLevels.data.datasets = [{
        label: 'Current Stock',
        data: data.map(item => item.current_stock),
        backgroundColor: 'rgba(54, 162, 235, 0.8)'
    }, {
        label: 'Minimum Stock',
        data: data.map(item => item.minimum_stock),
        backgroundColor: 'rgba(255, 99, 132, 0.8)'
    }];
    charts.stockLevels.update();
}

function updateCategoriesChart(data) {
    charts.categories.data.labels = data.map(item => item.category_name);
    charts.categories.data.datasets = [{
        data: data.map(item => item.item_count),
        backgroundColor: [
            '#FF6384', '#36A2EB', '#FFCE56', '#FF9F40',
            '#4BC0C0', '#9966FF', '#FF6600', '#C9CBCF'
        ]
    }];
    charts.categories.update();
}

function updateCustomerTrendsChart(data) {
    charts.customerTrends.data.labels = data.map(item => item.date);
    charts.customerTrends.data.datasets = [{
        label: 'New Customers',
        data: data.map(item => item.new_customers),
        borderColor: 'rgb(255, 99, 132)',
        backgroundColor: 'rgba(255, 99, 132, 0.2)',
        tension: 0.1
    }];
    charts.customerTrends.update();
}

function updateCustomerPatternsChart(data) {
    charts.customerPatterns.data.labels = data.map(item => item.hour + ':00');
    charts.customerPatterns.data.datasets = [{
        label: 'Purchases by Hour',
        data: data.map(item => item.purchase_count),
        backgroundColor: 'rgba(153, 102, 255, 0.8)'
    }];
    charts.customerPatterns.update();
}

function updateRevenueProfitChart(data) {
    charts.revenueProfit.data.labels = data.map(item => item.date);
    charts.revenueProfit.data.datasets = [{
        label: 'Revenue',
        data: data.map(item => item.revenue),
        borderColor: 'rgb(75, 192, 192)',
        backgroundColor: 'rgba(75, 192, 192, 0.2)',
        tension: 0.1
    }, {
        label: 'Profit',
        data: data.map(item => item.profit),
        borderColor: 'rgb(255, 99, 132)',
        backgroundColor: 'rgba(255, 99, 132, 0.2)',
        tension: 0.1
    }];
    charts.revenueProfit.update();
}

function updateMonthlyFinancialChart(data) {
    charts.monthlyFinancial.data.labels = data.map(item => item.month);
    charts.monthlyFinancial.data.datasets = [{
        label: 'Revenue',
        data: data.map(item => item.revenue),
        backgroundColor: 'rgba(54, 162, 235, 0.8)'
    }, {
        label: 'Expenses',
        data: data.map(item => item.expenses),
        backgroundColor: 'rgba(255, 99, 132, 0.8)'
    }];
    charts.monthlyFinancial.update();
}

// Table update functions
function updateDailySalesTable(data) {
    const tbody = document.getElementById('dailySalesTableBody');
    
    if (data.length === 0) {
        tbody.innerHTML = '<tr><td colspan="5" class="text-center">No sales data found</td></tr>';
        return;
    }
    
    tbody.innerHTML = data.map(row => `
        <tr>
            <td>${formatDate(row.date)}</td>
            <td>${row.transaction_count}</td>
            <td>${row.items_sold}</td>
            <td>${formatCurrency(row.revenue)}</td>
            <td>${formatCurrency(row.average_sale)}</td>
        </tr>
    `).join('');
}

function updateInventoryTable(data) {
    const tbody = document.getElementById('inventoryTableBody');
    
    if (data.length === 0) {
        tbody.innerHTML = '<tr><td colspan="6" class="text-center">No inventory data found</td></tr>';
        return;
    }
    
    tbody.innerHTML = data.map(item => {
        const status = getStockStatus(item.current_stock, item.minimum_stock);
        return `
            <tr>
                <td>${escapeHtml(item.medicine_name)}</td>
                <td>${escapeHtml(item.category_name || 'N/A')}</td>
                <td>${item.current_stock}</td>
                <td>${item.minimum_stock}</td>
                <td>${formatCurrency(item.stock_value)}</td>
                <td><span class="badge ${status.class}">${status.text}</span></td>
            </tr>
        `;
    }).join('');
}

function updateTopCustomersTable(data) {
    const tbody = document.getElementById('topCustomersTableBody');
    
    if (data.length === 0) {
        tbody.innerHTML = '<tr><td colspan="5" class="text-center">No customer data found</td></tr>';
        return;
    }
    
    tbody.innerHTML = data.map(customer => `
        <tr>
            <td>${escapeHtml(customer.customer_name)}</td>
            <td>${customer.total_purchases}</td>
            <td>${formatCurrency(customer.total_spent)}</td>
            <td>${formatDate(customer.last_purchase)}</td>
            <td><span class="badge ${getLoyaltyClass(customer.loyalty_status)}">${customer.loyalty_status}</span></td>
        </tr>
    `).join('');
}

// Update inventory alerts
function updateInventoryAlerts(alerts) {
    const lowStockContainer = document.getElementById('lowStockAlerts');
    const expiryContainer = document.getElementById('expiryAlerts');
    
    // Low stock alerts
    if (alerts.low_stock && alerts.low_stock.length > 0) {
        lowStockContainer.innerHTML = alerts.low_stock.map(item => `
            <div class="alert-item warning">
                <i class="fas fa-exclamation-triangle"></i>
                <div class="alert-content">
                    <strong>${escapeHtml(item.medicine_name)}</strong>
                    <span>Current: ${item.current_stock} | Minimum: ${item.minimum_stock}</span>
                </div>
            </div>
        `).join('');
    } else {
        lowStockContainer.innerHTML = '<div class="alert-item success"><i class="fas fa-check"></i> All medicines are well stocked</div>';
    }
    
    // Expiry alerts
    if (alerts.expiry && alerts.expiry.length > 0) {
        expiryContainer.innerHTML = alerts.expiry.map(item => `
            <div class="alert-item danger">
                <i class="fas fa-calendar-times"></i>
                <div class="alert-content">
                    <strong>${escapeHtml(item.medicine_name)}</strong>
                    <span>Expires: ${formatDate(item.expiry_date)}</span>
                </div>
            </div>
        `).join('');
    } else {
        expiryContainer.innerHTML = '<div class="alert-item success"><i class="fas fa-check"></i> No medicines expiring soon</div>';
    }
}

// Utility functions
function getStockStatus(current, minimum) {
    if (current <= 0) {
        return { class: 'badge-danger', text: 'Out of Stock' };
    } else if (current <= minimum) {
        return { class: 'badge-warning', text: 'Low Stock' };
    } else if (current <= minimum * 2) {
        return { class: 'badge-info', text: 'Normal' };
    } else {
        return { class: 'badge-success', text: 'Well Stocked' };
    }
}

function getLoyaltyClass(status) {
    const statusMap = {
        'Regular': 'badge-secondary',
        'Silver': 'badge-info',
        'Gold': 'badge-warning',
        'Platinum': 'badge-success'
    };
    return statusMap[status] || 'badge-secondary';
}

function formatCurrency(amount) {
    return new Intl.NumberFormat('en-US', {
        style: 'currency',
        currency: 'USD'
    }).format(amount || 0);
}

function formatDate(dateString) {
    if (!dateString) return 'N/A';
    return new Date(dateString).toLocaleDateString();
}

// Export and print functions
async function exportReports() {
    try {
        showLoading('Generating PDF report...');
        
        const startDate = document.getElementById('startDate').value;
        const endDate = document.getElementById('endDate').value;
        
        const response = await secureApiCall(`/api/reports.php?action=export&start=${startDate}&end=${endDate}&format=pdf`);
        
        if (response.success) {
            // Create download link
            const link = document.createElement('a');
            link.href = response.data.download_url;
            link.download = `pharmacy_report_${startDate}_to_${endDate}.pdf`;
            link.click();
            
            showSuccess('Report exported successfully!');
        } else {
            showError('Failed to export report: ' + response.message);
        }
    } catch (error) {
        showError('Error exporting report: ' + error.message);
    } finally {
        hideLoading();
    }
}

function printReports() {
    window.print();
}
