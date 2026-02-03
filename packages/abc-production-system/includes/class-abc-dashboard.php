<?php
if (!defined('ABSPATH')) { exit; }

class ABC_Dashboard {

    public function __construct() {
        add_action('admin_menu', [$this, 'register_admin_page']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_assets']);
    }

    public function register_admin_page() {
        add_submenu_page(
            'edit.php?post_type=abc_estimate',
            'Log Book',
            'Log Book',
            'edit_posts',
            'abc-log-book',
            [$this, 'render_admin_page']
        );
    }

    public function render_admin_page() {
        ?>
        <div class="wrap">
            <h1 class="wp-heading-inline">Estimator Log Book</h1>
            <a href="<?php echo esc_url(admin_url('post-new.php?post_type=abc_estimate')); ?>" class="page-title-action">New Job Jacket</a>

            <div style="margin-top: 20px; background: #fff; padding: 20px; border: 1px solid #ccd0d4; box-shadow: 0 1px 1px rgba(0,0,0,.04);">
                <div style="display:flex; gap:10px; margin-bottom:15px; align-items:center;">
                    <input type="text" id="abc-log-search" placeholder="Search invoice #, client, job name, keywords..." style="width:100%; max-width:520px; padding:8px; font-size:15px;">
                    <span class="spinner" id="abc-admin-spinner" style="float:none; margin:0;"></span>
                </div>

                <table class="widefat striped" style="width:100%; border-collapse:collapse;">
                    <thead>
                        <tr>
                            <th style="width:160px;">Invoice #</th>
                            <th>Job / Client</th>
                            <th style="width:140px;">Stage</th>
                            <th style="width:140px;">Due Date</th>
                            <th style="width:220px;">Actions</th>
                        </tr>
                    </thead>
                    <tbody id="abc-log-results"></tbody>
                </table>

                <p id="abc-no-results" style="display:none; color:#666; margin-top:14px;">No jobs found.</p>
            </div>
        </div>
        <?php
    }

    public function enqueue_admin_assets($hook) {
        if (strpos($hook, 'abc-log-book') === false) return;

        wp_enqueue_script('abc-app', ABCPS_URL . 'assets/app.js', ['jquery'], ABCPS_VERSION, true);
        wp_localize_script('abc-app', 'ABC_ESTIMATOR_PRO', [
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce'    => wp_create_nonce('abc_log_book_nonce')
        ]);

        // Basic urgency + pill styles (matches frontend)
        $css = "
            #abc-log-results td, #abc-log-results th { vertical-align: middle; }
            .abc-row-urgent { background-color: #ffebeb !important; color: #b32d2e; font-weight: 600; }
            .abc-row-warning { background-color: #fff7e6 !important; }
            .abc-pill { padding: 4px 8px; border-radius: 4px; font-size: 11px; text-transform: uppercase; font-weight: 700; display:inline-block; }
            .status-production { background: #e6fffa; color: #2c7a7b; }
            .status-estimate { background: #edf2f7; color: #4a5568; }
            .status-pending { background: #ebf8ff; color: #2b6cb0; }
            .status-completed { background: #f0fff4; color: #2f855a; }
        ";
        wp_add_inline_style('wp-admin', $css);
    }
}
