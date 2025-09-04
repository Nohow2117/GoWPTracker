<?php
// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Handles CSV export for the GO tracker report.
 *
 * Must be called before any HTML output.
 */
function gowptracker_handle_go_csv_export() {
    if (isset($_POST['gowptracker_export_csv'])) {
        // We can't use check_admin_referer here because the page reloads.
        // The security check is that this only runs for `manage_options` users inside an admin page.
        if (!current_user_can('manage_options')) {
            return;
        }

        global $wpdb;
        $table = $wpdb->prefix . 'go_clicks';
        $since = date('Y-m-d H:i:s', strtotime('-7 days'));
        $rows = $wpdb->get_results(
            $wpdb->prepare( "SELECT referrer, utm_campaign FROM $table WHERE ts >= %s", $since ),
            ARRAY_A
        );

        $agg = [];
        foreach ($rows as $row) {
            $ref = $row['referrer'];
            $parsed = wp_parse_url($ref);
            $plp_path = isset($parsed['path']) ? $parsed['path'] : '(unknown)';
            $camp = $row['utm_campaign'] ? $row['utm_campaign'] : '(none)';
            $key = $plp_path . '|' . $camp;
            if (!isset($agg[$key])) {
                $agg[$key] = ['plp' => $plp_path, 'utm_campaign' => $camp, 'clicks' => 0];
            }
            $agg[$key]['clicks']++;
        }

        $filename = 'gowptracker_clicks_' . date('Ymd_His') . '.csv';
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename=' . $filename);
        $output = fopen('php://output', 'w');
        fputcsv($output, ['PLP', 'Campaign', 'Clicks']);
        if ($agg) {
            foreach ($agg as $row) {
                fputcsv($output, [$row['plp'], $row['utm_campaign'], $row['clicks']]);
            }
        }
        fclose($output);
        exit;
    }
}
add_action('load-toplevel_page_gowptracker-admin', 'gowptracker_handle_go_csv_export');
add_action('load-toplevel_page_gowptracker-admin', 'gowptracker_handle_go_actions');


/**
 * Renders the admin page for the GO tracker.
 */
/**
 * Handles actions for the GO tracker admin page, like resetting stats.
 */
function gowptracker_handle_go_actions() {
    if (isset($_POST['gowptracker_action']) && $_POST['gowptracker_action'] === 'reset_go_stats') {
        // Check nonce and user capabilities
        if (!isset($_POST['gowptracker_reset_go_stats_nonce']) || !wp_verify_nonce($_POST['gowptracker_reset_go_stats_nonce'], 'gowptracker_reset_go_stats_action')) {
            wp_die('Invalid nonce.');
        }
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized action.');
        }

        global $wpdb;
        $table_name = $wpdb->prefix . 'go_clicks';
        
        // Truncate the table
        $wpdb->query("TRUNCATE TABLE {$table_name}");

        // Redirect to avoid form resubmission
        wp_redirect(admin_url('admin.php?page=gowptracker-admin&stats_reset=1'));
        exit;
    }
}

/**
 * Renders the admin page for the GO tracker.
 */
function gowptracker_render_go_admin_page() {
    if (!current_user_can('manage_options')) {
        wp_die('Unauthorized');
    }

    global $wpdb;
    $table = $wpdb->prefix . 'go_clicks';
    $since = date('Y-m-d H:i:s', strtotime('-7 days'));
    $rows = $wpdb->get_results(
        $wpdb->prepare("SELECT referrer, utm_campaign FROM $table WHERE ts >= %s", $since),
        ARRAY_A
    );

    // Aggregate data by PLP path and campaign
    $grouped_data = [];
    foreach ($rows as $row) {
        $ref = $row['referrer'];
        $parsed = wp_parse_url($ref);
        $plp_path = isset($parsed['path']) ? $parsed['path'] : '(unknown)';
        $camp = !empty($row['utm_campaign']) ? $row['utm_campaign'] : '(none)';

        if (!isset($grouped_data[$plp_path])) {
            $grouped_data[$plp_path] = ['total_clicks' => 0, 'campaigns' => []];
        }

        if (!isset($grouped_data[$plp_path]['campaigns'][$camp])) {
            $grouped_data[$plp_path]['campaigns'][$camp] = 0;
        }

        $grouped_data[$plp_path]['total_clicks']++;
        $grouped_data[$plp_path]['campaigns'][$camp]++;
    }

    // Sort PLPs by total clicks, descending
    uasort($grouped_data, function($a, $b) {
        return $b['total_clicks'] <=> $a['total_clicks'];
    });

    // Prepare data for the chart (aggregated by PLP only)
    $plp_counts = [];
    foreach ($grouped_data as $plp => $data) {
        $plp_counts[$plp] = $data['total_clicks'];
    }
    ?>
    <div class="wrap">
        <h1>GO Tracker – Clicks (Last 7 Days)</h1>

        <div style="margin-bottom: 1em;">
            <form method="post" style="display: inline-block;">
                <input type="submit" name="gowptracker_export_csv" class="button button-primary" value="Export CSV">
            </form>
            <form method="post" id="gowptracker-reset-form" style="display: inline-block; margin-left: 10px;">
                <?php wp_nonce_field( 'gowptracker_reset_go_stats_action', 'gowptracker_reset_go_stats_nonce' ); ?>
                <input type="hidden" name="gowptracker_action" value="reset_go_stats">
                <input type="submit" name="gowptracker_reset_stats" class="button button-secondary" value="Azzera Statistiche">
            </form>
        </div>

        <?php
        if (isset($_GET['stats_reset']) && $_GET['stats_reset'] == '1') {
            echo '<div class="notice notice-success is-dismissible"><p>Statistiche dei click azzerate con successo.</p></div>';
        }
        ?>

        <div id="gowptracker_report">
            <div style="width:60%; float:left; margin-right:2%;">
                <h2>Clicks by PLP & Campaign</h2>
                <table class="widefat">
                    <thead>
                        <tr>
                            <th>PLP (from Referrer)</th>
                            <th>Total Clicks</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($grouped_data): ?>
                            <?php foreach ($grouped_data as $plp => $data): ?>
                                <tr class="gowp-plp-row" style="cursor:pointer;" data-plp-id="<?php echo esc_attr(sanitize_title($plp)); ?>">
                                    <td><strong><?php echo esc_html($plp); ?></strong></td>
                                    <td><strong><?php echo intval($data['total_clicks']); ?></strong></td>
                                </tr>
                                <?php
                                // Sort campaigns by clicks
                                arsort($data['campaigns']);
                                foreach ($data['campaigns'] as $campaign => $clicks): ?>
                                    <tr class="gowp-campaign-row gowp-plp-<?php echo esc_attr(sanitize_title($plp)); ?>" style="display:none; background-color:#f9f9f9;">
                                        <td style="padding-left:30px;"><?php echo esc_html($campaign); ?></td>
                                        <td><?php echo intval($clicks); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr><td colspan="2">No data available.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            <div style="width:38%; float:left;">
                <h2>Top PLPs</h2>
                <canvas id="gowptracker_chart" height="200"></canvas>
            </div>
        </div>

    </div>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
    document.addEventListener("DOMContentLoaded", function() {
        console.log('GoWPTracker: Admin page script loaded.'); // Debug log

        // Reset form confirmation logic
        const resetForm = document.getElementById('gowptracker-reset-form');
        if (resetForm) {
            console.log('GoWPTracker: Reset form found. Attaching listener.'); // Debug log
            resetForm.addEventListener('submit', function(e) {
                console.log('GoWPTracker: Reset form submitted.'); // Debug log
                if (!confirm('Sei sicuro di voler azzerare tutte le statistiche dei click? Questa azione è irreversibile.')) {
                    console.log('GoWPTracker: Reset cancelled by user.'); // Debug log
                    e.preventDefault();
                } else {
                    console.log('GoWPTracker: Reset confirmed by user. Proceeding with submission.'); // Debug log
                }
            });
        } else {
            console.error('GoWPTracker: Reset form not found.'); // Debug log
        }

        // Chart.js logic
        var ctx = document.getElementById("gowptracker_chart").getContext("2d");
        new Chart(ctx, {
            type: "bar",
            data: {
                labels: <?php echo json_encode(array_keys($plp_counts)); ?>,
                datasets: [{
                    label: "Clicks per PLP",
                    data: <?php echo json_encode(array_values($plp_counts)); ?>,
                    backgroundColor: "rgba(54, 162, 235, 0.5)",
                    borderColor: "rgba(54, 162, 235, 1)",
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                indexAxis: 'y',
                scales: {
                    x: {
                        beginAtZero: true,
                        ticks: { precision: 0 }
                    }
                }
            }
        });

        // Accordion logic for the table
        document.querySelectorAll('.gowp-plp-row').forEach(row => {
            row.addEventListener('click', () => {
                const plpId = row.dataset.plpId;
                document.querySelectorAll(`.gowp-plp-${plpId}`).forEach(campaignRow => {
                    campaignRow.style.display = campaignRow.style.display === 'none' ? 'table-row' : 'none';
                });
            });
        });
    });
    </script>
    <?php
}
