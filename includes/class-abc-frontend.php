<?php
if (!defined('ABSPATH')) { exit; }

/**
 * Frontend display: [abc_estimator_pro]
 * Renders a searchable Log Book table for quick shop-floor access.
 */
class ABC_Frontend_Display {

    public function __construct() {
        add_shortcode('abc_estimator_pro', [$this, 'render_log_book']);
        add_action('wp_enqueue_scripts', [$this, 'enqueue_frontend_scripts']);
    }

    /**
     * Render search input + results table shell.
     */
    public function render_log_book($atts = []) {
        // Only logged-in staff can see this.
        if (!current_user_can('edit_posts')) {
            return '<p>Please log in to access the Estimator Log Book.</p>';
        }

        ob_start();
        ?>
        <div class="abc-estimator-frontend">
            <div style="display:flex; justify-content:space-between; align-items:center; gap:12px; margin-bottom: 20px; flex-wrap:wrap;">
                <div class="abc-controls" style="display:flex; gap:10px; align-items:center; flex-grow:1; max-width: 520px;">
                    <input type="text" id="abc-frontend-search" placeholder="Search invoice #, client, job name, keywords..." style="width: 100%; padding: 10px; font-size: 16px;">
                    <span class="spinner" id="abc-spinner" style="display:none;">Loading...</span>
                </div>

                <div>
                    <a href="<?php echo esc_url(admin_url('post-new.php?post_type=abc_estimate')); ?>" target="_blank" rel="noopener" class="button button-primary" style="padding:10px 18px; text-decoration:none;">+ New Estimate</a>
                </div>
            </div>

            <table class="abc-log-table widefat striped" style="width:100%; border-collapse: collapse;">
                <thead>
                    <tr style="background:#f1f1f1; text-align:left;">
                        <th style="padding:10px;">Invoice #</th>
                        <th style="padding:10px;">Job / Client</th>
                        <th style="padding:10px;">Stage</th>
                        <th style="padding:10px;">Due Date</th>
                        <th style="padding:10px;">Actions</th>
                    </tr>
                </thead>
                <tbody id="abc-log-results"></tbody>
            </table>

            <p id="abc-no-results" style="display:none; color:#666; margin-top:20px;">No estimates found.</p>
        </div>

        <style>
            .abc-log-table th, .abc-log-table td { border-bottom: 1px solid #ddd; padding: 10px; }
            .abc-row-urgent { background-color: #ffebeb; color: #b32d2e; font-weight: bold; }
            .abc-row-warning { background-color: #fff7e6; }
            .abc-pill { padding: 4px 8px; border-radius: 4px; font-size: 12px; text-transform: uppercase; font-weight: bold; display:inline-block; }
            .status-production { background: #e6fffa; color: #2c7a7b; }
            .status-estimate { background: #edf2f7; color: #4a5568; }
            .status-pending { background: #ebf8ff; color: #2b6cb0; }
            .status-completed { background: #f0fff4; color: #2f855a; }
        </style>
        <?php
        return ob_get_clean();
    }

    /**
     * Enqueue JS only on pages that contain the shortcode.
     */
    public function enqueue_frontend_scripts() {
        global $post;
        if (!is_a($post, 'WP_Post')) {
            return;
        }
        if (!has_shortcode($post->post_content, 'abc_estimator_pro')) {
            return;
        }

        wp_enqueue_script(
            'abc-estimator-pro-frontend',
            ABC_ESTIMATOR_PRO_URL . 'assets/app.js',
            ['jquery'],
            ABC_ESTIMATOR_PRO_VERSION,
            true
        );

        wp_localize_script('abc-estimator-pro-frontend', 'ABC_ESTIMATOR_PRO', [
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce'    => wp_create_nonce('abc_log_book_nonce'),
        ]);
    }
}
