<?php
// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Hook action handler to admin_init to ensure it runs before page render.
add_action('admin_init', 'gowptracker_split_handle_actions');

/**
 * Handles form submissions for creating/updating split tests.
 * This function is hooked into the 'load' action to process POST data before the page renders.
 */
function gowptracker_split_handle_actions() {
    if (!current_user_can('manage_options')) {
        return;
    }

    global $wpdb;
    $tests_table = $wpdb->prefix . 'go_split_tests';
    $variants_table = $wpdb->prefix . 'go_split_variants';
    $now = current_time('mysql');

    // --- Handle Create ---
    if (isset($_POST['gowp_split_create']) && check_admin_referer('gowp_split_create_nonce')) {
        $name = isset($_POST['name']) ? sanitize_text_field($_POST['name']) : '';
        $slug = isset($_POST['slug']) ? sanitize_title($_POST['slug']) : '';
        $status = isset($_POST['status']) ? 1 : 0;
        $variant_post_ids = isset($_POST['variant_post_id']) && is_array($_POST['variant_post_id']) ? array_map('absint', $_POST['variant_post_id']) : [];
        $variant_weights = isset($_POST['variant_weight']) && is_array($_POST['variant_weight']) ? array_map('absint', $_POST['variant_weight']) : [];

        // Validation...
        // (For brevity, assuming validation logic is sound as it was)
        $variants_to_save = [];
        if (!empty($variant_post_ids)) {
            foreach ($variant_post_ids as $idx => $pid) {
                if ($pid > 0) {
                    $w = isset($variant_weights[$idx]) && $variant_weights[$idx] > 0 ? $variant_weights[$idx] : 1;
                    $variants_to_save[] = [ 'post_id' => $pid, 'weight' => $w ];
                }
            }
        }

        if (!empty($name) && !empty($slug) && !empty($variants_to_save)) {
            $wpdb->insert($tests_table, [
                'slug' => $slug, 'name' => $name, 'status' => $status,
                'created_at' => $now, 'updated_at' => $now,
            ], ['%s', '%s', '%d', '%s', '%s']);
            $test_id = $wpdb->insert_id;
            foreach ($variants_to_save as $v) {
                $wpdb->insert($variants_table, [
                    'test_id' => $test_id, 'post_id' => $v['post_id'], 'weight' => $v['weight'],
                    'created_at' => $now, 'updated_at' => $now,
                ], ['%d', '%d', '%d', '%s', '%s']);
            }
            // Redirect to avoid form resubmission
            wp_redirect(add_query_arg(['page' => 'gowptracker-split-tests', 'message' => 'created'], admin_url('admin.php')));
            exit;
        }
    }

    // --- Handle Update ---
    if (isset($_POST['gowp_split_update']) && check_admin_referer('gowp_split_update_nonce')) {
        $test_id = isset($_POST['test_id']) ? absint($_POST['test_id']) : 0;
        $name = isset($_POST['name']) ? sanitize_text_field($_POST['name']) : '';
        $status = isset($_POST['status']) ? 1 : 0;
        $variant_post_ids = isset($_POST['variant_post_id']) && is_array($_POST['variant_post_id']) ? array_map('absint', $_POST['variant_post_id']) : [];
        $variant_weights = isset($_POST['variant_weight']) && is_array($_POST['variant_weight']) ? array_map('absint', $_POST['variant_weight']) : [];

        // Validation...
        $variants_to_save = [];
        if (!empty($variant_post_ids)) {
             foreach ($variant_post_ids as $idx => $pid) {
                if ($pid > 0) {
                    $w = isset($variant_weights[$idx]) && $variant_weights[$idx] > 0 ? $variant_weights[$idx] : 1;
                    $variants_to_save[] = [ 'post_id' => $pid, 'weight' => $w ];
                }
            }
        }

        if ($test_id > 0 && !empty($name) && !empty($variants_to_save)) {
            $wpdb->update(
                $tests_table,
                ['name' => $name, 'status' => $status, 'updated_at' => $now],
                ['id' => $test_id],
                ['%s', '%d', '%s'], ['%d']
            );
            $wpdb->delete($variants_table, ['test_id' => $test_id], ['%d']);
            foreach ($variants_to_save as $v) {
                $wpdb->insert($variants_table, [
                    'test_id' => $test_id, 'post_id' => $v['post_id'], 'weight' => $v['weight'],
                    'created_at' => $now, 'updated_at' => $now,
                ], ['%d', '%d', '%d', '%s', '%s']);
            }
            wp_redirect(add_query_arg(['page' => 'gowptracker-split-tests', 'edit' => $test_id, 'message' => 'updated'], admin_url('admin.php')));
            exit;
        }
    }

    // --- Handle Delete ---
    if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['test_id'])) {
        $test_id = absint($_GET['test_id']);
        if ($test_id > 0 && check_admin_referer('gowp_delete_split_test_' . $test_id)) {
            $wpdb->delete($tests_table, ['id' => $test_id], ['%d']);
            $wpdb->delete($variants_table, ['test_id' => $test_id], ['%d']);
            wp_redirect(add_query_arg(['page' => 'gowptracker-split-tests', 'message' => 'deleted'], admin_url('admin.php')));
            exit;
        }
    }

    // --- Handle CSV Export ---
    if (isset($_POST['gowp_split_export_csv']) && check_admin_referer('gowp_split_report_nonce')) {
        $report_slug = isset($_POST['report_slug']) ? sanitize_title($_POST['report_slug']) : '';
        $days = isset($_POST['report_days']) ? max(1, absint($_POST['report_days'])) : 7;
        $since = date('Y-m-d H:i:s', strtotime("-{$days} days"));

        $map = $wpdb->get_results($wpdb->prepare(
            "SELECT v.id as variant_id, v.post_id FROM {$variants_table} v JOIN {$tests_table} t ON t.id = v.test_id WHERE t.slug = %s",
            $report_slug
        ), ARRAY_A);
        $variant_to_post = wp_list_pluck($map, 'post_id', 'variant_id');

        $split_hits = $wpdb->prefix . 'go_split_hits';
        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT variant_id, COUNT(*) as clicks FROM $split_hits WHERE test_slug = %s AND ts >= %s GROUP BY variant_id",
            $report_slug, $since
        ), ARRAY_A);

        nocache_headers();
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename=split_report_' . $report_slug . '_' . $days . 'd.csv');
        $out = fopen('php://output', 'w');
        fputcsv($out, ['test_slug', 'variant_id', 'post_id', 'post_title', 'clicks']);
        foreach ($rows as $r) {
            $vid = intval($r['variant_id']);
            $pid = isset($variant_to_post[$vid]) ? $variant_to_post[$vid] : 0;
            $title = $pid ? get_the_title($pid) : '';
            fputcsv($out, [$report_slug, $vid, $pid, $title, intval($r['clicks'])]);
        }
        fclose($out);
        exit;
    }

    // --- Handle Reset Statistics ---
    if (isset($_POST['gowp_split_reset_stats']) && check_admin_referer('gowp_split_reset_nonce')) {
        $reset_slug = isset($_POST['reset_slug']) ? sanitize_title($_POST['reset_slug']) : '';
        if (!empty($reset_slug)) {
            $split_hits_table = $wpdb->prefix . 'go_split_hits';
            $deleted_rows = $wpdb->delete($split_hits_table, ['test_slug' => $reset_slug], ['%s']);
            
            // Add a transient to pass the debug message
            if ($deleted_rows !== false) {
                set_transient('gowp_split_reset_debug', 'Statistiche resettate. Righe eliminate: ' . $deleted_rows, 45);
            }

            wp_redirect(add_query_arg(['page' => 'gowptracker-split-tests', 'report_slug' => $reset_slug, 'message' => 'reset'], admin_url('admin.php')));
            exit;
        }
    }
}


/**
 * Renders the admin page for Split Tests.
 */
function gowptracker_render_split_admin_page() {
    if (!current_user_can('manage_options')) {
        wp_die('Unauthorized');
    }

    global $wpdb;
    $tests_table = $wpdb->prefix . 'go_split_tests';
    $variants_table = $wpdb->prefix . 'go_split_variants';
    $is_edit = isset($_GET['edit']) ? absint($_GET['edit']) : 0;

    // Display success/error messages
    if (isset($_GET['message'])) {
        $message = '';
        if ($_GET['message'] === 'created') $message = 'Split test creato correttamente.';
        if ($_GET['message'] === 'updated') $message = 'Split test aggiornato correttamente.';
        if ($_GET['message'] === 'deleted') $message = 'Split test eliminato correttamente.';
        if ($_GET['message'] === 'reset') $message = 'Statistiche del test resettate correttamente.';
        if ($message) echo '<div class="notice notice-success"><p>' . esc_html($message) . '</p></div>';
    }

    // Check for and display the debug transient
    $debug_message = get_transient('gowp_split_reset_debug');
    if ($debug_message) {
        echo '<script>console.log("GoWPTracker Debug: ' . esc_js($debug_message) . '");</script>';
        delete_transient('gowp_split_reset_debug');
    }

    echo '<div class="wrap">';
    echo '<h1>Split Tests</h1>';

    if ($is_edit) {
        // Render Edit Form
        render_split_test_form($is_edit);
    } else {
        // Render List and Create Form
        render_split_tests_list();
        render_split_test_form(0);
    }

    echo '</div>'; // .wrap

    // Render reporting section
    render_split_test_reports();

    // Render recent hits report
    render_split_test_recent_hits_report();

    // Render JS for form interactions
    render_split_test_js();
}

/**
 * Renders the list of existing split tests.
 */
function render_split_tests_list() {
    global $wpdb;
    $tests_table = $wpdb->prefix . 'go_split_tests';
    $variants_table = $wpdb->prefix . 'go_split_variants';
    $tests = $wpdb->get_results("SELECT t.*, (SELECT COUNT(*) FROM $variants_table v WHERE v.test_id = t.id) as variants FROM $tests_table t ORDER BY t.id DESC", ARRAY_A);
    ?>
    <h2 class="title">Lista Test</h2>
    <table class="widefat">
        <thead><tr><th>Slug</th><th>Nome</th><th>Stato</th><th># Varianti</th><th>URL</th><th>Azioni</th></tr></thead>
        <tbody>
            <?php if ($tests): foreach ($tests as $t): ?>
                <tr>
                    <td><?php echo esc_html($t['slug']); ?></td>
                    <td><?php echo esc_html($t['name']); ?></td>
                    <td><?php echo $t['status'] ? 'Attivo' : 'Disattivo'; ?></td>
                    <td><?php echo intval($t['variants']); ?></td>
                    <td><code><?php echo esc_html(home_url('/split/' . $t['slug'])); ?></code></td>
                    <td>
                        <a class="button button-small" href="<?php echo esc_url(add_query_arg(['page' => 'gowptracker-split-tests', 'edit' => $t['id']])); ?>">Modifica</a>
                        <?php
                        $delete_url = wp_nonce_url(admin_url('admin.php?page=gowptracker-split-tests&action=delete&test_id=' . $t['id']), 'gowp_delete_split_test_' . $t['id']);
                        ?>
                        <a href="<?php echo esc_url($delete_url); ?>" class="button button-small" style="color:#a00;margin-left:4px;" onclick="return confirm('Sei sicuro di voler eliminare questo test? L\'azione è irreversibile.');">Elimina</a>
                    </td>
                </tr>
            <?php endforeach; else: ?>
                <tr><td colspan="6">Nessun test presente</td></tr>
            <?php endif; ?>
        </tbody>
    </table>
    <?php
}

/**
 * Renders the Create/Edit form for a split test.
 * @param int $test_id The ID of the test to edit, or 0 to create.
 */
function render_split_test_form($test_id = 0) {
    global $wpdb;
    $test = null;
    $variants = [];
    if ($test_id > 0) {
        $tests_table = $wpdb->prefix . 'go_split_tests';
        $variants_table = $wpdb->prefix . 'go_split_variants';
        $test = $wpdb->get_row($wpdb->prepare("SELECT * FROM $tests_table WHERE id = %d", $test_id), ARRAY_A);
        $variants = $wpdb->get_results($wpdb->prepare("SELECT * FROM $variants_table WHERE test_id = %d", $test_id), ARRAY_A);
    }

    $is_edit = (bool) $test;
    $form_title = $is_edit ? 'Modifica Test: ' . esc_html($test['slug']) : 'Crea Nuovo Test';
    ?>
    <h2 class="title" style="margin-top:2em;"><?php echo $form_title; ?></h2>
    <form method="post" id="gowp_split_form" data-max="10">
        <?php if ($is_edit): ?>
            <input type="hidden" name="test_id" value="<?php echo intval($test_id); ?>">
            <?php wp_nonce_field('gowp_split_update_nonce'); ?>
        <?php else: ?>
            <?php wp_nonce_field('gowp_split_create_nonce'); ?>
        <?php endif; ?>

        <table class="form-table">
            <?php if ($is_edit): ?>
                <tr><th>Slug</th><td><code><?php echo esc_html($test['slug']); ?></code> <small>(immutabile)</small></td></tr>
            <?php else: ?>
                 <tr><th><label for="gowp_slug">Slug</label></th><td><input type="text" id="gowp_slug" name="slug" class="regular-text" placeholder="es. summer-sale" required></td></tr>
            <?php endif; ?>
            <tr><th><label for="gowp_name">Nome</label></th><td><input type="text" id="gowp_name" name="name" class="regular-text" value="<?php echo esc_attr($is_edit ? $test['name'] : ''); ?>" required></td></tr>
            <tr><th><label for="gowp_status">Attivo</label></th><td><label><input type="checkbox" id="gowp_status" name="status" value="1" <?php checked($is_edit ? $test['status'] : 1); ?>> Attivo</label></td></tr>
        </table>

        <h3>Varianti</h3>
        <div id="gowp_variants">
            <?php
            if ($variants) {
                foreach ($variants as $v) {
                    render_variant_row($v['post_id'], $v['weight']);
                }
            } elseif (!$is_edit) {
                render_variant_row(); render_variant_row(); // Start with two empty rows
            }
            ?>
        </div>
        <p>
            <button type="button" id="gowp_add_variant" class="button">+ Aggiungi variante</button>
            <button type="button" id="gowp_equalize" class="button">Equalizza pesi</button>
        </p>
        <p>
            <button type="submit" name="<?php echo $is_edit ? 'gowp_split_update' : 'gowp_split_create'; ?>" class="button button-primary">
                <?php echo $is_edit ? 'Salva Modifiche' : 'Crea Split Test'; ?>
            </button>
        </p>
    </form>
    <template id="gowp_variant_tpl"><?php render_variant_row(0, 1); ?></template>
    <?php
}

/**
 * Renders a single variant row for the form.
 */
function render_variant_row($post_id = 0, $weight = 1) {
    ?>
    <div class="gowp-variant-row" style="display:flex; gap:12px; align-items:center; margin-bottom:8px;">
        <div><?php wp_dropdown_pages(['name' => 'variant_post_id[]', 'show_option_none' => '— Seleziona pagina —', 'option_none_value' => '0', 'selected' => $post_id]); ?></div>
        <div><label>Peso <input type="number" class="gowp-weight" name="variant_weight[]" value="<?php echo intval($weight); ?>" min="1" style="width:80px;"></label></div>
        <button type="button" class="button button-secondary gowp-remove-variant">Rimuovi</button>
    </div>
    <?php
}

/**
 * Renders the reporting UI for split tests.
 */
function render_split_test_reports() {
    global $wpdb;
    $tests_table = $wpdb->prefix . 'go_split_tests';
    $all_tests = $wpdb->get_results("SELECT slug, name FROM $tests_table ORDER BY id DESC", ARRAY_A);
    $selected_slug = isset($_GET['report_slug']) ? sanitize_title($_GET['report_slug']) : ($all_tests[0]['slug'] ?? '');
    $selected_days = isset($_GET['report_days']) ? absint($_GET['report_days']) : 7;
    ?>
    <hr style="margin:2em 0;">
    <h2 class="title">Report</h2>
    <form method="get" style="margin-bottom:1em;">
        <input type="hidden" name="page" value="gowptracker-split-tests">
        <label style="margin-right:8px;">Test: <select name="report_slug">
            <?php foreach ($all_tests as $t) {
                printf('<option value="%s" %s>%s</option>', esc_attr($t['slug']), selected($selected_slug, $t['slug'], false), esc_html($t['name'] . ' (' . $t['slug'] . ')'));
            } ?>
        </select></label>
        <label style="margin-right:8px;">Periodo: <select name="report_days">
            <?php foreach ([7, 30, 90] as $opt) {
                printf('<option value="%d" %s>%d giorni</option>', $opt, selected($selected_days, $opt, false), $opt);
            } ?>
        </select></label>
        <button class="button">Aggiorna Report</button>
    </form>

    <?php if ($selected_slug):
        $split_hits_table = $wpdb->prefix . 'go_split_hits';
        $variants_table = $wpdb->prefix . 'go_split_variants';
        $since = date('Y-m-d H:i:s', strtotime("-{$selected_days} days"));

        $map = $wpdb->get_results($wpdb->prepare(
            "SELECT v.id as variant_id, v.post_id FROM {$variants_table} v JOIN {$tests_table} t ON t.id = v.test_id WHERE t.slug = %s",
            $selected_slug
        ), ARRAY_A);
        $variant_to_post = wp_list_pluck($map, 'post_id', 'variant_id');

        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT variant_id, COUNT(*) as clicks FROM $split_hits_table WHERE test_slug = %s AND ts >= %s GROUP BY variant_id",
            $selected_slug, $since
        ), ARRAY_A);
    ?>
        <table class="widefat">
            <thead><tr><th>Variante ID</th><th>Pagina</th><th>Clicks</th></tr></thead>
            <tbody>
            <?php if ($rows): foreach ($rows as $r):
                $vid = intval($r['variant_id']);
                $pid = $variant_to_post[$vid] ?? 0;
                $title = $pid ? get_the_title($pid) : '—';
                $plink = $pid ? get_permalink($pid) : '';
            ?>
                <tr>
                    <td>#<?php echo $vid; ?></td>
                    <td><?php echo $plink ? '<a href="' . esc_url($plink) . '" target="_blank">' . esc_html($title) . '</a>' : esc_html($title); ?></td>
                    <td><?php echo intval($r['clicks']); ?></td>
                </tr>
            <?php endforeach; else: ?>
                <tr><td colspan="3">Nessun dato nel periodo selezionato.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
        <div style="margin-top:1em; display: flex; gap: 10px;">
            <form method="post">
                <?php wp_nonce_field('gowp_split_report_nonce'); ?>
                <input type="hidden" name="report_slug" value="<?php echo esc_attr($selected_slug); ?>">
                <input type="hidden" name="report_days" value="<?php echo esc_attr($selected_days); ?>">
                <button type="submit" name="gowp_split_export_csv" class="button button-secondary">Esporta CSV</button>
            </form>
            <form method="post">
                <?php wp_nonce_field('gowp_split_reset_nonce'); ?>
                <input type="hidden" name="reset_slug" value="<?php echo esc_attr($selected_slug); ?>">
                <button type="submit" name="gowp_split_reset_stats" class="button" style="color:#a00;" onclick="return confirm('Sei sicuro di voler resettare le statistiche per questo test? L\'azione è irreversibile.');">Resetta Statistiche</button>
            </form>
        </div>
    <?php endif; ?>
    <?php
}

/**
 * Renders the inline JavaScript for the admin form.
 */
/**
 * Renders the report of the last 10 hits for the selected split test.
 */
function render_split_test_recent_hits_report() {
    global $wpdb;
    $tests_table = $wpdb->prefix . 'go_split_tests';

    // Get the default slug if not specified in URL, to match the main report's behavior
    if (isset($_GET['report_slug'])) {
        $selected_slug = sanitize_title($_GET['report_slug']);
    } else {
        $selected_slug = $wpdb->get_var("SELECT slug FROM $tests_table ORDER BY id DESC LIMIT 1");
    }

    if (empty($selected_slug)) {
        // Don't render if no test is selected
        return;
    }

    $hits_table = $wpdb->prefix . 'go_split_hits';
    $tests_table = $wpdb->prefix . 'go_split_tests';
    $variants_table = $wpdb->prefix . 'go_split_variants';

    // Pre-fetch variant to post ID mapping for the selected test
    $map = $wpdb->get_results($wpdb->prepare(
        "SELECT v.id as variant_id, v.post_id FROM {$variants_table} v JOIN {$tests_table} t ON t.id = v.test_id WHERE t.slug = %s",
        $selected_slug
    ), ARRAY_A);
    $variant_to_post = wp_list_pluck($map, 'post_id', 'variant_id');

    $recent_hits = $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM {$hits_table} WHERE test_slug = %s ORDER BY ts DESC LIMIT 10",
        $selected_slug
    ), ARRAY_A);
    ?>
    <hr style="margin:2em 0;">
    <h2 class="title">Ultimi 10 Click</h2>
    <table class="widefat">
        <thead>
            <tr>
                <th>Timestamp</th>
                <th>Pagina Variante</th>
                <th>IP / Geo</th>
                <th>Dispositivo</th>
                <th>User Agent</th>
            </tr>
        </thead>
        <tbody>
            <?php if ($recent_hits): foreach ($recent_hits as $hit): ?>
                <?php
                $variant_id = intval($hit['variant_id']);
                $post_id = $variant_to_post[$variant_id] ?? 0;
                $post_title = $post_id ? get_the_title($post_id) : '<em>Sconosciuto</em>';
                $ip_address = isset($hit['ip']) ? inet_ntop($hit['ip']) : 'N/A';
                $geo_info = trim($hit['geo_city'] . ', ' . $hit['geo_country'], ', ');
                ?>
                <tr>
                    <td><?php echo esc_html($hit['ts']); ?></td>
                    <td><?php echo esc_html($post_title); ?></td>
                    <td>
                        <?php echo esc_html($ip_address); ?><br>
                        <small><?php echo esc_html($geo_info); ?></small>
                    </td>
                    <td><?php echo esc_html(ucfirst($hit['device_type'])); ?></td>
                    <td style="font-size:0.9em; color:#555;"><?php echo esc_html($hit['ua']); ?></td>
                </tr>
            <?php endforeach; else: ?>
                <tr><td colspan="5">Nessun click registrato per questo test.</td></tr>
            <?php endif; ?>
        </tbody>
    </table>
    <?php
}

function render_split_test_js() {
    ?>
<script>
document.addEventListener("DOMContentLoaded", function(){
  const form = document.getElementById("gowp_split_form");
  if (!form) return;
  const max = parseInt(form.getAttribute("data-max")) || 10;
  const container = document.getElementById("gowp_variants");
  const tpl = document.getElementById("gowp_variant_tpl");
  const addBtn = document.getElementById("gowp_add_variant");
  const eqBtn = document.getElementById("gowp_equalize");

  function bindRow(row){
    row.querySelector(".gowp-remove-variant")?.addEventListener("click", () => row.remove());
  }
  container.querySelectorAll(".gowp-variant-row").forEach(bindRow);

  addBtn?.addEventListener("click", () => {
    if (container.querySelectorAll(".gowp-variant-row").length >= max) return;
    const node = tpl.content.firstElementChild.cloneNode(true);
    bindRow(node);
    container.appendChild(node);
  });

  eqBtn?.addEventListener("click", () => {
    const weights = container.querySelectorAll(".gowp-weight");
    const n = weights.length;
    if (n === 0) return;
    const base = Math.floor(100 / n);
    weights.forEach(inp => {
        inp.value = base;
        inp.dispatchEvent(new Event("input", { bubbles: true }));
    });
  });
});
</script>
    <?php
}
