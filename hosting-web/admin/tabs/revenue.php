<div class="card">
    <h2 class="card-title"><i class="fas fa-chart-line"></i> Báo cáo doanh thu</h2>
    
    <!-- Revenue Filter Section -->
    <div class="filter-section" style="background: #f8fafc; padding: 1rem; border-radius: 8px; margin-bottom: 1.5rem; border: 1px solid #e2e8f0;">
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 1rem; align-items: end;">
            <div class="form-group" style="margin-bottom: 0;">
                <label class="form-label" style="font-size: 0.875rem; font-weight: 600; color: #374151;">💰 Loại giao dịch</label>
                <select id="revenueTypeFilter" class="form-control" style="font-size: 0.875rem;">
                    <option value="">Tất cả loại</option>
                    <option value="booking">Đặt chỗ</option>
                    <option value="walkin">Vãng lai</option>
                    <option value="overstay">Phí quá giờ</option>
                </select>
            </div>
            <div class="form-group" style="margin-bottom: 0;">
                <label class="form-label" style="font-size: 0.875rem; font-weight: 600; color: #374151;">💳 Trạng thái thanh toán</label>
                <select id="revenueStatusFilter" class="form-control" style="font-size: 0.875rem;">
                    <option value="">Tất cả trạng thái</option>
                    <option value="completed">Hoàn thành</option>
                    <option value="pending">Đang xử lý</option>
                    <option value="failed">Thất bại</option>
                </select>
            </div>
            <div class="form-group" style="margin-bottom: 0;">
                <label class="form-label" style="font-size: 0.875rem; font-weight: 600; color: #374151;">📅 Từ ngày</label>
                <input type="date" id="revenueDateFrom" class="form-control" style="font-size: 0.875rem;">
            </div>
            <div class="form-group" style="margin-bottom: 0;">
                <label class="form-label" style="font-size: 0.875rem; font-weight: 600; color: #374151;">📅 Đến ngày</label>
                <input type="date" id="revenueDateTo" class="form-control" style="font-size: 0.875rem;">
            </div>
            <div style="display: flex; gap: 0.5rem;">
                <button onclick="applyRevenueFilters()" class="btn btn-primary" style="padding: 0.5rem 1rem; font-size: 0.875rem;">📊 Cập nhật</button>
                <button onclick="clearRevenueFilters()" class="btn" style="background: #6b7280; color: white; padding: 0.5rem 1rem; font-size: 0.875rem;">🔄 Reset</button>
            </div>
        </div>
    </div>

    <div class="stats">
        <div class="stat-card" style="border-left: 4px solid #2563eb;">
            <div class="stat-icon" style="color: #2563eb;">
                <i class="fas fa-dollar-sign"></i>
            </div>
            <div class="stat-value"><?php echo number_format($revenue_stats['total'], 0, ',', '.'); ?>₫
            </div>
            <div class="stat-label">Tổng doanh thu</div>
        </div>

        <div class="stat-card" style="border-left: 4px solid #2563eb;">
            <div class="stat-icon" style="color: #2563eb;">
                <i class="fas fa-file-invoice-dollar"></i>
            </div>
            <div class="stat-value"><?php echo number_format($revenue_stats['today'], 0, ',', '.'); ?>₫
            </div>
            <div class="stat-label">Doanh thu hôm nay</div>
        </div>

        <div class="stat-card" style="border-left: 4px solid #10b981;">
            <div class="stat-icon" style="color: #10b981;">
                <i class="fas fa-check-circle"></i>
            </div>
            <div class="stat-value"><?php echo number_format($completed_payments_count, 0, ',', '.'); ?>
            </div>
            <div class="stat-label">Tổng giao dịch thành công</div>
        </div>

        <div class="stat-card" style="border-left: 4px solid #10b981;">
            <div class="stat-icon" style="color: #10b981;">
                <i class="fas fa-percentage"></i>
            </div>
            <div class="stat-value"><?php echo number_format($success_rate, 2); ?>%</div>
            <div class="stat-label">Tỷ lệ thành công</div>
        </div>
    </div>

    <div class="tab-nav">
        <div id="tab-total-revenue" class="active" onclick="switchTab('total-revenue')">💰 Tổng Doanh Thu</div>
        <div id="tab-monthly-trend" onclick="switchTab('monthly-trend')">📊 Xu Hướng Tháng</div>
        <div id="tab-payment-methods" onclick="switchTab('payment-methods')">💳 Phương Thức Thanh Toán</div>
    </div>

    <div id="chart-total-revenue" class="chart-container">
        <h3>💰 Tổng Doanh Thu Theo Loại Giao Dịch</h3>
        <canvas id="totalRevenueChart"></canvas>
    </div>

    <div id="chart-monthly-trend" class="chart-container" style="display: none;">
        <h3>📊 Xu Hướng Doanh Thu Theo Tháng</h3>
        <canvas id="monthlyTrendChart"></canvas>
    </div>

    <div id="chart-payment-methods" class="chart-container" style="display: none;">
        <h3>💳 Phân Bổ Theo Phương Thức Thanh Toán</h3>
        <canvas id="paymentMethodsChart"></canvas>
    </div>
</div>

<script>
// JavaScript for Revenue Charts
Chart.register(ChartDataLabels);

let myTotalRevenueChart;
let myMonthlyTrendChart;  
let myPaymentMethodsChart;

function switchTab(tabName) {
    const tabs = ['total-revenue', 'monthly-trend', 'payment-methods'];
    tabs.forEach(tab => {
        const container = document.getElementById(`chart-${tab}`);
        const navItem = document.getElementById(`tab-${tab}`);
        if (container) container.style.display = 'none';
        if (navItem) navItem.classList.remove('active');
    });

    const activeContainer = document.getElementById(`chart-${tabName}`);
    const activeNav = document.getElementById(`tab-${tabName}`);
    if (activeContainer) activeContainer.style.display = 'block';
    if (activeNav) activeNav.classList.add('active');

    // Load chart for the selected tab
    switch (tabName) {
        case 'total-revenue':
            loadTotalRevenueChart();
            break;
        case 'monthly-trend':
            loadMonthlyTrendChart();
            break;
        case 'payment-methods':
            loadPaymentMethodsChart();
            break;
    }
}

function loadTotalRevenueChart() {
    if (myTotalRevenueChart) myTotalRevenueChart.destroy();
    
    // Fetch dữ liệu thật từ API
    fetch('api/get_revenue_distribution.php')
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const ctx = document.getElementById('totalRevenueChart').getContext('2d');
                myTotalRevenueChart = new Chart(ctx, {
                    type: 'doughnut',
                    data: {
                        labels: data.data.labels,
                        datasets: [{
                            data: data.data.values,
                            backgroundColor: [
                                '#3b82f6',
                                '#f59e0b', 
                                '#ef4444'
                            ],
                            borderWidth: 4,
                            borderColor: '#ffffff',
                            hoverBorderWidth: 6
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: {
                                position: 'bottom',
                                labels: {
                                    padding: 25,
                                    usePointStyle: true,
                                    pointStyle: 'circle',
                                    font: {
                                        size: 14,
                                        weight: '600'
                                    }
                                }
                            },
                            tooltip: {
                                callbacks: {
                                    label: function(context) {
                                        const total = context.dataset.data.reduce((a, b) => a + b, 0);
                                        const percentage = ((context.parsed / total) * 100).toFixed(1);
                                        return context.label + ': ' + context.parsed.toLocaleString('vi-VN') + '₫ (' + percentage + '%)';
                                    }
                                },
                                backgroundColor: 'rgba(0, 0, 0, 0.8)',
                                titleColor: '#ffffff',
                                bodyColor: '#ffffff',
                                borderColor: 'rgba(255, 255, 255, 0.1)',
                                borderWidth: 1
                            }
                        },
                        animation: {
                            animateRotate: true,
                            animateScale: true
                        }
                    }
                });
            }
        })
        .catch(error => {
            console.error('Error loading total revenue chart:', error);
        });
}

function loadMonthlyTrendChart() {
    if (myMonthlyTrendChart) myMonthlyTrendChart.destroy();
    
    // Fetch dữ liệu thật từ API
    fetch('api/get_revenue_trend.php')
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const ctx = document.getElementById('monthlyTrendChart').getContext('2d');
                myMonthlyTrendChart = new Chart(ctx, {
                    type: 'line',
                    data: {
                        labels: data.data.labels,
                        datasets: [{
                            label: 'Doanh thu tháng (VNĐ)',
                            data: data.data.values,
                            borderColor: '#059669',
                            backgroundColor: 'rgba(5, 150, 105, 0.1)',
                            fill: true,
                            tension: 0.4,
                            pointBackgroundColor: '#059669',
                            pointBorderColor: '#ffffff',
                            pointBorderWidth: 3,
                            pointRadius: 7,
                            pointHoverRadius: 9
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: {
                                display: false
                            },
                            tooltip: {
                                callbacks: {
                                    label: function(context) {
                                        return 'Doanh thu: ' + context.parsed.y.toLocaleString('vi-VN') + '₫';
                                    }
                                },
                                backgroundColor: 'rgba(0, 0, 0, 0.8)',
                                titleColor: '#ffffff',
                                bodyColor: '#ffffff',
                                borderColor: 'rgba(255, 255, 255, 0.1)',
                                borderWidth: 1
                            }
                        },
                        scales: {
                            y: {
                                beginAtZero: true,
                                ticks: {
                                    callback: function(value) {
                                        return value.toLocaleString('vi-VN') + '₫';
                                    }
                                },
                                grid: {
                                    color: 'rgba(0, 0, 0, 0.1)'
                                }
                            },
                            x: {
                                grid: {
                                    color: 'rgba(0, 0, 0, 0.1)'
                                }
                            }
                        },
                        animation: {
                            duration: 2000,
                            easing: 'easeInOutQuart'
                        }
                    }
                });
            }
        })
        .catch(error => {
            console.error('Error loading monthly trend chart:', error);
        });
}

function loadPaymentMethodsChart() {
    if (myPaymentMethodsChart) myPaymentMethodsChart.destroy();
    
    // Fetch dữ liệu thật từ API
    fetch('api/get_revenue_ranking.php')
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const ctx = document.getElementById('paymentMethodsChart').getContext('2d');
                myPaymentMethodsChart = new Chart(ctx, {
                    type: 'bar',
                    data: {
                        labels: data.data.labels,
                        datasets: [{
                            label: 'Doanh thu (₫)',
                            data: data.data.values,
                            backgroundColor: [
                                '#10b981',
                                '#f59e0b',
                                '#3b82f6'
                            ],
                            borderRadius: 12,
                            borderSkipped: false,
                            hoverBackgroundColor: [
                                '#059669',
                                '#d97706',
                                '#2563eb'
                            ]
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: {
                                display: false
                            },
                            tooltip: {
                                callbacks: {
                                    label: function(context) {
                                        const total = context.dataset.data.reduce((a, b) => a + b, 0);
                                        const percentage = ((context.parsed.y / total) * 100).toFixed(1);
                                        return context.label + ': ' + context.parsed.y.toLocaleString('vi-VN') + '₫ (' + percentage + '%)';
                                    }
                                },
                                backgroundColor: 'rgba(0, 0, 0, 0.8)',
                                titleColor: '#ffffff',
                                bodyColor: '#ffffff',
                                borderColor: 'rgba(255, 255, 255, 0.1)',
                                borderWidth: 1
                            }
                        },
                        scales: {
                            y: {
                                beginAtZero: true,
                                ticks: {
                                    callback: function(value) {
                                        return value.toLocaleString('vi-VN') + '₫';
                                    }
                                },
                                grid: {
                                    color: 'rgba(0, 0, 0, 0.1)'
                                }
                            },
                            x: {
                                grid: {
                                    display: false
                                }
                            }
                        },
                        animation: {
                            duration: 1500,
                            easing: 'easeOutBounce'
                        }
                    }
                });
            }
        })
        .catch(error => {
            console.error('Error loading payment methods chart:', error);
        });
}

// Init first tab
switchTab('total-revenue');
</script>
