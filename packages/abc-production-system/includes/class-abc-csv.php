<?php
if (!defined('ABSPATH')) { exit; }

class ABC_Production_CSV_Manager {

    public function __construct() {
        add_action('admin_menu', [$this, 'register_csv_page']);
        add_action('admin_post_abcps_import_csv', [$this, 'handle_csv_import']);
        add_action('admin_post_abcps_delete_all_csv', [$this, 'handle_delete_all_csv']);
        add_action('admin_notices', [$this, 'render_admin_notices']);
    }

    public function register_csv_page() {
        add_submenu_page(
            'edit.php?post_type=abc_estimate',
            'Import / Data Tools',
            'Import / Data Tools',
            'manage_options',
            'abc-import',
            [$this, 'render_csv_page']
        );
    }

    public function render_csv_page() {
        ?>
        <div class="wrap">
            <h1>Import Log Book CSV</h1>

            <div class="card" style="max-width: 900px; padding: 16px 20px;">
                <h2 style="margin-top:0;">Accepted CSV formats</h2>

                <p><strong>A) Legacy Physical Log Book export (your current file)</strong></p>
                <ul style="margin-left: 18px; list-style: disc;">
                    <li>Header names like: <code>Invoice No</code>, <code>Company</code>, <code>Item</code>, <code>Quantity</code>, <code>Amount</code>, <code>Date</code>, <code>PAID</code>, <code>Notes</code></li>
                    <li><code>Invoice No</code> becomes <strong>Invoice #</strong></li>
                    <li><code>Date</code> becomes <strong>Date In</strong> (<code>abc_order_date</code>)</li>
                    <li><code>Company</code> becomes <strong>Client Name</strong></li>
                    <li><code>Item</code> + <code>Notes</code> become <strong>Job Description</strong></li>
                    <li><code>Amount</code> becomes <strong>Total</strong> (<code>abc_order_total</code>)</li>
                    <li><code>PAID</code> sets payment status: blank = <strong>Unpaid</strong>, non-blank = <strong>Paid/Deposit</strong> (see note below)</li>
                    <li><code>HOT</code> is ignored (per your instruction)</li>
                    <li>Imported historical rows are marked <strong>Stage = Completed</strong></li>
                </ul>

                <p><strong>B) Simple format</strong> (3 columns)</p>
                <ul style="margin-left: 18px; list-style: disc;">
                    <li><code>Title, Invoice, Due Date</code></li>
                    <li>Invoice must look like <code>1436-18</code> (4 digits, dash, 2 digits)</li>
                </ul>

                <p style="margin-bottom:0;"><em>Due date rule:</em> if a due date year is older than 2026, it will be stored as blank.</p>
            </div>

            <form method="post" enctype="multipart/form-data" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="margin-top: 16px;">
                <input type="hidden" name="action" value="abcps_import_csv">
                <?php wp_nonce_field('abcps_import_csv', 'abcps_import_nonce'); ?>
                <input type="file" name="abc_csv_file" accept=".csv" required>
                <button type="submit" class="button button-primary" style="margin-left: 8px;">Import CSV</button>
            </form>

            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="margin-top: 10px;" onsubmit="return confirm('Delete ALL imported entries? This cannot be undone.');">
                <input type="hidden" name="action" value="abcps_delete_all_csv">
                <?php wp_nonce_field('abcps_delete_all_csv', 'abcps_delete_all_nonce'); ?>
                <button type="submit" class="button button-link-delete">Delete All Imported Entries</button>
            </form>
        </div>
        <?php
    }

    private function normalize_due_date_for_storage($raw) {
        $raw = trim((string)$raw);
        if ($raw === '') return '';

        // Try yyyy-mm-dd
        if (preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $raw, $m)) {
            $year = (int)$m[1];
            return ($year < 2026) ? '' : $raw;
        }

        // Try mm/dd/yyyy or m/d/yyyy
        if (preg_match('/^(\d{1,2})\/(\d{1,2})\/(\d{4})$/', $raw, $m)) {
            $month = str_pad($m[1], 2, '0', STR_PAD_LEFT);
            $day   = str_pad($m[2], 2, '0', STR_PAD_LEFT);
            $year  = (int)$m[3];
            if ($year < 2026) return '';
            return sprintf('%04d-%02d-%02d', $year, (int)$month, (int)$day);
        }

        // Fallback: if it contains a year < 2026, blank it.
        if (preg_match('/\b(20\d{2})\b/', $raw, $m)) {
            $year = (int)$m[1];
            if ($year < 2026) return '';
        }
        return $raw;
    }

    private function parse_money($raw) {
        $raw = trim((string)$raw);
        if ($raw === '') return 0.0;
        $raw = str_replace([',', '$'], '', $raw);
        // Keep leading minus if any, and decimal.
        if (!is_numeric($raw)) return 0.0;
        return (float)$raw;
    }

    private function payment_status_from_columns($paid_cell, $amount_cell) {
        $paid_cell = trim((string)$paid_cell);
        if ($paid_cell === '') return 'unpaid';

        // If it literally says PAID
        if (stripos($paid_cell, 'paid') !== false) return 'paid';

        $paid = $this->parse_money($paid_cell);
        $amount = $this->parse_money($amount_cell);

        if ($paid <= 0) {
            // Non-empty but not numeric – treat as paid flag
            return 'paid';
        }

        if ($amount > 0 && $paid + 0.00001 < $amount) {
            return 'deposit';
        }

        return 'paid';
    }

    private function build_header_map($header_row) {
        $map = [];
        foreach ($header_row as $idx => $h) {
            $key = strtolower(trim((string)$h));
            $key = preg_replace('/\s+/', ' ', $key);
            if ($key !== '') {
                $map[$key] = $idx;
            }
        }
        return $map;
    }

    private function idx($map, $possible_keys) {
        foreach ($possible_keys as $k) {
            $k = strtolower(trim($k));
            if (isset($map[$k])) return $map[$k];
        }
        return null;
    }

    public function handle_csv_import() {
        if (!current_user_can('manage_options')) {
            wp_die('You do not have permission to import.');
        }
        if (!isset($_POST['abcps_import_nonce']) || !wp_verify_nonce($_POST['abcps_import_nonce'], 'abcps_import_csv')) {
            wp_die('Invalid nonce.');
        }
        if (empty($_FILES['abc_csv_file']['tmp_name'])) {
            wp_die('No file uploaded.');
        }

        $file = $_FILES['abc_csv_file']['tmp_name'];
        $handle = fopen($file, 'r');
        if (!$handle) {
            wp_die('Could not open CSV file.');
        }

        $imported = 0;
        $errors = [];
        $row_num = 0;

        $first_row = fgetcsv($handle);
        if ($first_row === false) {
            fclose($handle);
            wp_safe_redirect(admin_url('edit.php?post_type=abc_estimate&page=abc-import&imported=0'));
            exit;
        }

        $row_num++;
        $header_map = $this->build_header_map($first_row);
        $is_legacy = ($this->idx($header_map, ['invoice no', 'invoice #', 'invoice']) !== null) && ($this->idx($header_map, ['company', 'client']) !== null || $this->idx($header_map, ['item']) !== null);

        // If not legacy, treat first row as data and process it below.
        $rows_to_process = [];
        if (!$is_legacy) {
            $rows_to_process[] = $first_row;
        }

        // Add the rest.
        while (($data = fgetcsv($handle)) !== false) {
            $rows_to_process[] = $data;
        }
        fclose($handle);

        foreach ($rows_to_process as $data) {
            $row_num++;

            // Guard
            if (!is_array($data) || count($data) === 0) {
                continue;
            }

            if ($is_legacy) {
                // Map by header names
                $i_invoice = $this->idx($header_map, ['invoice no', 'invoice #', 'invoice']);
                $i_company = $this->idx($header_map, ['company', 'client', 'customer']);
                $i_item    = $this->idx($header_map, ['item', 'job', 'description']);
                $i_qty     = $this->idx($header_map, ['quantity', 'qty']);
                $i_amount  = $this->idx($header_map, ['amount', 'total', 'total ($)']);
                $i_paid    = $this->idx($header_map, ['paid']);
                $i_date    = $this->idx($header_map, ['date', 'date in', 'datein']);
                $i_notes   = $this->idx($header_map, ['notes', 'note']);

                $invoice = isset($data[$i_invoice]) ? trim((string)$data[$i_invoice]) : '';
                if ($invoice === '') {
                    continue;
                }

                // Invoice format in your system is 4digits-2digits (e.g. 1436-18)
                if (!preg_match('/^\d{4}-\d{2}$/', $invoice)) {
                    // Some rows could have blanks or junk; just skip with note.
                    $errors[] = "Row {$row_num} ignored (Invalid Invoice: {$invoice}). Expected 1436-18.";
                    continue;
                }

                // Duplicate check
                $exists = new WP_Query([
                    'post_type' => 'abc_estimate',
                    'posts_per_page' => 1,
                    'meta_key' => 'abc_invoice_number',
                    'meta_value' => $invoice,
                    'fields' => 'ids',
                ]);
                if ($exists->have_posts()) {
                    continue;
                }

                $client = ($i_company !== null && isset($data[$i_company])) ? trim((string)$data[$i_company]) : '';
                if ($client === '') $client = 'Walk-in';

                $item = ($i_item !== null && isset($data[$i_item])) ? trim((string)$data[$i_item]) : '';
                $qty  = ($i_qty !== null && isset($data[$i_qty])) ? trim((string)$data[$i_qty]) : '';
                $notes = ($i_notes !== null && isset($data[$i_notes])) ? trim((string)$data[$i_notes]) : '';
                $desc_parts = [];
                if ($item !== '') $desc_parts[] = $item;
                if ($qty !== '') $desc_parts[] = 'Qty: ' . $qty;
                if ($notes !== '') $desc_parts[] = 'Notes: ' . $notes;
                $desc = implode("\n", $desc_parts);

                $amount_raw = ($i_amount !== null && isset($data[$i_amount])) ? trim((string)$data[$i_amount]) : '';
                $paid_raw   = ($i_paid !== null && isset($data[$i_paid])) ? trim((string)$data[$i_paid]) : '';

                $paid_status = $this->payment_status_from_columns($paid_raw, $amount_raw);

                $date_in_raw = ($i_date !== null && isset($data[$i_date])) ? trim((string)$data[$i_date]) : '';
                // Legacy export date looks like M/D/YYYY or MM/DD/YYYY - store as YYYY-MM-DD if possible.
                $order_date = '';
                if (preg_match('/^(\d{1,2})\/(\d{1,2})\/(\d{4})$/', $date_in_raw, $m)) {
                    $order_date = sprintf('%04d-%02d-%02d', (int)$m[3], (int)$m[1], (int)$m[2]);
                }

                // Legacy file doesn't provide due dates in your export.
                $due_date = '';

                $title = $client . ' - ' . $invoice;
                $post_id = wp_insert_post([
                    'post_type' => 'abc_estimate',
                    'post_status' => 'publish',
                    'post_title' => $title,
                ]);

                if (is_wp_error($post_id) || !$post_id) {
                    $errors[] = "Row {$row_num} failed to import (Invoice: {$invoice}).";
                    continue;
                }

                update_post_meta($post_id, 'abc_invoice_number', $invoice);
                update_post_meta($post_id, 'abc_client_name', $client);
                update_post_meta($post_id, 'abc_job_description', $desc);
                update_post_meta($post_id, 'abc_qty', $qty);
                update_post_meta($post_id, 'abc_order_total', $amount_raw);
                update_post_meta($post_id, 'abc_paid_status', $paid_status);
                update_post_meta($post_id, 'abc_order_date', $order_date);
                update_post_meta($post_id, 'abc_due_date', $due_date);
                update_post_meta($post_id, 'abc_is_rush', '0'); // HOT ignored
                update_post_meta($post_id, 'abc_status', 'completed'); // historical default
                update_post_meta($post_id, 'abc_is_imported', '1');

                // Lightweight search index.
                $index = 'Inv:' . $invoice . ' | ' . $client . " | " . $item;
                wp_update_post(['ID' => $post_id, 'post_excerpt' => $index]);

                $imported++;

            } else {
                // Simple format: Title, Invoice, Due Date
                if (count($data) < 2) continue;
                $title = sanitize_text_field($data[0]);
                $invoice = sanitize_text_field($data[1]);
                $due_raw = isset($data[2]) ? sanitize_text_field($data[2]) : '';
                $due = $this->normalize_due_date_for_storage($due_raw);

                if (!preg_match('/^\d{4}-\d{2}$/', $invoice)) {
                    $errors[] = "Row {$row_num} ignored (Invalid Invoice: {$invoice}). Expected 1436-18.";
                    continue;
                }

                $exists = new WP_Query([
                    'post_type' => 'abc_estimate',
                    'posts_per_page' => 1,
                    'meta_key' => 'abc_invoice_number',
                    'meta_value' => $invoice,
                    'fields' => 'ids',
                ]);
                if ($exists->have_posts()) {
                    continue;
                }

                $post_id = wp_insert_post([
                    'post_type' => 'abc_estimate',
                    'post_status' => 'publish',
                    'post_title' => $title,
                ]);

                if (is_wp_error($post_id) || !$post_id) {
                    $errors[] = "Row {$row_num} failed to import (Invoice: {$invoice}).";
                    continue;
                }

                update_post_meta($post_id, 'abc_invoice_number', $invoice);
                update_post_meta($post_id, 'abc_due_date', $due);
                update_post_meta($post_id, 'abc_is_imported', '1');
                update_post_meta($post_id, 'abc_is_rush', '0');
                update_post_meta($post_id, 'abc_status', 'completed');
                update_post_meta($post_id, 'abc_paid_status', 'unpaid');

                $imported++;
            }
        }

        set_transient('abc_csv_errors', $errors, 300);
        wp_safe_redirect(admin_url('edit.php?post_type=abc_estimate&page=abc-import&imported=' . $imported));
        exit;
    }

    public function handle_delete_all_csv() {
        if (!current_user_can('manage_options')) {
            wp_die('You do not have permission to delete.');
        }
        if (!isset($_POST['abcps_delete_all_nonce']) || !wp_verify_nonce($_POST['abcps_delete_all_nonce'], 'abcps_delete_all_csv')) {
            wp_die('Invalid nonce.');
        }

        $query = new WP_Query([
            'post_type' => 'abc_estimate',
            'posts_per_page' => -1,
            'post_status' => 'any',
            'meta_key' => 'abc_is_imported',
            'meta_value' => '1',
            'fields' => 'ids',
        ]);

        foreach ($query->posts as $id) {
            wp_delete_post($id, true);
        }

        wp_safe_redirect(admin_url('edit.php?post_type=abc_estimate&page=abc-import&deleted=1'));
        exit;
    }

    public function render_admin_notices() {
        if (isset($_GET['page']) && $_GET['page'] === 'abc-import') {
            if (isset($_GET['imported'])) {
                echo '<div class="notice notice-success"><p>Imported ' . intval($_GET['imported']) . ' items.</p></div>';
            }
            if (isset($_GET['deleted'])) {
                echo '<div class="notice notice-success"><p>Deleted all imported entries.</p></div>';
            }

            $errors = get_transient('abc_csv_errors');
            if ($errors && is_array($errors) && count($errors) > 0) {
                echo '<div class="notice notice-warning"><p><strong>Import notes:</strong></p><ul style="margin-left:18px; list-style:disc;">';
                foreach ($errors as $e) {
                    echo '<li>' . esc_html($e) . '</li>';
                }
                echo '</ul></div>';
                delete_transient('abc_csv_errors');
            }
        }
    }
}
