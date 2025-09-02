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
    $agg = [];
    foreach ($rows as $row) {
        $ref = $row['referrer'];
        $parsed = wp_parse_url($ref);
        $plp_path = isset($parsed['path']) ? $parsed['path'] : '(unknown)';
        $camp = !empty($row['utm_campaign']) ? $row['utm_campaign'] : '(none)';
        $key = $plp_path . '|' . $camp;
        if (!isset($agg[$key])) {
            $agg[$key] = ['plp' => $plp_path, 'utm_campaign' => $camp, 'clicks' => 0];
        }
        $agg[$key]['clicks']++;
    }
    arsort($agg);

    // Prepare data for the chart (aggregated by PLP only)
    $plp_counts = [];
    foreach ($agg as $row) {
        $plp = $row['plp'];
        if (!isset($plp_counts[$plp])) $plp_counts[$plp] = 0;
        $plp_counts[$plp] += $row['clicks'];
    }
    ?>
    <div class="wrap">
        <h1>GO Tracker – Clicks (Last 7 Days)</h1>

        <form method="post" style="margin-bottom: 1em;">
            <input type="submit" name="gowptracker_export_csv" class="button button-primary" value="Export CSV">
        </form>

        <div id="gowptracker_report">
            <div style="width:60%; float:left; margin-right:2%;">
                <h2>Clicks by PLP & Campaign</h2>
                <table class="widefat">
                    <thead>
                        <tr>
                            <th>PLP (from Referrer)</th>
                            <th>Campaign</th>
                            <th>Clicks</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($agg): ?>
                            <?php foreach ($agg as $row): ?>
                                <tr>
                                    <td><?php echo esc_html($row['plp']); ?></td>
                                    <td><?php echo esc_html($row['utm_campaign']); ?></td>
                                    <td><?php echo intval($row['clicks']); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr><td colspan="3">No data available.</td></tr>
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
    });
    </script>
    <?php
}
