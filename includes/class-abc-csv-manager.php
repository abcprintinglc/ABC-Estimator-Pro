<?php
if (!defined('ABSPATH')) { exit; }

/**
 * CSV import & bulk delete tools.
 */
class ABC_CSV_Manager {

    public function __construct() {
        add_action('admin_post_abc_process_csv', [$this, 'process_csv_upload']);
        add_action('admin_post_abc_delete_all_csv', [$this, 'delete_all_imported_data']);

        add_action('admin_menu', [$this, 'register_tools_page']);
        add_action('admin_notices', [$this, 'render_notices']);
    }

    public function register_tools_page() {
        add_submenu_page(
            'edit.php?post_type=abc_estimate',
            'Import / Data Tools',
            'Import / Data Tools',
            'manage_options',
            'abc-import-settings',
            [$this, 'render_tools_page']
        );
    }

    /**
     * Validate Invoice Format (tttt-yy)
     */
    private function validate_invoice_format($invoice) {
        return (bool) preg_match('/^\d{4}-\d{2}$/', (string) $invoice);
    }


    /**
     * Check if invoice already exists (prevents duplicates on re-import).
     */
    private function invoice_exists($invoice) {
        $invoice = (string) $invoice;
        if ($invoice === '') {
            return false;
        }

        $existing = new WP_Query([
            'post_type'      => 'abc_estimate',
            'posts_per_page' => 1,
            'post_status'    => 'any',
            'meta_key'       => 'abc_invoice_number',
            'meta_value'     => $invoice,
            'fields'         => 'ids',
            'no_found_rows'  => true,
        ]);

        return $existing->have_posts();
    }


    /**
     * Process import.
     * CSV Columns: [0] Title, [1] Invoice Number, [2] Due Date (YYYY-MM-DD)
     */
    public function process_csv_upload() {
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }
        check_admin_referer('abc_import_csv_action');

        if (empty($_FILES['abc_csv_file']['tmp_name'])) {
            wp_die('No file uploaded.');
        }

        $handle = fopen($_FILES['abc_csv_file']['tmp_name'], 'r');
        if (!$handle) {
            wp_die('Could not read uploaded file.');
        }

        $row_count = 0;
        $skipped_duplicates = 0;
        $errors = [];

        // Optional: skip UTF-8 BOM
        $first_bytes = fread($handle, 3);
        if ($first_bytes !== "\xEF\xBB\xBF") {
            rewind($handle);
        }

        $row_index = 0;
        while (($data = fgetcsv($handle, 0, ",")) !== false) {
            $row_index++;

            // Skip empty rows.
            if (!is_array($data) || count($data) < 3) {
                continue;
            }

            $title   = sanitize_text_field((string) $data[0]);
            $invoice = sanitize_text_field((string) $data[1]);
            $due_date = sanitize_text_field((string) $data[2]);

            // Allow header row.
            if ($row_index === 1 && preg_match('/invoice/i', $invoice)) {
                continue;
            }

            if ($title === '' && $invoice === '' && $due_date === '') {
                continue;
            }

            // Validation rule
            if (!$this->validate_invoice_format($invoice)) {
                $errors[] = "Row {$row_index} ignored (Invalid Invoice: {$invoice}). Must be tttt-yy (e.g., 1005-24).";
                continue;
            }

            // Prevent duplicates (invoice uniqueness)
            if ($this->invoice_exists($invoice)) {
                $skipped_duplicates++;
                $errors[] = "Row {$row_index} skipped (Duplicate Invoice: {$invoice}).";
                continue;
            }

            // Create post.
            $post_id = wp_insert_post([
                'post_type'   => 'abc_estimate',
                'post_title'   => $title !== '' ? $title : $invoice,
                'post_status'  => 'publish',
                'post_excerpt' => 'Invoice: ' . $invoice . ' | ' . ($title !== '' ? $title : ''),
            ], true);

            if (is_wp_error($post_id)) {
                $errors[] = "Row {$row_index} failed to insert post: " . $post_id->get_error_message();
                continue;
            }

            update_post_meta($post_id, 'abc_invoice_number', $invoice);
            update_post_meta($post_id, 'abc_due_date', $due_date);
            update_post_meta($post_id, 'abc_status', 'estimate');
            update_post_meta($post_id, 'abc_is_imported', '1');
            $row_count++;
        }

        fclose($handle);

        // Store errors in transient so we can show them without querystring bloat.
        if (!empty($errors)) {
            set_transient('abc_csv_import_errors_' . get_current_user_id(), $errors, 5 * MINUTE_IN_SECONDS);
        }

        $url = add_query_arg(
            ['imported' => $row_count, 'dupes' => $skipped_duplicates, 'errors' => count($errors)],
            admin_url('edit.php?post_type=abc_estimate&page=abc-import-settings')
        );
        wp_safe_redirect($url);
        exit;
    }

    /**
     * Delete all items previously imported via CSV (flag meta abc_is_imported=1).
     */
    public function delete_all_imported_data() {
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }
        check_admin_referer('abc_delete_all_action');

        $query = new WP_Query([
            'post_type'      => 'abc_estimate',
            'posts_per_page' => -1,
            'meta_key'       => 'abc_is_imported',
            'meta_value'     => '1',
            'fields'         => 'ids',
            'no_found_rows'  => true,
        ]);

        $deleted = 0;
        foreach ($query->posts as $id) {
            $r = wp_delete_post((int) $id, true);
            if ($r) {
                $deleted++;
            }
        }

        $url = add_query_arg(
            ['deleted' => $deleted],
            admin_url('edit.php?post_type=abc_estimate&page=abc-import-settings')
        );
        wp_safe_redirect($url);
        exit;
    }

    /**
     * Render admin notices for import/delete results and show transient errors.
     */
    public function render_notices() {
        $screen = function_exists('get_current_screen') ? get_current_screen() : null;
        if (!$screen || $screen->id !== 'abc_estimate_page_abc-import-settings') {
            return;
        }

        if (isset($_GET['imported'])) {
            $imported = absint($_GET['imported']);
            $errors_count = isset($_GET['errors']) ? absint($_GET['errors']) : 0;
            $dupes = isset($_GET['dupes']) ? absint($_GET['dupes']) : 0;
            echo '<div class="notice notice-success"><p>' . esc_html("Imported {$imported} row(s).") . '</p></div>';
            if ($dupes > 0) {
                echo '<div class="notice notice-info"><p>' . esc_html("Skipped {$dupes} duplicate invoice row(s).") . '</p></div>';
            }
            if ($errors_count > 0) {
                echo '<div class="notice notice-warning"><p>' . esc_html("{$errors_count} row(s) were ignored or failed. See details below.") . '</p></div>';
            }
        }

        if (isset($_GET['deleted'])) {
            $deleted = absint($_GET['deleted']);
            echo '<div class="notice notice-success"><p>' . esc_html("Deleted {$deleted} imported estimate(s).") . '</p></div>';
        }

        $errors = get_transient('abc_csv_import_errors_' . get_current_user_id());
        if (!empty($errors) && is_array($errors)) {
            delete_transient('abc_csv_import_errors_' . get_current_user_id());
            echo '<div class="notice notice-warning"><p><strong>CSV Import Details</strong></p><ul style="margin-left: 18px; list-style: disc;">';
            foreach (array_slice($errors, 0, 40) as $err) {
                echo '<li>' . esc_html($err) . '</li>';
            }
            if (count($errors) > 40) {
                echo '<li>' . esc_html('…and more. (Fix the CSV and re-import.)') . '</li>';
            }
            echo '</ul></div>';
        }
    }

    /**
     * Settings page UI.
     */
    public function render_tools_page() {
        ?>
        <div class="wrap">
            <h1>Estimator Data Tools</h1>

            <div class="card" style="max-width: 720px; padding: 20px; margin-top: 20px;">
                <h2>Import Estimates via CSV</h2>
                <p>Format required: <strong>Title, Invoice Number (tttt-yy), Due Date (YYYY-MM-DD)</strong></p>

                <form action="<?php echo esc_url(admin_url('admin-post.php')); ?>" method="post" enctype="multipart/form-data">
                    <input type="hidden" name="action" value="abc_process_csv">
                    <?php wp_nonce_field('abc_import_csv_action'); ?>
                    <input type="file" name="abc_csv_file" required accept=".csv">
                    <p class="description">Tip: Header row is allowed.</p>
                    <p>
                        <button type="submit" class="button button-primary">Upload &amp; Import</button>
                    </p>
                </form>
            </div>

            <div class="card" style="max-width: 720px; padding: 20px; margin-top: 20px; border-left: 4px solid #b32d2e;">
                <h2 style="color:#b32d2e;">Danger Zone</h2>
                <p>Delete all estimates previously imported via CSV (only entries flagged as imported).</p>

                <form action="<?php echo esc_url(admin_url('admin-post.php')); ?>" method="post" onsubmit="return confirm('Are you strictly sure? This will delete ALL imported estimates.');">
                    <input type="hidden" name="action" value="abc_delete_all_csv">
                    <?php wp_nonce_field('abc_delete_all_action'); ?>
                    <button type="submit" class="button button-link-delete">Delete All CSV Entries</button>
                </form>
            </div>
        </div>
        <?php
    }
}
