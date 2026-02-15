<?php
// YOURLS admin pages should not include <html>, <head>, <body> tags
// YOURLS provides its own wrapper

// Load Chart.js
yourls_add_action('html_head', 'advanced_tracker_add_chartjs');
function advanced_tracker_add_chartjs() {
    echo '<script src="https://cdn.jsdelivr.net/npm/chart.js@3.9.1/dist/chart.min.js"></script>';
    echo '<link rel="stylesheet" href="' . yourls_plugin_url(dirname(__FILE__)) . '/style.css">';
}

// Add the CSS and JS
advanced_tracker_add_chartjs();
?>

<div class="wrap advanced-tracker-dashboard">
    <h2>Advanced Link Tracker - Analytics Dashboard</h2>

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
        <?php if (!empty($stats['top_countries'])): ?>
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
                    <td><?php echo $stats['total_clicks'] > 0 ? round(($country->count / $stats['total_clicks']) * 100, 1) : 0; ?>%</td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php else: ?>
        <p>No data available yet. Start tracking clicks to see country statistics.</p>
        <?php endif; ?>
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
                    <button type="submit" class="button button-primary">Apply Filters</button>
                    <a href="?page=advanced_tracker" class="button button-secondary">Reset</a>
                </div>
            </div>
        </form>
    </div>

    <!-- Export Buttons -->
    <div class="export-section">
        <h3>Export Data</h3>
        <a href="?page=advanced_tracker&export=csv" class="button button-primary">📥 Export to CSV</a>
        <a href="?page=advanced_tracker&export=json" class="button button-primary">📥 Export to JSON</a>
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

        <?php if ($total_clicks > 0): ?>
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
                        <th>Fingerprint</th>
                        <th>Details</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($clicks as $click): ?>
                    <tr class="click-row">
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
                            echo !empty($location_parts) ? htmlspecialchars(implode(', ', $location_parts)) : '-';
                            ?>
                            <?php if ($click->isp): ?>
                            <br><small>ISP: <?php echo htmlspecialchars($click->isp); ?></small>
                            <?php endif; ?>
                        </td>
                        <td class="device-cell">
                            <?php echo htmlspecialchars($click->device_type); ?>
                            <?php if ($click->screen_resolution): ?>
                            <br><small>📱 <?php echo htmlspecialchars($click->screen_resolution); ?></small>
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
                            <?php if ($click->platform): ?>
                            <br><small><?php echo htmlspecialchars($click->platform); ?></small>
                            <?php endif; ?>
                        </td>
                        <td class="fingerprint-cell">
                            <?php if ($click->canvas_fingerprint): ?>
                            <code title="<?php echo htmlspecialchars($click->canvas_fingerprint); ?>">
                                <?php echo htmlspecialchars(substr($click->canvas_fingerprint, 0, 8)); ?>...
                            </code>
                            <?php else: ?>
                            <em>-</em>
                            <?php endif; ?>
                        </td>
                        <td class="details-cell">
                            <button class="button button-small toggle-details" data-row="<?php echo $click->id; ?>">
                                ▼ More
                            </button>
                        </td>
                    </tr>
                    <tr class="details-row" id="details-<?php echo $click->id; ?>" style="display: none;">
                        <td colspan="9">
                            <div class="fingerprint-details">
                                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px;">
                                    <h4 style="margin: 0;">🔍 Advanced Fingerprint Data</h4>
                                    <div class="export-buttons">
                                        <a href="?page=advanced_tracker&export=json&export_id=<?php echo $click->id; ?>" class="button button-small">📄 JSON</a>
                                        <a href="?page=advanced_tracker&export=csv&export_id=<?php echo $click->id; ?>" class="button button-small">📊 CSV</a>
                                    </div>
                                </div>
                                <div class="fp-grid">
                                    <div class="fp-section">
                                        <h5>Display Info</h5>
                                        <ul>
                                            <li><strong>Screen Resolution:</strong> <?php echo $click->screen_resolution ?: 'N/A'; ?></li>
                                            <li><strong>Viewport Size:</strong> <?php echo $click->viewport_size ?: 'N/A'; ?></li>
                                            <li><strong>Color Depth:</strong> <?php echo $click->color_depth ? $click->color_depth . ' bits' : 'N/A'; ?></li>
                                            <li><strong>Touch Support:</strong> <?php echo $click->touch_support ? 'Yes' : 'No'; ?></li>
                                        </ul>
                                    </div>
                                    <div class="fp-section">
                                        <h5>System Info</h5>
                                        <ul>
                                            <li><strong>Platform:</strong> <?php echo htmlspecialchars($click->platform ?: 'N/A'); ?></li>
                                            <li><strong>CPU Cores:</strong> <?php echo $click->cpu_cores ?: 'N/A'; ?></li>
                                            <li><strong>Device Memory:</strong> <?php echo $click->device_memory ? $click->device_memory . ' GB' : 'N/A'; ?></li>
                                            <li><strong>Timezone:</strong> <?php echo htmlspecialchars($click->timezone ?: 'N/A'); ?></li>
                                        </ul>
                                    </div>
                                    <div class="fp-section">
                                        <h5>Privacy Settings</h5>
                                        <ul>
                                            <li><strong>Cookies Enabled:</strong> <?php echo $click->cookies_enabled ? 'Yes' : 'No'; ?></li>
                                            <li><strong>Do Not Track:</strong> <?php echo $click->do_not_track ? 'Enabled' : 'Disabled'; ?></li>
                                            <li><strong>Connection Type:</strong> <?php echo htmlspecialchars($click->connection_type ?: 'N/A'); ?></li>
                                        </ul>
                                    </div>
                                    <div class="fp-section">
                                        <h5>Hardware Info</h5>
                                        <ul>
                                            <li><strong>WebGL Vendor:</strong> <?php echo htmlspecialchars($click->webgl_vendor ?: 'N/A'); ?></li>
                                            <li><strong>WebGL Renderer:</strong> <?php echo htmlspecialchars($click->webgl_renderer ?: 'N/A'); ?></li>
                                            <li><strong>Canvas Fingerprint:</strong> <code><?php echo htmlspecialchars($click->canvas_fingerprint ?: 'N/A'); ?></code></li>
                                        </ul>
                                    </div>
                                    <?php if ($click->battery_level): ?>
                                    <div class="fp-section">
                                        <h5>Battery Status</h5>
                                        <ul>
                                            <li><strong>Charging:</strong> <?php echo $click->battery_charging ? 'Yes' : 'No'; ?></li>
                                            <li><strong>Level:</strong> <?php echo $click->battery_level; ?>%</li>
                                        </ul>
                                    </div>
                                    <?php endif; ?>
                                    <?php if ($click->fonts_detected): ?>
                                    <div class="fp-section">
                                        <h5>Detected Fonts</h5>
                                        <p><?php echo htmlspecialchars(implode(', ', json_decode($click->fonts_detected))); ?></p>
                                    </div>
                                    <?php endif; ?>
                                    <?php if ($click->plugins_list): ?>
                                    <div class="fp-section">
                                        <h5>Browser Plugins</h5>
                                        <p><?php echo htmlspecialchars(implode(', ', json_decode($click->plugins_list))); ?></p>
                                    </div>
                                    <?php endif; ?>
                                    <div class="fp-section full-width">
                                        <h5>Referrer</h5>
                                        <p><?php echo !empty($click->referrer) ? htmlspecialchars($click->referrer) : '<em>Direct visit</em>'; ?></p>
                                    </div>
                                </div>
                            </div>
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
        <?php else: ?>
        <p>No clicks tracked yet. Create a short URL and click it to see tracking data appear here!</p>
        <?php endif; ?>
    </div>
</div>

<script>
// Wait for Chart.js to load
if (typeof Chart !== 'undefined') {
    // Prepare data for charts
    const clicksData = <?php echo json_encode(array_reverse($stats['clicks_by_date'])); ?>;
    const deviceData = <?php echo json_encode($stats['device_types']); ?>;
    const browserData = <?php echo json_encode($stats['top_browsers']); ?>;
    const osData = <?php echo json_encode($stats['top_os']); ?>;

    // Clicks over time chart
    if (clicksData && clicksData.length > 0) {
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
                maintainAspectRatio: true,
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
    }

    // Device types chart
    if (deviceData && deviceData.length > 0) {
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
                maintainAspectRatio: true,
                plugins: {
                    legend: {
                        position: 'bottom'
                    }
                }
            }
        });
    }

    // Browser chart
    if (browserData && browserData.length > 0) {
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
                maintainAspectRatio: true,
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
    }

    // OS chart
    if (osData && osData.length > 0) {
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
                maintainAspectRatio: true,
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
    }
} else {
    console.error('Chart.js not loaded');
}

// Toggle fingerprint details
document.addEventListener('DOMContentLoaded', function() {
    var toggleButtons = document.querySelectorAll('.toggle-details');
    toggleButtons.forEach(function(button) {
        button.addEventListener('click', function() {
            var rowId = this.getAttribute('data-row');
            var detailsRow = document.getElementById('details-' + rowId);
            if (detailsRow.style.display === 'none') {
                detailsRow.style.display = 'table-row';
                this.innerHTML = '▲ Less';
            } else {
                detailsRow.style.display = 'none';
                this.innerHTML = '▼ More';
            }
        });
    });
});
</script>

<style>
.fingerprint-details {
    background: #f9f9f9;
    padding: 20px;
    border-radius: 8px;
    margin: 10px 0;
}

.fingerprint-details h4 {
    margin-top: 0;
    color: #333;
    border-bottom: 2px solid #0073aa;
    padding-bottom: 10px;
}

.fp-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 20px;
    margin-top: 15px;
}

.fp-section {
    background: white;
    padding: 15px;
    border-radius: 6px;
    border-left: 3px solid #0073aa;
}

.fp-section.full-width {
    grid-column: 1 / -1;
}

.fp-section h5 {
    margin-top: 0;
    color: #0073aa;
    font-size: 14px;
    margin-bottom: 10px;
}

.fp-section ul {
    list-style: none;
    padding: 0;
    margin: 0;
}

.fp-section li {
    padding: 5px 0;
    font-size: 13px;
    border-bottom: 1px solid #eee;
}

.fp-section li:last-child {
    border-bottom: none;
}

.fp-section p {
    margin: 0;
    font-size: 13px;
    word-break: break-all;
}

.details-row {
    background: #fafafa;
}

.toggle-details {
    font-size: 12px;
    padding: 5px 10px;
    cursor: pointer;
}

.fingerprint-cell code {
    font-size: 11px;
    background: #f5f5f5;
    padding: 2px 6px;
    border-radius: 3px;
}

@media (max-width: 768px) {
    .fp-grid {
        grid-template-columns: 1fr;
    }
}
</style>
