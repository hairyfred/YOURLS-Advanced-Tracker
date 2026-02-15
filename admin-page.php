<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Advanced Link Tracker - Analytics Dashboard</title>
    <link rel="stylesheet" href="<?php echo yourls_plugin_url(dirname(__FILE__)) . '/style.css'; ?>">
    <script src="https://cdn.jsdelivr.net/npm/chart.js@3.9.1/dist/chart.min.js"></script>
</head>
<body>

<div class="wrap">
    <h1>Advanced Link Tracker - Analytics Dashboard</h1>

    <!-- Statistics Summary Cards -->
    <div class="stats-container">
        <div class="stat-card">
            <div class="stat-icon">📊</div>
            <div class="stat-content">
                <div class="stat-label">Total Clicks</div>
                <div class="stat-value"><?php echo number_format($stats['total_clicks']); ?></div>
            </div>
        </div>

        <div class="stat-card">
            <div class="stat-icon">👥</div>
            <div class="stat-content">
                <div class="stat-label">Unique Visitors</div>
                <div class="stat-value"><?php echo number_format($stats['unique_visitors']); ?></div>
            </div>
        </div>

        <div class="stat-card">
            <div class="stat-icon">📱</div>
            <div class="stat-content">
                <div class="stat-label">Mobile Visitors</div>
                <div class="stat-value">
                    <?php
                    $mobile_count = 0;
                    foreach ($stats['device_types'] as $device) {
                        if ($device->device_type === 'Mobile') {
                            $mobile_count = $device->count;
                        }
                    }
                    echo number_format($mobile_count);
                    ?>
                </div>
            </div>
        </div>

        <div class="stat-card">
            <div class="stat-icon">🌍</div>
            <div class="stat-content">
                <div class="stat-label">Countries</div>
                <div class="stat-value"><?php echo count($stats['top_countries']); ?></div>
            </div>
        </div>
    </div>

    <!-- Charts Section -->
    <div class="charts-container">
        <div class="chart-box">
            <h3>Clicks Over Time (Last 30 Days)</h3>
            <canvas id="clicksChart"></canvas>
        </div>

        <div class="chart-box">
            <h3>Device Types</h3>
            <canvas id="deviceChart"></canvas>
        </div>
    </div>

    <div class="charts-container">
        <div class="chart-box">
            <h3>Top Browsers</h3>
            <canvas id="browserChart"></canvas>
        </div>

        <div class="chart-box">
            <h3>Top Operating Systems</h3>
            <canvas id="osChart"></canvas>
        </div>
    </div>

    <!-- Top Countries Table -->
    <div class="table-section">
        <h3>🌍 Top Countries</h3>
        <table class="data-table">
            <thead>
                <tr>
                    <th>Country</th>
                    <th>Clicks</th>
                    <th>Percentage</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($stats['top_countries'] as $country): ?>
                <tr>
                    <td><?php echo htmlspecialchars($country->country); ?></td>
                    <td><?php echo number_format($country->count); ?></td>
                    <td><?php echo round(($country->count / $stats['total_clicks']) * 100, 1); ?>%</td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <!-- Top Referrers Table -->
    <?php if (!empty($stats['top_referrers'])): ?>
    <div class="table-section">
        <h3>🔗 Top Referrers</h3>
        <table class="data-table">
            <thead>
                <tr>
                    <th>Referrer</th>
                    <th>Clicks</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($stats['top_referrers'] as $referrer): ?>
                <tr>
                    <td class="referrer-cell" title="<?php echo htmlspecialchars($referrer->referrer); ?>">
                        <?php echo htmlspecialchars(substr($referrer->referrer, 0, 80)) . (strlen($referrer->referrer) > 80 ? '...' : ''); ?>
                    </td>
                    <td><?php echo number_format($referrer->count); ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>

    <!-- Filters -->
    <div class="filters-section">
        <h3>Filter Click Data</h3>
        <form method="get" action="">
            <input type="hidden" name="page" value="advanced_tracker">

            <div class="filter-row">
                <div class="filter-group">
                    <label for="keyword">Short URL:</label>
                    <select name="keyword" id="keyword">
                        <option value="">All URLs</option>
                        <?php foreach ($keywords as $kw): ?>
                        <option value="<?php echo htmlspecialchars($kw->keyword); ?>"
                                <?php echo ($keyword_filter === $kw->keyword) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($kw->keyword); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="filter-group">
                    <label for="date_from">From:</label>
                    <input type="date" name="date_from" id="date_from" value="<?php echo htmlspecialchars($date_from); ?>">
                </div>

                <div class="filter-group">
                    <label for="date_to">To:</label>
                    <input type="date" name="date_to" id="date_to" value="<?php echo htmlspecialchars($date_to); ?>">
                </div>

                <div class="filter-group">
                    <label for="limit">Results per page:</label>
                    <select name="limit" id="limit">
                        <option value="50" <?php echo ($limit === 50) ? 'selected' : ''; ?>>50</option>
                        <option value="100" <?php echo ($limit === 100) ? 'selected' : ''; ?>>100</option>
                        <option value="500" <?php echo ($limit === 500) ? 'selected' : ''; ?>>500</option>
                        <option value="1000" <?php echo ($limit === 1000) ? 'selected' : ''; ?>>1000</option>
                    </select>
                </div>

                <div class="filter-group">
                    <button type="submit" class="button">Apply Filters</button>
                    <a href="?page=advanced_tracker" class="button button-secondary">Reset</a>
                </div>
            </div>
        </form>
    </div>

    <!-- Export Buttons -->
    <div class="export-section">
        <h3>Export Data</h3>
        <a href="?page=advanced_tracker&export=csv" class="button button-export">📥 Export to CSV</a>
        <a href="?page=advanced_tracker&export=json" class="button button-export">📥 Export to JSON</a>
    </div>

    <!-- Click Details Table -->
    <div class="table-section">
        <h3>Recent Clicks (<?php echo number_format($total_clicks); ?> total)</h3>

        <?php if ($total_clicks > $limit): ?>
        <div class="pagination">
            <?php
            $total_pages = ceil($total_clicks / $limit);
            $base_url = '?page=advanced_tracker&limit=' . $limit;
            if (!empty($keyword_filter)) $base_url .= '&keyword=' . urlencode($keyword_filter);
            if (!empty($date_from)) $base_url .= '&date_from=' . urlencode($date_from);
            if (!empty($date_to)) $base_url .= '&date_to=' . urlencode($date_to);

            // Previous
            if ($page > 1) {
                echo '<a href="' . $base_url . '&paged=' . ($page - 1) . '" class="button">← Previous</a> ';
            }

            // Page numbers
            echo '<span>Page ' . $page . ' of ' . $total_pages . '</span> ';

            // Next
            if ($page < $total_pages) {
                echo '<a href="' . $base_url . '&paged=' . ($page + 1) . '" class="button">Next →</a>';
            }
            ?>
        </div>
        <?php endif; ?>

        <div class="table-responsive">
            <table class="data-table clicks-table">
                <thead>
                    <tr>
                        <th>Timestamp</th>
                        <th>Short URL</th>
                        <th>IP Address</th>
                        <th>Location</th>
                        <th>Device</th>
                        <th>Browser</th>
                        <th>OS</th>
                        <th>Referrer</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($clicks as $click): ?>
                    <tr>
                        <td class="timestamp-cell"><?php echo htmlspecialchars($click->timestamp); ?></td>
                        <td class="keyword-cell">
                            <strong><?php echo htmlspecialchars($click->keyword); ?></strong>
                        </td>
                        <td class="ip-cell">
                            <code><?php echo htmlspecialchars($click->ip_address); ?></code>
                        </td>
                        <td class="location-cell">
                            <?php
                            $location_parts = array_filter([
                                $click->city,
                                $click->region,
                                $click->country
                            ]);
                            echo htmlspecialchars(implode(', ', $location_parts));
                            ?>
                            <?php if ($click->isp): ?>
                            <br><small>ISP: <?php echo htmlspecialchars($click->isp); ?></small>
                            <?php endif; ?>
                        </td>
                        <td class="device-cell">
                            <?php echo htmlspecialchars($click->device_type); ?>
                            <?php if ($click->device_brand): ?>
                            <br><small><?php echo htmlspecialchars($click->device_brand . ($click->device_model ? ' ' . $click->device_model : '')); ?></small>
                            <?php endif; ?>
                        </td>
                        <td class="browser-cell">
                            <?php echo htmlspecialchars($click->browser); ?>
                            <?php if ($click->browser_version): ?>
                            <br><small>v<?php echo htmlspecialchars($click->browser_version); ?></small>
                            <?php endif; ?>
                        </td>
                        <td class="os-cell">
                            <?php echo htmlspecialchars($click->os); ?>
                            <?php if ($click->os_version): ?>
                            <br><small>v<?php echo htmlspecialchars($click->os_version); ?></small>
                            <?php endif; ?>
                        </td>
                        <td class="referrer-cell" title="<?php echo htmlspecialchars($click->referrer); ?>">
                            <?php
                            if (!empty($click->referrer)) {
                                $ref_display = strlen($click->referrer) > 30 ? substr($click->referrer, 0, 30) . '...' : $click->referrer;
                                echo htmlspecialchars($ref_display);
                            } else {
                                echo '<em>Direct</em>';
                            }
                            ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <?php if ($total_clicks > $limit): ?>
        <div class="pagination">
            <?php
            // Previous
            if ($page > 1) {
                echo '<a href="' . $base_url . '&paged=' . ($page - 1) . '" class="button">← Previous</a> ';
            }

            // Page numbers
            echo '<span>Page ' . $page . ' of ' . $total_pages . '</span> ';

            // Next
            if ($page < $total_pages) {
                echo '<a href="' . $base_url . '&paged=' . ($page + 1) . '" class="button">Next →</a>';
            }
            ?>
        </div>
        <?php endif; ?>
    </div>
</div>

<script>
// Prepare data for charts
const clicksData = <?php echo json_encode(array_reverse($stats['clicks_by_date'])); ?>;
const deviceData = <?php echo json_encode($stats['device_types']); ?>;
const browserData = <?php echo json_encode($stats['top_browsers']); ?>;
const osData = <?php echo json_encode($stats['top_os']); ?>;

// Clicks over time chart
const clicksCtx = document.getElementById('clicksChart').getContext('2d');
new Chart(clicksCtx, {
    type: 'line',
    data: {
        labels: clicksData.map(d => d.date),
        datasets: [{
            label: 'Clicks',
            data: clicksData.map(d => d.count),
            borderColor: 'rgb(75, 192, 192)',
            backgroundColor: 'rgba(75, 192, 192, 0.2)',
            tension: 0.1,
            fill: true
        }]
    },
    options: {
        responsive: true,
        plugins: {
            legend: {
                display: false
            }
        },
        scales: {
            y: {
                beginAtZero: true,
                ticks: {
                    precision: 0
                }
            }
        }
    }
});

// Device types chart
const deviceCtx = document.getElementById('deviceChart').getContext('2d');
new Chart(deviceCtx, {
    type: 'doughnut',
    data: {
        labels: deviceData.map(d => d.device_type),
        datasets: [{
            data: deviceData.map(d => d.count),
            backgroundColor: [
                'rgba(255, 99, 132, 0.8)',
                'rgba(54, 162, 235, 0.8)',
                'rgba(255, 206, 86, 0.8)',
                'rgba(75, 192, 192, 0.8)'
            ],
            borderWidth: 1
        }]
    },
    options: {
        responsive: true,
        plugins: {
            legend: {
                position: 'bottom'
            }
        }
    }
});

// Browser chart
const browserCtx = document.getElementById('browserChart').getContext('2d');
new Chart(browserCtx, {
    type: 'bar',
    data: {
        labels: browserData.map(d => d.browser),
        datasets: [{
            label: 'Clicks',
            data: browserData.map(d => d.count),
            backgroundColor: 'rgba(54, 162, 235, 0.8)',
            borderColor: 'rgba(54, 162, 235, 1)',
            borderWidth: 1
        }]
    },
    options: {
        responsive: true,
        plugins: {
            legend: {
                display: false
            }
        },
        scales: {
            y: {
                beginAtZero: true,
                ticks: {
                    precision: 0
                }
            }
        }
    }
});

// OS chart
const osCtx = document.getElementById('osChart').getContext('2d');
new Chart(osCtx, {
    type: 'bar',
    data: {
        labels: osData.map(d => d.os),
        datasets: [{
            label: 'Clicks',
            data: osData.map(d => d.count),
            backgroundColor: 'rgba(153, 102, 255, 0.8)',
            borderColor: 'rgba(153, 102, 255, 1)',
            borderWidth: 1
        }]
    },
    options: {
        responsive: true,
        plugins: {
            legend: {
                display: false
            }
        },
        scales: {
            y: {
                beginAtZero: true,
                ticks: {
                    precision: 0
                }
            }
        }
    }
});
</script>

</body>
</html>
