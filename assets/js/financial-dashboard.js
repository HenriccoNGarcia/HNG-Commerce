/**
 * HNG Commerce - Financial Dashboard with Charts
 * Interactive dashboard with Chart.js integration
 */

(function ($) {
    'use strict';

    const HNG_FinancialDashboard = {
        charts: {},
        currentPeriod: '30days',

        init: function () {
            if ($('.hng-financial-dashboard').length === 0) return;

            this.bindEvents();
            this.loadDashboardData();
        },

        /**
         * Bind event listeners
         */
        bindEvents: function () {
            const self = this;

            // Period selector
            $(document).on('change', '#hng-period-selector', function () {
                self.currentPeriod = $(this).val();
                self.loadDashboardData();
            });

            // Refresh button
            $(document).on('click', '.hng-refresh-dashboard', function (e) {
                e.preventDefault();
                self.loadDashboardData();
            });

            // Export buttons
            $(document).on('click', '.hng-export-pdf', function (e) {
                e.preventDefault();
                self.exportToPDF();
            });

            $(document).on('click', '.hng-export-excel', function (e) {
                e.preventDefault();
                self.exportToExcel();
            });
        },

        /**
         * Load dashboard data via AJAX
         */
        loadDashboardData: function () {
            const self = this;

            // Show loading
            $('.hng-financial-dashboard').addClass('loading');

            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'hng_get_dashboard_stats',
                    period: self.currentPeriod,
                    nonce: hngAdmin.nonce
                },
                success: function (response) {
                    if (response.success) {
                        self.updateStats(response.data.stats);
                        self.updateCharts(response.data.charts);
                    }
                },
                error: function () {
                    self.showNotification('Erro ao carregar dados', 'error');
                },
                complete: function () {
                    $('.hng-financial-dashboard').removeClass('loading');
                }
            });
        },

        /**
         * Update stat cards
         */
        updateStats: function (stats) {
            // Update each stat card
            $.each(stats, function (key, value) {
                const $card = $('[data-stat="' + key + '"]');
                const $valueEl = $card.find('.hng-stat-value');
                const $trendEl = $card.find('.hng-stat-trend');

                // Animate number change
                if ($valueEl.length) {
                    self.animateValue($valueEl, value.current);
                }

                // Update trend
                if ($trendEl.length && value.trend) {
                    $trendEl.html(value.trend);
                    $trendEl.removeClass('hng-trend-up hng-trend-down');
                    $trendEl.addClass(value.trendClass);
                }
            });
        },

        /**
         * Animate value change
         */
        animateValue: function ($el, newValue) {
            const oldValue = parseFloat($el.text().replace(/[^0-9.-]+/g, '')) || 0;
            const duration = 1000;
            const startTime = Date.now();

            const isMonetary = $el.text().includes('R$');

            function update() {
                const now = Date.now();
                const progress = Math.min((now - startTime) / duration, 1);
                const currentValue = oldValue + (newValue - oldValue) * progress;

                if (isMonetary) {
                    $el.text('R$ ' + currentValue.toLocaleString('pt-BR', {
                        minimumFractionDigits: 2,
                        maximumFractionDigits: 2
                    }));
                } else {
                    $el.text(Math.round(currentValue).toLocaleString('pt-BR'));
                }

                if (progress < 1) {
                    requestAnimationFrame(update);
                }
            }

            update();
        },

        /**
         * Update charts
         */
        updateCharts: function (chartsData) {
            const self = this;

            // Revenue vs Profit Chart
            if (chartsData.revenueProfit) {
                self.createLineChart('chart-revenue-profit', chartsData.revenueProfit);
            }

            // Gateway Performance Chart
            if (chartsData.gatewayPerformance) {
                self.createPieChart('chart-gateway-performance', chartsData.gatewayPerformance);
            }

            // Top Products Chart
            if (chartsData.topProducts) {
                self.createBarChart('chart-top-products', chartsData.topProducts);
            }

            // Trend Chart
            if (chartsData.trend) {
                self.createAreaChart('chart-trend', chartsData.trend);
            }
        },

        /**
         * Create line chart
         */
        createLineChart: function (canvasId, data) {
            const ctx = document.getElementById(canvasId);
            if (!ctx) return;

            // Destroy existing chart
            if (this.charts[canvasId]) {
                this.charts[canvasId].destroy();
            }

            this.charts[canvasId] = new Chart(ctx, {
                type: 'line',
                data: {
                    labels: data.labels,
                    datasets: [{
                        label: 'Receita',
                        data: data.revenue,
                        borderColor: '#6366f1',
                        backgroundColor: 'rgba(99, 102, 241, 0.1)',
                        tension: 0.4
                    }, {
                        label: 'Lucro',
                        data: data.profit,
                        borderColor: '#10b981',
                        backgroundColor: 'rgba(16, 185, 129, 0.1)',
                        tension: 0.4
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'top',
                        },
                        tooltip: {
                            callbacks: {
                                label: function (context) {
                                    let label = context.dataset.label || '';
                                    if (label) {
                                        label += ': ';
                                    }
                                    label += 'R$ ' + context.parsed.y.toLocaleString('pt-BR', {
                                        minimumFractionDigits: 2,
                                        maximumFractionDigits: 2
                                    });
                                    return label;
                                }
                            }
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: {
                                callback: function (value) {
                                    return 'R$ ' + value.toLocaleString('pt-BR');
                                }
                            }
                        }
                    }
                }
            });
        },

        /**
         * Create pie chart
         */
        createPieChart: function (canvasId, data) {
            const ctx = document.getElementById(canvasId);
            if (!ctx) return;

            if (this.charts[canvasId]) {
                this.charts[canvasId].destroy();
            }

            this.charts[canvasId] = new Chart(ctx, {
                type: 'pie',
                data: {
                    labels: data.labels,
                    datasets: [{
                        data: data.values,
                        backgroundColor: [
                            '#6366f1', '#10b981', '#f59e0b', '#ef4444',
                            '#8b5cf6', '#ec4899', '#06b6d4', '#84cc16'
                        ]
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'right',
                        },
                        tooltip: {
                            callbacks: {
                                label: function (context) {
                                    const label = context.label || '';
                                    const value = context.parsed || 0;
                                    const total = context.dataset.data.reduce((a, b) => a + b, 0);
                                    const percentage = ((value / total) * 100).toFixed(1);
                                    return label + ': R$ ' + value.toLocaleString('pt-BR', {
                                        minimumFractionDigits: 2
                                    }) + ' (' + percentage + '%)';
                                }
                            }
                        }
                    }
                }
            });
        },

        /**
         * Create bar chart
         */
        createBarChart: function (canvasId, data) {
            const ctx = document.getElementById(canvasId);
            if (!ctx) return;

            if (this.charts[canvasId]) {
                this.charts[canvasId].destroy();
            }

            this.charts[canvasId] = new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: data.labels,
                    datasets: [{
                        label: 'Vendas',
                        data: data.values,
                        backgroundColor: '#6366f1'
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            display: false
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true
                        }
                    }
                }
            });
        },

        /**
         * Create area chart
         */
        createAreaChart: function (canvasId, data) {
            const ctx = document.getElementById(canvasId);
            if (!ctx) return;

            if (this.charts[canvasId]) {
                this.charts[canvasId].destroy();
            }

            this.charts[canvasId] = new Chart(ctx, {
                type: 'line',
                data: {
                    labels: data.labels,
                    datasets: [{
                        label: 'Pedidos',
                        data: data.values,
                        borderColor: '#6366f1',
                        backgroundColor: 'rgba(99, 102, 241, 0.2)',
                        fill: true,
                        tension: 0.4
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            display: false
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true
                        }
                    }
                }
            });
        },

        /**
         * Export to PDF
         */
        exportToPDF: function () {
            this.showNotification('Exportando para PDF...', 'info');
            // Implementation would use jsPDF or server-side PDF generation
        },

        /**
         * Export to Excel
         */
        exportToExcel: function () {
            this.showNotification('Exportando para Excel...', 'info');
            // Implementation would use SheetJS or server-side Excel generation
        },

        /**
         * Show notification
         */
        showNotification: function (message, type) {
            // Use toast notification system
            if (typeof HNG_Notifications !== 'undefined') {
                HNG_Notifications.show(message, type);
            } else {
                alert(message);
            }
        }
    };

    // Initialize on document ready
    $(document).ready(function () {
        HNG_FinancialDashboard.init();
    });

    // Make it globally accessible
    window.HNG_FinancialDashboard = HNG_FinancialDashboard;

})(jQuery);
