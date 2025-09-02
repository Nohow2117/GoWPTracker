<?php
/*
Plugin Name: GoWPTracker
Description: Server-side tracking of outbound clicks from pre-landing pages (PLP) to e-commerce, with logging and redirect.
Version: 0.5.0
Author: Nohow2117
*/

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Main plugin class
class GoWPTracker {
    public function __construct() {
        add_action( 'init', [ $this, 'register_go_endpoint' ] );
        add_action( 'init', [ $this, 'register_split_endpoint' ] );
        add_action( 'admin_menu', [ $this, 'add_admin_page' ] );
    }

    public function render_split_tests_page() {
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }
        global $wpdb;
        $tests_table = $wpdb->prefix . 'go_split_tests';
        $variants_table = $wpdb->prefix . 'go_split_variants';

        // Handle create form
        if (isset($_POST['gowp_split_create'])) {
            check_admin_referer('gowp_split_create_nonce');
            $name = isset($_POST['name']) ? sanitize_text_field($_POST['name']) : '';
            $slug = isset($_POST['slug']) ? sanitize_title($_POST['slug']) : '';
            $status = isset($_POST['status']) ? 1 : 0;
            $variant_post_ids = isset($_POST['variant_post_id']) && is_array($_POST['variant_post_id']) ? array_map('absint', $_POST['variant_post_id']) : [];
            $variant_weights = isset($_POST['variant_weight']) && is_array($_POST['variant_weight']) ? array_map('absint', $_POST['variant_weight']) : [];
            $now = current_time('mysql');

            $errors = [];
            if (empty($name)) { $errors[] = 'Nome richiesto.'; }
            if (empty($slug)) { $errors[] = 'Slug richiesto.'; }
            // Ensure at least one valid variant
            $variants_to_save = [];
            if (!empty($variant_post_ids)) {
                $count_valid = 0;
                foreach ($variant_post_ids as $idx => $pid) {
                    if ($pid > 0) {
                        $w = isset($variant_weights[$idx]) && $variant_weights[$idx] > 0 ? $variant_weights[$idx] : 1;
                        $variants_to_save[] = [ 'post_id' => $pid, 'weight' => $w ];
                        $count_valid++;
                        if ($count_valid >= 10) { break; }
                    }
                }
            }
            if (empty($variants_to_save)) { $errors[] = 'Aggiungi almeno una variante.'; }
            // Check slug uniqueness
            $exists = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $tests_table WHERE slug = %s", $slug));
            if ($exists) { $errors[] = 'Slug già esistente.'; }

            if (empty($errors)) {
                $wpdb->insert($tests_table, [
                    'slug' => $slug,
                    'name' => $name,
                    'status' => $status,
                    'created_at' => $now,
                    'updated_at' => $now,
                ], [ '%s','%s','%d','%s','%s' ]);
                $test_id = $wpdb->insert_id;
                foreach ($variants_to_save as $v) {
                    $wpdb->insert($variants_table, [
                        'test_id' => $test_id,
                        'post_id' => $v['post_id'],
                        'weight' => $v['weight'],
                        'created_at' => $now,
                        'updated_at' => $now,
                    ], [ '%d','%d','%d','%s','%s' ]);
                }
                echo '<div class="notice notice-success"><p>Split test creato correttamente. URL: <code>' . esc_html(home_url('/split/' . $slug)) . '</code></p></div>';
            } else {
                echo '<div class="notice notice-error"><p>' . esc_html(implode(' ', $errors)) . '</p></div>';
            }
        }

        // Handle update form (Edit existing test)
        if (isset($_POST['gowp_split_update'])) {
            check_admin_referer('gowp_split_update_nonce');
            $test_id = isset($_POST['test_id']) ? absint($_POST['test_id']) : 0;
            $name = isset($_POST['name']) ? sanitize_text_field($_POST['name']) : '';
            $status = isset($_POST['status']) ? 1 : 0;
            $variant_post_ids = isset($_POST['variant_post_id']) && is_array($_POST['variant_post_id']) ? array_map('absint', $_POST['variant_post_id']) : [];
            $variant_weights = isset($_POST['variant_weight']) && is_array($_POST['variant_weight']) ? array_map('absint', $_POST['variant_weight']) : [];
            $now = current_time('mysql');

            $errors = [];
            if ($test_id <= 0) { $errors[] = 'Test non valido.'; }
            if (empty($name)) { $errors[] = 'Nome richiesto.'; }

            $test = $wpdb->get_row($wpdb->prepare("SELECT * FROM $tests_table WHERE id = %d", $test_id), ARRAY_A);
            if (!$test) { $errors[] = 'Test non trovato.'; }

            // Build variants
            $variants_to_save = [];
            if (!empty($variant_post_ids)) {
                $count_valid = 0;
                foreach ($variant_post_ids as $idx => $pid) {
                    if ($pid > 0) {
                        $w = isset($variant_weights[$idx]) && $variant_weights[$idx] > 0 ? $variant_weights[$idx] : 1;
                        $variants_to_save[] = [ 'post_id' => $pid, 'weight' => $w ];
                        $count_valid++;
                        if ($count_valid >= 10) { break; }
                    }
                }
            }
            if (empty($variants_to_save)) { $errors[] = 'Aggiungi almeno una variante.'; }

            if (empty($errors)) {
                // Update test (keep slug immutable to avoid breaking URLs)
                $wpdb->update(
                    $tests_table,
                    [ 'name' => $name, 'status' => $status, 'updated_at' => $now ],
                    [ 'id' => $test_id ],
                    [ '%s','%d','%s' ],
                    [ '%d' ]
                );
                // Replace variants
                $wpdb->delete($variants_table, [ 'test_id' => $test_id ], [ '%d' ]);
                foreach ($variants_to_save as $v) {
                    $wpdb->insert($variants_table, [
                        'test_id' => $test_id,
                        'post_id' => $v['post_id'],
                        'weight' => $v['weight'],
                        'created_at' => $now,
                        'updated_at' => $now,
                    ], [ '%d','%d','%d','%s','%s' ]);
                }
                echo '<div class="notice notice-success"><p>Split test aggiornato correttamente.</p></div>';
            } else {
                echo '<div class="notice notice-error"><p>' . esc_html(implode(' ', $errors)) . '</p></div>';
            }
        }

        // Fetch tests with variant counts
        $tests = $wpdb->get_results("SELECT t.id, t.slug, t.name, t.status, (
            SELECT COUNT(*) FROM $variants_table v WHERE v.test_id = t.id
        ) as variants FROM $tests_table t ORDER BY t.id DESC", ARRAY_A);

        echo '<div class="wrap">';
        echo '<h1>Split Tests</h1>';
        // Listing
        echo '<h2 class="title">Lista Test</h2>';
        echo '<table class="widefat"><thead><tr><th>Slug</th><th>Nome</th><th>Stato</th><th># Varianti</th><th>URL</th><th>Azioni</th></tr></thead><tbody>';
        if ($tests) {
            foreach ($tests as $t) {
                $url = home_url('/split/' . $t['slug']);
                echo '<tr>';
                echo '<td>' . esc_html($t['slug']) . '</td>';
                echo '<td>' . esc_html($t['name']) . '</td>';
                echo '<td>' . ($t['status'] ? 'Attivo' : 'Disattivo') . '</td>';
                echo '<td>' . intval($t['variants']) . '</td>';
                echo '<td><code>' . esc_html($url) . '</code></td>';
                $edit_link = add_query_arg([ 'page' => 'gowptracker-split-tests', 'edit' => intval($t['id']) ], admin_url('admin.php'));
                echo '<td><a class="button button-small" href="' . esc_url($edit_link) . '">Modifica</a></td>';
                echo '</tr>';
            }
        } else {
            echo '<tr><td colspan="6">Nessun test presente</td></tr>';
        }
        echo '</tbody></table>';

        $is_edit = isset($_GET['edit']) ? absint($_GET['edit']) : 0;
        if ($is_edit) {
            // Edit form
            $test = $wpdb->get_row($wpdb->prepare("SELECT * FROM $tests_table WHERE id = %d", $is_edit), ARRAY_A);
            if ($test) {
                $vars = $wpdb->get_results($wpdb->prepare("SELECT id, post_id, weight FROM $variants_table WHERE test_id = %d ORDER BY id ASC", $is_edit), ARRAY_A);
                echo '<h2 class="title" style="margin-top:2em;">Modifica Test: ' . esc_html($test['slug']) . '</h2>';
                echo '<form method="post" id="gowp_split_form" data-max="10">';
                wp_nonce_field('gowp_split_update_nonce');
                echo '<input type="hidden" name="test_id" value="' . intval($is_edit) . '">';
                echo '<table class="form-table">';
                echo '<tr><th>Slug</th><td><code>' . esc_html($test['slug']) . '</code> <small>(immutabile)</small></td></tr>';
                echo '<tr><th><label for="gowp_name">Nome</label></th><td><input type="text" id="gowp_name" name="name" class="regular-text" value="' . esc_attr($test['name']) . '" required></td></tr>';
                $checked = $test['status'] ? 'checked' : '';
                echo '<tr><th><label for="gowp_status">Attivo</label></th><td><label><input type="checkbox" id="gowp_status" name="status" value="1" ' . $checked . '> Attivo</label></td></tr>';
                echo '</table>';

                echo '<h3>Varianti</h3>';
                echo '<div id="gowp_variants">';
                if ($vars) {
                    foreach ($vars as $v) {
                        echo '<div class="gowp-variant-row" style="display:flex; gap:12px; align-items:center; margin-bottom:8px;">';
                        echo '<div>';
                        wp_dropdown_pages([
                            'name' => 'variant_post_id[]',
                            'show_option_none' => '— Seleziona pagina —',
                            'option_none_value' => '0',
                            'selected' => intval($v['post_id']),
                            'echo' => 1,
                        ]);
                        echo '</div>';
                        echo '<div><label>Peso <input type="number" class="gowp-weight" name="variant_weight[]" value="' . intval($v['weight']) . '" min="1" style="width:80px;"></label></div>';
                        echo '<button type="button" class="button button-secondary gowp-remove-variant">Rimuovi</button>';
                        echo '</div>';
                    }
                }
                echo '</div>';
                // Controls
                echo '<p>';
                echo '<button type="button" id="gowp_add_variant" class="button">+ Aggiungi variante</button> ';
                echo '<button type="button" id="gowp_equalize" class="button">Equalizza pesi</button> ';
                echo '</p>';
                echo '<p><button type="submit" name="gowp_split_update" class="button button-primary">Salva Modifiche</button></p>';
                echo '</form>';
            }
        } else {
            // Create form
            echo '<h2 class="title" style="margin-top:2em;">Crea Nuovo Test</h2>';
            echo '<form method="post" id="gowp_split_form" data-max="10">';
            wp_nonce_field('gowp_split_create_nonce');
            echo '<table class="form-table">';
            echo '<tr><th><label for="gowp_name">Nome</label></th><td><input type="text" id="gowp_name" name="name" class="regular-text" required></td></tr>';
            echo '<tr><th><label for="gowp_slug">Slug</label></th><td><input type="text" id="gowp_slug" name="slug" class="regular-text" placeholder="es. summer-sale" required></td></tr>';
            echo '<tr><th><label for="gowp_status">Attivo</label></th><td><label><input type="checkbox" id="gowp_status" name="status" value="1" checked> Attivo</label></td></tr>';
            echo '</table>';

            echo '<h3>Varianti</h3>';
            echo '<div id="gowp_variants">';
            for ($i = 0; $i < 3; $i++) {
                echo '<div class="gowp-variant-row" style="display:flex; gap:12px; align-items:center; margin-bottom:8px;">';
                echo '<div>';
                wp_dropdown_pages([
                    'name' => 'variant_post_id[]',
                    'show_option_none' => '— Seleziona pagina —',
                    'option_none_value' => '0',
                    'echo' => 1,
                ]);
                echo '</div>';
                echo '<div><label>Peso <input type="number" class="gowp-weight" name="variant_weight[]" value="1" min="1" style="width:80px;"></label></div>';
                echo '<button type="button" class="button button-secondary gowp-remove-variant">Rimuovi</button>';
                echo '</div>';
            }
            echo '</div>';
            // Controls
            echo '<p>';
            echo '<button type="button" id="gowp_add_variant" class="button">+ Aggiungi variante</button> ';
            echo '<button type="button" id="gowp_equalize" class="button">Equalizza pesi</button> ';
            echo '</p>';
            echo '<p><button type="submit" name="gowp_split_create" class="button button-primary">Crea Split Test</button></p>';
            echo '</form>';
        }

        // Hidden template for new variant row
        echo '<template id="gowp_variant_tpl">';
        echo '<div class="gowp-variant-row" style="display:flex; gap:12px; align-items:center; margin-bottom:8px;">';
        echo '<div>';
        // dropdown without selection
        wp_dropdown_pages([
            'name' => 'variant_post_id[]',
            'show_option_none' => '— Seleziona pagina —',
            'option_none_value' => '0',
            'echo' => 1,
        ]);
        echo '</div>';
        echo '<div><label>Peso <input type="number" class="gowp-weight" name="variant_weight[]" value="1" min="1" style="width:80px;"></label></div>';
        echo '<button type="button" class="button button-secondary gowp-remove-variant">Rimuovi</button>';
        echo '</div>';
        echo '</template>';

        // Inline JS to manage add/remove/equalize
        echo <<<GOWPJS
<script>
document.addEventListener("DOMContentLoaded", function(){
  try {
    console.log("[GoWPTracker] Split UI init");
    const form = document.getElementById("gowp_split_form");
    if (!form) { console.log("[GoWPTracker] No form found"); return; }
    const max = parseInt(form.getAttribute("data-max")) || 10;
    const container = document.getElementById("gowp_variants");
    const tpl = document.getElementById("gowp_variant_tpl");
    const addBtn = document.getElementById("gowp_add_variant");
    const eqBtn = document.getElementById("gowp_equalize");
    console.log("[GoWPTracker] Buttons:", {addBtn: !!addBtn, eqBtn: !!eqBtn});

    function bindRow(row){
      const rm = row.querySelector(".gowp-remove-variant");
      if (rm){
        rm.addEventListener("click", function(){
          console.log("[GoWPTracker] Remove variant row");
          row.remove();
        });
      }
    }
    // bind existing
    container.querySelectorAll(".gowp-variant-row").forEach(bindRow);

    if (addBtn){
      addBtn.addEventListener("click", function(){
        const count = container.querySelectorAll(".gowp-variant-row").length;
        console.log("[GoWPTracker] Add variant click, current:", count);
        if (count >= max) { console.log("[GoWPTracker] Max variants reached:", max); return; }
        const node = tpl.content.firstElementChild.cloneNode(true);
        bindRow(node);
        container.appendChild(node);
        console.log("[GoWPTracker] Variant added. New count:", container.querySelectorAll(".gowp-variant-row").length);
      });
    }
    if (eqBtn){
      console.log("[GoWPTracker] Bind equalize click");
      eqBtn.addEventListener("click", function(){
        const weights = container.querySelectorAll(".gowp-weight");
        const n = weights.length;
        const base = n > 0 ? Math.floor(100 / n) : 0;
        console.log("[GoWPTracker] Equalize click. Found weights:", n, " base:", base);
        weights.forEach(function(inp, idx){
          try {
            const before = inp.value;
            inp.value = base; // update property to percentage
            inp.setAttribute("value", String(base)); // update attribute for some UIs
            // Fire events so any listeners/frameworks pick up the change
            inp.dispatchEvent(new Event("input", { bubbles: true }));
            inp.dispatchEvent(new Event("change", { bubbles: true }));
            if (window.jQuery) { window.jQuery(inp).trigger("input").trigger("change"); }
            // Visual feedback
            const oldBg = inp.style.backgroundColor;
            inp.style.transition = "background-color 300ms ease";
            inp.style.backgroundColor = "#fff3cd"; // light yellow
            setTimeout(function(){ inp.style.backgroundColor = oldBg || ""; }, 500);
            // Log per input
            console.log("[GoWPTracker] Weight", idx, "before:", before, "after:", inp.value);
          } catch(e) { console.warn("[GoWPTracker] Set weight error", e); }
        });
        console.log("[GoWPTracker] Equalize done.");
      });
    }
  } catch(e) {
    console.error("[GoWPTracker] Split UI error:", e);
  }
});
</script>
GOWPJS;

        echo '</div>';

        // Reporting section
        echo '<hr style="margin:2em 0;">';
        echo '<h2 class="title">Report</h2>';
        // CSV export handler
        if (isset($_POST['gowp_split_export_csv'])) {
            check_admin_referer('gowp_split_report_nonce');
            $report_slug = isset($_POST['report_slug']) ? sanitize_title($_POST['report_slug']) : '';
            $days = isset($_POST['report_days']) ? max(1, absint($_POST['report_days'])) : 7;
            $since = date('Y-m-d H:i:s', strtotime('-' . $days . ' days'));
            $split_hits = $wpdb->prefix . 'go_split_hits';
            // Get variant mapping
            $map = $wpdb->get_results($wpdb->prepare(
                "SELECT v.id as variant_id, v.post_id FROM $variants_table v JOIN $tests_table t ON t.id = v.test_id WHERE t.slug = %s",
                $report_slug
            ), ARRAY_A);
            $variant_to_post = [];
            foreach ($map as $m) { $variant_to_post[intval($m['variant_id'])] = intval($m['post_id']); }
            $rows = $wpdb->get_results($wpdb->prepare(
                "SELECT variant_id, COUNT(*) as clicks FROM $split_hits WHERE test_slug = %s AND ts >= %s GROUP BY variant_id",
                $report_slug, $since
            ), ARRAY_A);
            // Output CSV
            nocache_headers();
            header('Content-Type: text/csv; charset=utf-8');
            header('Content-Disposition: attachment; filename=split_report_' . $report_slug . '_' . $days . 'd.csv');
            $out = fopen('php://output', 'w');
            fputcsv($out, ['test_slug','variant_id','post_id','post_title','clicks']);
            foreach ($rows as $r) {
                $vid = intval($r['variant_id']);
                $pid = isset($variant_to_post[$vid]) ? $variant_to_post[$vid] : 0;
                $title = $pid ? get_the_title($pid) : '';
                fputcsv($out, [$report_slug, $vid, $pid, $title, intval($r['clicks'])]);
            }
            fclose($out);
            exit;
        }

        // Report UI: selector and summary table
        $all_tests = $wpdb->get_results("SELECT slug, name FROM $tests_table ORDER BY id DESC", ARRAY_A);
        echo '<form method="get" style="margin-bottom:1em;">';
        // Preserve admin page
        echo '<input type="hidden" name="page" value="gowptracker-split-tests">';
        echo '<label style="margin-right:8px;">Test: <select name="report_slug">';
        $selected_slug = isset($_GET['report_slug']) ? sanitize_title($_GET['report_slug']) : '';
        foreach ($all_tests as $t) {
            $sel = selected($selected_slug, $t['slug'], false);
            echo '<option value="' . esc_attr($t['slug']) . '" ' . $sel . '>' . esc_html($t['name'] . ' (' . $t['slug'] . ')') . '</option>';
        }
        echo '</select></label>';
        $selected_days = isset($_GET['report_days']) ? max(1, absint($_GET['report_days'])) : 7;
        echo '<label style="margin-right:8px;">Periodo: <select name="report_days">';
        foreach ([7,30] as $opt) {
            $sel = selected($selected_days, $opt, false);
            echo '<option value="' . $opt . '" ' . $sel . '>' . $opt . ' giorni</option>';
        }
        echo '</select></label>';
        echo '<button class="button">Aggiorna</button>';
        echo '</form>';

        if (!empty($selected_slug)) {
            $since = date('Y-m-d H:i:s', strtotime('-' . $selected_days . ' days'));
            $split_hits = $wpdb->prefix . 'go_split_hits';
            $rows = $wpdb->get_results($wpdb->prepare(
                "SELECT variant_id, COUNT(*) as clicks FROM $split_hits WHERE test_slug = %s AND ts >= %s GROUP BY variant_id",
                $selected_slug, $since
            ), ARRAY_A);
            // Map variant -> post
            $map = $wpdb->get_results($wpdb->prepare(
                "SELECT v.id as variant_id, v.post_id FROM $variants_table v JOIN $tests_table t ON t.id = v.test_id WHERE t.slug = %s",
                $selected_slug
            ), ARRAY_A);
            $variant_to_post = [];
            foreach ($map as $m) { $variant_to_post[intval($m['variant_id'])] = intval($m['post_id']); }
            echo '<table class="widefat"><thead><tr><th>Variante</th><th>Pagina</th><th>Clicks</th></tr></thead><tbody>';
            if ($rows) {
                foreach ($rows as $r) {
                    $vid = intval($r['variant_id']);
                    $pid = isset($variant_to_post[$vid]) ? $variant_to_post[$vid] : 0;
                    $title = $pid ? get_the_title($pid) : '—';
                    $plink = $pid ? get_permalink($pid) : '';
                    echo '<tr>';
                    echo '<td>#' . $vid . '</td>';
                    echo '<td>' . ($plink ? '<a href="' . esc_url($plink) . '" target="_blank">' . esc_html($title) . '</a>' : esc_html($title)) . '</td>';
                    echo '<td>' . intval($r['clicks']) . '</td>';
                    echo '</tr>';
                }
            } else {
                echo '<tr><td colspan="3">Nessun dato nel periodo selezionato</td></tr>';
            }
            echo '</tbody></table>';

            // CSV export form
            echo '<form method="post" style="margin-top:1em;">';
            wp_nonce_field('gowp_split_report_nonce');
            echo '<input type="hidden" name="report_slug" value="' . esc_attr($selected_slug) . '">';
            echo '<input type="hidden" name="report_days" value="' . esc_attr($selected_days) . '">';
            echo '<button type="submit" name="gowp_split_export_csv" class="button button-secondary">Esporta CSV</button>';
            echo '</form>';
        }

        echo '</div>';
    }

    public function add_admin_page() {
        add_menu_page(
            'GO Tracker',
            'GO Tracker',
            'manage_options',
            'gowptracker-admin',
            [ $this, 'render_admin_page' ],
            'dashicons-chart-bar',
            80
        );
        // Split Tests submenu
        add_submenu_page(
            'gowptracker-admin',
            'Split Tests',
            'Split Tests',
            'manage_options',
            'gowptracker-split-tests',
            [ $this, 'render_split_tests_page' ]
        );
    }

    public function render_admin_page() {
        global $wpdb;
        $table = $wpdb->prefix . 'go_clicks';
        $since = date('Y-m-d H:i:s', strtotime('-7 days'));
        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT referrer, utm_campaign FROM $table WHERE ts >= %s",
                $since
            ),
            ARRAY_A
        );
        // Raggruppa per percorso referrer e campagna
        $agg = [];
        foreach ($rows as $row) {
            $ref = $row['referrer'];
            $parsed = wp_parse_url($ref);
            $plp_path = isset($parsed['path']) ? $parsed['path'] : '(nessun referrer)';
            $camp = $row['utm_campaign'];
            $key = $plp_path . '|' . $camp;
            if (!isset($agg[$key])) {
                $agg[$key] = ['plp' => $plp_path, 'utm_campaign' => $camp, 'clicks' => 0];
            }
            $agg[$key]['clicks']++;
        }
        // Pulsante esporta CSV
        echo '<div class="wrap"><h1>GO Tracker – Clicks ultimi 7 giorni</h1>';
        echo '<form method="post"><input type="submit" name="gowptracker_export_csv" class="button button-primary" value="Esporta CSV"></form>';
        echo '<table class="widefat"><thead><tr><th>PLP (da referrer)</th><th>Campagna</th><th>Clicks</th></tr></thead><tbody>';
        if ($agg) {
            foreach ($agg as $row) {
                echo '<tr><td>' . esc_html($row['plp']) . '</td><td>' . esc_html($row['utm_campaign']) . '</td><td>' . intval($row['clicks']) . '</td></tr>';
            }
        } else {
            echo '<tr><td colspan="3">Nessun dato</td></tr>';
        }
        echo '</tbody></table>';
        // Chart.js CDN
        echo '<canvas id="gowptracker_chart" height="120"></canvas>';
        echo '<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>';
        // Prepara i dati per il grafico (aggregazione per PLP)
        $plp_counts = [];
        foreach ($agg as $row) {
            $plp = $row['plp'];
            if (!isset($plp_counts[$plp])) $plp_counts[$plp] = 0;
            $plp_counts[$plp] += $row['clicks'];
        }
        echo '<script>
        document.addEventListener("DOMContentLoaded", function() {
            var ctx = document.getElementById("gowptracker_chart").getContext("2d");
            new Chart(ctx, {
                type: "bar",
                data: {
                    labels: ' . json_encode(array_keys($plp_counts)) . ',
                    datasets: [{
                        label: "Clicks per PLP (ultimi 7 giorni)",
                        data: ' . json_encode(array_values($plp_counts)) . ',
                        backgroundColor: "rgba(54, 162, 235, 0.5)",
                        borderColor: "rgba(54, 162, 235, 1)",
                        borderWidth: 1
                    }]
                },
                options: {
                    responsive: true,
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: { precision:0 }
                        }
                    }
                }
            });
        });
        </script>';

        // Prepara i dati per il grafico (aggregazione per PLP)
        $plp_counts = [];
        foreach ($agg as $row) {
            $plp = $row['plp'];
            if (!isset($plp_counts[$plp])) $plp_counts[$plp] = 0;
            $plp_counts[$plp] += $row['clicks'];
        }
        echo '<script>var gowptracker_labels = ' . json_encode(array_keys($plp_counts)) . ';var gowptracker_clicks = ' . json_encode(array_values($plp_counts)) . ';</script>';
        echo '<script>
        document.addEventListener("DOMContentLoaded", function() {
            var ctx = document.getElementById("gowptracker_chart").getContext("2d");
            new Chart(ctx, {
                type: "bar",
                data: {
                    labels: gowptracker_labels,
                    datasets: [{
                        label: "Clicks per PLP (ultimi 7 giorni)",
                        data: gowptracker_clicks,
                        backgroundColor: "rgba(54, 162, 235, 0.5)",
                        borderColor: "rgba(54, 162, 235, 1)",
                        borderWidth: 1
                    }]
                },
                options: {
                    responsive: true,
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: { precision:0 }
                        }
                    }
                }
            });
        });
        </script>';
        // Gestione esportazione CSV
        if (isset($_POST['gowptracker_export_csv'])) {
            $filename = 'gowptracker_clicks_' . date('Ymd_His') . '.csv';
            header('Content-Type: text/csv; charset=utf-8');
            header('Content-Disposition: attachment; filename=' . $filename);
            $output = fopen('php://output', 'w');
            fputcsv($output, ['PLP', 'Campagna', 'Clicks']);
            foreach ($agg as $row) {
                fputcsv($output, [$row['plp'], $row['utm_campaign'], $row['clicks']]);
            }
            fclose($output);
            exit;
        }
    }

    public static function activate_plugin() {
        error_log('GoWPTracker: activate_plugin START');
        global $wpdb;
        $table_name = $wpdb->prefix . 'go_clicks';
        $charset_collate = $wpdb->get_charset_collate();
        error_log('GoWPTracker: before require_once');
        require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
        error_log('GoWPTracker: after require_once');
        $sql = "CREATE TABLE $table_name (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            ts DATETIME NOT NULL,
            ip VARBINARY(16) NOT NULL,
            ua TEXT,
            referrer TEXT,
            dest TEXT,
            dest_host VARCHAR(191),
            plp VARCHAR(191),
            utm_source VARCHAR(191),
            utm_medium VARCHAR(191),
            utm_campaign VARCHAR(191),
            utm_content VARCHAR(191),
            utm_term VARCHAR(191),
            fbclid VARCHAR(191),
            gclid VARCHAR(191),
            PRIMARY KEY (id),
            KEY idx_ts (ts),
            KEY idx_plp (plp),
            KEY idx_dest_host (dest_host)
        ) $charset_collate;";
        error_log('GoWPTracker: before dbDelta');
        try {
            dbDelta($sql);
            error_log('GoWPTracker: after dbDelta');
        } catch (Exception $e) {
            error_log('GoWPTracker: EXCEPTION - ' . $e->getMessage());
        }
        // Split Testing Tables
        // go_split_tests
        $split_tests_table = $wpdb->prefix . 'go_split_tests';
        $sql_split_tests = "CREATE TABLE $split_tests_table (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            slug VARCHAR(191) NOT NULL,
            name VARCHAR(191) NOT NULL,
            status TINYINT(1) NOT NULL DEFAULT 1,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY uniq_slug (slug)
        ) $charset_collate;";
        dbDelta($sql_split_tests);

        // go_split_variants
        $split_variants_table = $wpdb->prefix . 'go_split_variants';
        $sql_split_variants = "CREATE TABLE $split_variants_table (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            test_id BIGINT UNSIGNED NOT NULL,
            post_id BIGINT UNSIGNED NOT NULL,
            weight INT UNSIGNED NOT NULL DEFAULT 1,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            KEY idx_test (test_id),
            KEY idx_post (post_id)
        ) $charset_collate;";
        dbDelta($sql_split_variants);

        // go_split_hits
        $split_hits_table = $wpdb->prefix . 'go_split_hits';
        $sql_split_hits = "CREATE TABLE $split_hits_table (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            ts DATETIME NOT NULL,
            test_slug VARCHAR(191) NOT NULL,
            variant_id BIGINT UNSIGNED NOT NULL,
            client_id VARCHAR(191) NULL,
            ip VARBINARY(16) NOT NULL,
            ua TEXT NULL,
            referrer TEXT NULL,
            PRIMARY KEY (id),
            KEY idx_ts (ts),
            KEY idx_test (test_slug),
            KEY idx_variant (variant_id)
        ) $charset_collate;";
        dbDelta($sql_split_hits);

        // Save/upgrade plugin version
        update_option('gowptracker_version', '0.5.0');
        // Ensure rewrite rules are flushed so /split/{slug} becomes active after activation
        if (function_exists('flush_rewrite_rules')) {
            flush_rewrite_rules();
        }
        error_log('GoWPTracker: activate_plugin END');
    }

    public static function maybe_upgrade() {
        $current = '0.5.0';
        $installed = get_option('gowptracker_version');
        if ($installed !== $current) {
            // Re-run activation logic to apply dbDelta changes safely
            self::activate_plugin();
            update_option('gowptracker_version', $current);
        }
    }

    public function register_go_endpoint() {
        add_rewrite_rule( '^go/?$', 'index.php?gowptracker_go=1', 'top' );
        add_rewrite_tag( '%gowptracker_go%', '1' );
        add_action( 'template_redirect', [ $this, 'handle_go_redirect' ], 9 );
    }

    public function register_split_endpoint() {
        add_rewrite_rule( '^split/([^/]+)/?$', 'index.php?gowptracker_split=$matches[1]', 'top' );
        add_rewrite_tag( '%gowptracker_split%', '([^&]+)' );
        add_action( 'template_redirect', [ $this, 'handle_split_redirect' ], 9 );
    }

    public function handle_split_redirect() {
        $slug = get_query_var('gowptracker_split');
        if (empty($slug)) {
            return;
        }
        // IMPORTANT: Do NOT block HEAD or bots on /split.
        // Ad/social crawlers (Meta, Google, etc.) must be able to access landing pages.
        global $wpdb;
        $slug = sanitize_title($slug);
        $tests_table = $wpdb->prefix . 'go_split_tests';
        $variants_table = $wpdb->prefix . 'go_split_variants';
        $test = $wpdb->get_row(
            $wpdb->prepare("SELECT id, slug FROM $tests_table WHERE slug = %s AND status = 1", $slug),
            ARRAY_A
        );
        if (!$test) {
            status_header(404);
            exit('Not Found');
        }
        $variants = $wpdb->get_results(
            $wpdb->prepare("SELECT id, post_id, weight FROM $variants_table WHERE test_id = %d", intval($test['id'])),
            ARRAY_A
        );
        if (!$variants) {
            status_header(404);
            exit('Not Found');
        }
        // Keep only published posts
        $valid = [];
        foreach ($variants as $v) {
            $post_id = intval($v['post_id']);
            if (get_post_status($post_id) === 'publish') {
                $valid[] = $v;
            }
        }
        if (empty($valid)) {
            status_header(404);
            exit('Not Found');
        }
        // Sticky assignment via cookie if present and still valid
        $cookie_name = 'GoWPTrackerSplit_' . $slug;
        if (isset($_COOKIE[$cookie_name])) {
            $cookie_variant_id = absint($_COOKIE[$cookie_name]);
            foreach ($valid as $v) {
                if (intval($v['id']) === $cookie_variant_id) {
                    $choice = $v;
                    // Reuse sticky without reassigning
                    $dest = get_permalink(intval($choice['post_id']));
                    if (!empty($dest)) {
                        wp_redirect($dest, 302);
                        exit;
                    }
                }
            }
        }
        // Weighted rotation selection
        $total_weight = 0;
        foreach ($valid as $v) { $total_weight += max(1, intval($v['weight'])); }
        $r = mt_rand(1, max(1, $total_weight));
        $acc = 0; $choice = $valid[0];
        foreach ($valid as $v) {
            $acc += max(1, intval($v['weight']));
            if ($r <= $acc) { $choice = $v; break; }
        }
        // Set sticky cookie for 30 days
        $expire = time() + 60 * 60 * 24 * 30;
        setcookie($cookie_name, strval(intval($choice['id'])), $expire, '/', '', is_ssl(), true);
        $dest = get_permalink(intval($choice['post_id']));
        if (empty($dest)) {
            status_header(404);
            exit('Not Found');
        }
        // Step 5: Logging hit and propagating UTM/query
        // Build merged query by propagating all incoming params except internal WP/query vars
        $incoming = $_GET;
        unset($incoming['gowptracker_split']);
        // Merge into destination URL
        if (!empty($incoming)) {
            $parsed_dest = wp_parse_url($dest);
            $dest_query = [];
            if (isset($parsed_dest['query'])) { parse_str($parsed_dest['query'], $dest_query); }
            $merged_query = array_merge($dest_query, array_map('sanitize_text_field', $incoming));
            $query_str = http_build_query($merged_query);
            $base = (isset($parsed_dest['scheme']) ? $parsed_dest['scheme'] : 'https') . '://' . $parsed_dest['host'];
            if (isset($parsed_dest['port'])) { $base .= ':' . $parsed_dest['port']; }
            $base .= isset($parsed_dest['path']) ? $parsed_dest['path'] : '';
            $dest = $base . (strlen($query_str) ? ('?' . $query_str) : '');
        }

        // Prepare logging data
        $split_hits_table = $wpdb->prefix . 'go_split_hits';
        $ts = current_time('mysql');
        $ip = isset($_SERVER['REMOTE_ADDR']) ? inet_pton(sanitize_text_field($_SERVER['REMOTE_ADDR'])) : null;
        $ua = isset($_SERVER['HTTP_USER_AGENT']) ? sanitize_text_field($_SERVER['HTTP_USER_AGENT']) : '';
        $referrer = isset($_SERVER['HTTP_REFERER']) ? esc_url_raw($_SERVER['HTTP_REFERER']) : '';
        // Client ID cookie (anonymous)
        $cid_cookie = 'GoWPTrackerCID';
        if (empty($_COOKIE[$cid_cookie])) {
            $cid = wp_hash(uniqid('gowp', true));
            // 1 year
            setcookie($cid_cookie, $cid, time() + 60*60*24*365, '/', '', is_ssl(), true);
        } else {
            $cid = sanitize_text_field($_COOKIE[$cid_cookie]);
        }
        // Insert log
        $wpdb->insert(
            $split_hits_table,
            [
                'ts' => $ts,
                'test_slug' => $slug,
                'variant_id' => intval($choice['id']),
                'client_id' => $cid,
                'ip' => $ip,
                'ua' => $ua,
                'referrer' => $referrer,
            ],
            [ '%s','%s','%d','%s','%s','%s','%s' ]
        );

        wp_redirect($dest, 302);
        exit;
    }

    public function handle_go_redirect() {
        // DEBUG: logga tutto ciò che arriva
        error_log('REQUEST_URI: ' . $_SERVER['REQUEST_URI']);
        error_log('QUERY_STRING: ' . $_SERVER['QUERY_STRING']);
        error_log('GET: ' . print_r($_GET, true));
        if ( get_query_var( 'gowptracker_go' ) ) {
            // Blocca richieste HEAD e bot PRIMA di ogni logica
            if ($_SERVER['REQUEST_METHOD'] === 'HEAD') {
                error_log('GoWPTracker: BLOCCO HEAD');
                status_header(403);
                exit('Forbidden');
            }
            $ua_check = isset($_SERVER['HTTP_USER_AGENT']) ? strtolower($_SERVER['HTTP_USER_AGENT']) : '';
            $bot_signals = ['bot','crawl','spider','slurp','facebookexternalhit','mediapartners-google','adsbot','bingpreview'];
            foreach ($bot_signals as $signal) {
                if (strpos($ua_check, $signal) !== false) {
                    error_log('GoWPTracker: BLOCCO BOT - UA: ' . $ua_check);
                    status_header(403);
                    exit('Forbidden');
                }
            }
            $allowed_domains = [
                'milano-bags.com',
                // aggiungi altri domini consentiti qui
            ];
            $dest = isset($_GET['dest']) ? esc_url_raw($_GET['dest']) : '';
            if (empty($dest)) {
                wp_die('Errore: parametro dest mancante.');
            }
            $parsed = wp_parse_url($dest);
            $scheme = isset($parsed['scheme']) ? strtolower($parsed['scheme']) : '';
            if ($scheme !== 'http' && $scheme !== 'https') {
                wp_die('Protocollo di destinazione non consentito.');
            }
            $host = isset($parsed['host']) ? sanitize_text_field($parsed['host']) : '';
            // Blocca destinazioni pericolose: IP, localhost, reti locali
            if (
                $host === 'localhost' ||
                filter_var($host, FILTER_VALIDATE_IP) && (
                    preg_match('/^127\./', $host) || // loopback IPv4
                    preg_match('/^10\./', $host) ||   // privato IPv4
                    preg_match('/^192\.168\./', $host) || // privato IPv4
                    preg_match('/^172\.(1[6-9]|2[0-9]|3[0-1])\./', $host) // privato IPv4
                )
            ) {
                wp_die('Destinazione IP/localhost/rete privata non consentita.');
            }
            if (!in_array($host, $allowed_domains, true)) {
                wp_die('Dominio di destinazione non consentito.');
            }
            // Blocca richieste HEAD e bot
            if ($_SERVER['REQUEST_METHOD'] === 'HEAD') {
                status_header(403);
                exit('Forbidden');
            }
            $ua_check = isset($_SERVER['HTTP_USER_AGENT']) ? strtolower($_SERVER['HTTP_USER_AGENT']) : '';
            $bot_signals = ['bot','crawl','spider','slurp','facebookexternalhit','mediapartners-google','adsbot','bingpreview'];
            foreach ($bot_signals as $signal) {
                if (strpos($ua_check, $signal) !== false) {
                    status_header(403);
                    exit('Forbidden');
                }
            }
            // Logging del click
            global $wpdb;
            $table = $wpdb->prefix . 'go_clicks';
            $ts = current_time('mysql');
            $ip = isset($_SERVER['REMOTE_ADDR']) ? inet_pton(sanitize_text_field($_SERVER['REMOTE_ADDR'])) : null;
            $ua = isset($_SERVER['HTTP_USER_AGENT']) ? sanitize_text_field($_SERVER['HTTP_USER_AGENT']) : '';
            $referrer = isset($_SERVER['HTTP_REFERER']) ? esc_url_raw($_SERVER['HTTP_REFERER']) : '';
            $dest_host = $host;
            $plp = isset($_GET['plp']) ? sanitize_text_field($_GET['plp']) : '';
            $utm_source = isset($_GET['utm_source']) ? sanitize_text_field($_GET['utm_source']) : '';
            $utm_medium = isset($_GET['utm_medium']) ? sanitize_text_field($_GET['utm_medium']) : '';
            $utm_campaign = isset($_GET['utm_campaign']) ? sanitize_text_field($_GET['utm_campaign']) : '';
            $utm_content = isset($_GET['utm_content']) ? sanitize_text_field($_GET['utm_content']) : '';
            $utm_term = isset($_GET['utm_term']) ? sanitize_text_field($_GET['utm_term']) : '';
            $fbclid = isset($_GET['fbclid']) ? sanitize_text_field($_GET['fbclid']) : '';
            $gclid = isset($_GET['gclid']) ? sanitize_text_field($_GET['gclid']) : '';
            $wpdb->insert(
                $table,
                [
                    'ts' => $ts,
                    'ip' => $ip,
                    'ua' => $ua,
                    'referrer' => $referrer,
                    'dest' => $dest,
                    'dest_host' => $dest_host,
                    'plp' => $plp,
                    'utm_source' => $utm_source,
                    'utm_medium' => $utm_medium,
                    'utm_campaign' => $utm_campaign,
                    'utm_content' => $utm_content,
                    'utm_term' => $utm_term,
                    'fbclid' => $fbclid,
                    'gclid' => $gclid,
                ],
                [
                    '%s','%s','%s','%s','%s','%s','%s','%s','%s','%s','%s','%s','%s','%s','%s'
                ]
            );
            // Propagazione automatica UTM e PLP
            $params_to_propagate = [
                'plp',
                'utm_source',
                'utm_medium',
                'utm_campaign',
                'utm_content',
                'utm_term',
                'fbclid',
                'gclid'
            ];
            $query = [];
            foreach ($params_to_propagate as $k) {
                if (!empty($_GET[$k])) {
                    $query[$k] = sanitize_text_field($_GET[$k]);
                }
            }
            if (!empty($query)) {
                $parsed_dest = wp_parse_url($dest);
                $dest_query = [];
                if (isset($parsed_dest['query'])) {
                    parse_str($parsed_dest['query'], $dest_query);
                }
                $merged_query = array_merge($dest_query, $query);
                $query_str = http_build_query($merged_query);
                $base = $parsed_dest['scheme'] . '://' . $parsed_dest['host'];
                if (isset($parsed_dest['port'])) {
                    $base .= ':' . $parsed_dest['port'];
                }
                $base .= isset($parsed_dest['path']) ? $parsed_dest['path'] : '';
                $dest = $base . '?' . $query_str;
            }
            // Redirect 302 verso la destinazione
            wp_redirect($dest, 302);
            exit;
        }
    }
}

new GoWPTracker();

register_activation_hook(__FILE__, ['GoWPTracker', 'activate_plugin']);
add_action('plugins_loaded', ['GoWPTracker', 'maybe_upgrade']);
