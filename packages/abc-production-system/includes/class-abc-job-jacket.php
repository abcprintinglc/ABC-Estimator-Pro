<?php
if (!defined('ABSPATH')) { exit; }

/**
 * Job Jacket meta boxes + saving + history log + search index.
 * Layout mirrors the physical production ticket.
 */
class ABC_Job_Jacket {

    public function __construct() {
        add_action('add_meta_boxes', [$this, 'add_meta_boxes']);
        add_action('save_post_abc_estimate', [$this, 'save_data'], 10, 2);
    }

    public function add_meta_boxes() {
        add_meta_box('abc_job_jacket_meta', 'Job Jacket (Production Ticket)', [$this, 'render_main_box'], 'abc_estimate', 'normal', 'high');
        add_meta_box('abc_history_log', 'History / Notes', [$this, 'render_history_box'], 'abc_estimate', 'side', 'default');
    }

    private function meta($post_id, $key, $default = '') {
        $v = get_post_meta($post_id, $key, true);
        return ($v === '' || $v === null) ? $default : $v;
    }

    private function due_date_display($due) {
        if (empty($due)) return '';
        $ts = strtotime($due);
        if (!$ts) return '';
        $year = (int) gmdate('Y', $ts);
        return ($year < 2026) ? '' : gmdate('Y-m-d', $ts);
    }

    private function sanitize_date($value) {
        $value = sanitize_text_field(wp_unslash($value));
        if ($value === '') return '';
        // HTML5 date input returns YYYY-MM-DD.
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) return $value;
        $ts = strtotime($value);
        return $ts ? gmdate('Y-m-d', $ts) : '';
    }

    private function sanitize_due_date($value) {
        $date = $this->sanitize_date($value);
        if ($date === '') return '';
        $year = (int) substr($date, 0, 4);
        return ($year < 2026) ? '' : $date;
    }

    private function cb_checked($post_id, $key) {
        return $this->meta($post_id, $key, '0') === '1';
    }

    public function render_main_box($post) {
        wp_nonce_field('abc_save_estimate', 'abc_estimate_nonce');

        $invoice     = esc_attr($this->meta($post->ID, 'abc_invoice_number'));
        $date_in     = esc_attr($this->meta($post->ID, 'abc_order_date'));
        $ordered     = esc_attr($this->meta($post->ID, 'abc_ordered_date'));
        $due_raw     = $this->meta($post->ID, 'abc_due_date');
        $due         = esc_attr($this->due_date_display($due_raw));

        $client_name = esc_attr($this->meta($post->ID, 'abc_client_name'));
        $client_phone= esc_attr($this->meta($post->ID, 'abc_client_phone'));

        $qty         = esc_attr($this->meta($post->ID, 'abc_qty'));
        $job_text    = esc_attr($this->meta($post->ID, 'abc_job_name_other'));
        $last_ticket = esc_attr($this->meta($post->ID, 'abc_last_ticket'));

        $stock       = esc_attr($this->meta($post->ID, 'abc_stock'));
        $finish_size = esc_attr($this->meta($post->ID, 'abc_finish_size'));

        $ship_to     = esc_attr($this->meta($post->ID, 'abc_ship_to'));
        $po_number   = esc_attr($this->meta($post->ID, 'abc_po_number'));

        $desc        = esc_textarea($this->meta($post->ID, 'abc_job_description'));

        $proof_to    = esc_attr($this->meta($post->ID, 'abc_send_proof_to'));

        $total       = esc_attr($this->meta($post->ID, 'abc_order_total'));
        $paid_status = $this->meta($post->ID, 'abc_paid_status', 'unpaid');

        $status      = $this->meta($post->ID, 'abc_status', 'estimate');
        $proof_status= $this->meta($post->ID, 'abc_proof_status', 'needed');

        $estimate_json = $this->meta($post->ID, 'abc_estimate_data', '[]');

        $is_rush     = $this->cb_checked($post->ID, 'abc_is_rush');

        ?>
        <style>
            .abcps-grid{display:grid;grid-template-columns: 1fr 1fr;gap:14px;}
            .abcps-grid .full{grid-column:span 2;}
            .abcps-row{display:flex;gap:12px;align-items:flex-end;flex-wrap:wrap;}
            .abcps-field{min-width:180px;flex:1;}
            .abcps-field label{display:block;font-weight:600;margin-bottom:4px;}
            .abcps-field input[type=text], .abcps-field input[type=date], .abcps-field textarea, .abcps-field select{width:100%;max-width:100%;}
            .abcps-section{border-top:1px solid #e5e5e5;padding-top:14px;margin-top:14px;}
            .abcps-checks{display:flex;flex-wrap:wrap;gap:12px;}
            .abcps-checks label{font-weight:600;}
            .abcps-rush{background:#ffebeb;border:1px solid #cc0000;padding:10px;border-radius:6px;color:#b32d2e;font-weight:700;}
            .abcps-mini{font-size:12px;color:#555;}
            .abcps-money{background:#f9f9f9;border:1px solid #e5e5e5;padding:12px;border-radius:8px;}
        </style>

        <!-- Header (matches printed order) -->
        <div class="abcps-grid">
            <div class="abcps-field">
                <label>Invoice # (tttt-yy)</label>
                <input type="text" name="abc_invoice_number" value="<?php echo $invoice; ?>" placeholder="1436-18" />
            </div>
            <div class="abcps-field">
                <label>Date In</label>
                <input type="date" name="abc_order_date" value="<?php echo esc_attr($date_in); ?>" />
            </div>

            <div class="abcps-field">
                <label>Promised (Due)</label>
                <input type="date" name="abc_due_date" value="<?php echo $due; ?>" />
                <div class="abcps-mini">Rule: if you pick a date before 2026, it will be left blank.</div>
            </div>
            <div class="abcps-field">
                <label>Ordered</label>
                <input type="date" name="abc_ordered_date" value="<?php echo esc_attr($ordered); ?>" />
            </div>

            <div class="abcps-field full">
                <label class="abcps-rush"><input type="checkbox" name="abc_is_rush" value="1" <?php checked($is_rush); ?> /> RUSH JOB</label>
            </div>
        </div>

        <!-- Customer -->
        <div class="abcps-section">
            <div class="abcps-grid">
                <div class="abcps-field">
                    <label>Name</label>
                    <input type="text" name="abc_client_name" value="<?php echo $client_name; ?>" placeholder="Customer / Company" />
                </div>
                <div class="abcps-field">
                    <label>Phone / Email</label>
                    <input type="text" name="abc_client_phone" value="<?php echo $client_phone; ?>" placeholder="575-555-1234" />
                </div>
            </div>
        </div>

        <!-- Flags row (NEW/REPEAT/CHANGES/PRINT-READY/COPIES/NOTES SEE BACK/SEND PROOF TO/LAST TKT #) -->
        <div class="abcps-section">
            <div class="abcps-checks">
                <label><input type="checkbox" name="abc_flag_new" value="1" <?php checked($this->cb_checked($post->ID,'abc_flag_new')); ?> /> NEW</label>
                <label><input type="checkbox" name="abc_flag_repeat" value="1" <?php checked($this->cb_checked($post->ID,'abc_flag_repeat')); ?> /> REPEAT</label>
                <label><input type="checkbox" name="abc_flag_changes" value="1" <?php checked($this->cb_checked($post->ID,'abc_flag_changes')); ?> /> CHANGES</label>
                <label><input type="checkbox" name="abc_flag_print_ready" value="1" <?php checked($this->cb_checked($post->ID,'abc_flag_print_ready')); ?> /> PRINT‑READY</label>
                <label><input type="checkbox" name="abc_flag_copies" value="1" <?php checked($this->cb_checked($post->ID,'abc_flag_copies')); ?> /> COPIES</label>
                <label><input type="checkbox" name="abc_flag_notes_back" value="1" <?php checked($this->cb_checked($post->ID,'abc_flag_notes_back')); ?> /> NOTES: SEE BACK</label>
                <label><input type="checkbox" name="abc_flag_send_proof_to" value="1" <?php checked($this->cb_checked($post->ID,'abc_flag_send_proof_to')); ?> /> SEND PROOF TO:</label>
            </div>

            <div class="abcps-row" style="margin-top:10px;">
                <div class="abcps-field">
                    <input type="text" name="abc_send_proof_to" value="<?php echo $proof_to; ?>" placeholder="email / phone" />
                </div>
                <div class="abcps-field" style="max-width:240px;">
                    <label>Last TKT #</label>
                    <input type="text" name="abc_last_ticket" value="<?php echo $last_ticket; ?>" />
                </div>
            </div>
        </div>

        <!-- Qty + Job Name (BC/APPT/RACK/BROCHURES) -->
        <div class="abcps-section">
            <div class="abcps-row">
                <div class="abcps-field" style="max-width:180px;">
                    <label>QTY</label>
                    <input type="text" name="abc_qty" value="<?php echo $qty; ?>" />
                </div>
                <div class="abcps-field">
                    <label>Job Name</label>
                    <div class="abcps-checks" style="margin-bottom:8px;">
                        <label><input type="checkbox" name="abc_job_type_bc" value="1" <?php checked($this->cb_checked($post->ID,'abc_job_type_bc')); ?> /> BC</label>
                        <label><input type="checkbox" name="abc_job_type_appt_cards" value="1" <?php checked($this->cb_checked($post->ID,'abc_job_type_appt_cards')); ?> /> APPT CARDS</label>
                        <label><input type="checkbox" name="abc_job_type_rack_cards" value="1" <?php checked($this->cb_checked($post->ID,'abc_job_type_rack_cards')); ?> /> RACK CARDS</label>
                        <label><input type="checkbox" name="abc_job_type_brochures" value="1" <?php checked($this->cb_checked($post->ID,'abc_job_type_brochures')); ?> /> BROCHURES</label>
                    </div>
                    <input type="text" name="abc_job_name_other" value="<?php echo $job_text; ?>" placeholder="(optional) custom job name" />
                </div>
            </div>
        </div>

        <!-- Job description (big area) -->
        <div class="abcps-section">
            <div class="abcps-field">
                <label>Job Details / Specs</label>
                <textarea name="abc_job_description" rows="5"><?php echo $desc; ?></textarea>
            </div>
        </div>

        <!-- Stock -->
        <div class="abcps-section">
            <div class="abcps-field">
                <label>Stock</label>
                <input type="text" name="abc_stock" value="<?php echo $stock; ?>" />
            </div>
        </div>

        <!-- Press work / setup -->
        <div class="abcps-section">
            <div class="abcps-row">
                <div class="abcps-field">
                    <label>Press Work / Set‑Up</label>
                    <div class="abcps-checks">
                        <label><input type="checkbox" name="abc_press_two_sided" value="1" <?php checked($this->cb_checked($post->ID,'abc_press_two_sided')); ?> /> 2 SIDED</label>
                        <label><input type="checkbox" name="abc_press_color" value="1" <?php checked($this->cb_checked($post->ID,'abc_press_color')); ?> /> COLOR</label>
                        <label><input type="checkbox" name="abc_press_bw" value="1" <?php checked($this->cb_checked($post->ID,'abc_press_bw')); ?> /> B/W</label>
                    </div>
                </div>
            </div>

            <div class="abcps-row" style="margin-top:10px;">
                <div class="abcps-field" style="max-width:260px;">
                    <label><input type="checkbox" name="abc_perf" value="1" <?php checked($this->cb_checked($post->ID,'abc_perf')); ?> /> PERF</label>
                    <input type="text" name="abc_perf_notes" value="<?php echo esc_attr($this->meta($post->ID,'abc_perf_notes')); ?>" placeholder="" />
                </div>
                <div class="abcps-field" style="max-width:260px;">
                    <label><input type="checkbox" name="abc_foil" value="1" <?php checked($this->cb_checked($post->ID,'abc_foil')); ?> /> FOIL</label>
                    <input type="text" name="abc_foil_notes" value="<?php echo esc_attr($this->meta($post->ID,'abc_foil_notes')); ?>" placeholder="" />
                </div>
            </div>

            <div class="abcps-row" style="margin-top:10px;">
                <div class="abcps-field" style="min-width:280px;">
                    <label><input type="checkbox" name="abc_numbering_required" value="1" <?php checked($this->cb_checked($post->ID,'abc_numbering_required')); ?> /> NUMBERING MUST BE</label>
                    <div class="abcps-row" style="margin-top:6px;">
                        <div class="abcps-field" style="max-width:180px;">
                            <label>Start</label>
                            <input type="text" name="abc_numbering_start" value="<?php echo esc_attr($this->meta($post->ID,'abc_numbering_start')); ?>" />
                        </div>
                        <div class="abcps-field" style="max-width:180px;">
                            <label>Stop</label>
                            <input type="text" name="abc_numbering_stop" value="<?php echo esc_attr($this->meta($post->ID,'abc_numbering_stop')); ?>" />
                        </div>
                    </div>
                    <div class="abcps-checks" style="margin-top:8px;">
                        <label><input type="checkbox" name="abc_numbering_black" value="1" <?php checked($this->cb_checked($post->ID,'abc_numbering_black')); ?> /> BLACK</label>
                        <label style="display:flex;align-items:center;gap:6px;">
                            COLOR:
                            <input type="text" name="abc_numbering_color_text" value="<?php echo esc_attr($this->meta($post->ID,'abc_numbering_color_text')); ?>" style="width:140px;" />
                        </label>
                    </div>
                </div>
            </div>
        </div>

        <!-- Bindery -->
        <div class="abcps-section">
            <div class="abcps-field">
                <label>Bindery</label>
                <div class="abcps-checks">
                    <label><input type="checkbox" name="abc_bindery_saddle" value="1" <?php checked($this->cb_checked($post->ID,'abc_bindery_saddle')); ?> /> SADDLE STITCH</label>
                    <label><input type="checkbox" name="abc_bindery_corner" value="1" <?php checked($this->cb_checked($post->ID,'abc_bindery_corner')); ?> /> CORNER STAPLE</label>
                    <label><input type="checkbox" name="abc_bindery_3hole" value="1" <?php checked($this->cb_checked($post->ID,'abc_bindery_3hole')); ?> /> 3‑HOLE PUNCH</label>
                </div>
            </div>

            <div class="abcps-row" style="margin-top:10px;">
                <div class="abcps-field" style="max-width:360px;">
                    <label><input type="checkbox" name="abc_wraparound" value="1" <?php checked($this->cb_checked($post->ID,'abc_wraparound')); ?> /> WRAPAROUND</label>
                    <input type="text" name="abc_wraparound_text" value="<?php echo esc_attr($this->meta($post->ID,'abc_wraparound_text')); ?>" />
                </div>
                <div class="abcps-field" style="max-width:200px;">
                    <label><input type="checkbox" name="abc_fold" value="1" <?php checked($this->cb_checked($post->ID,'abc_fold')); ?> /> FOLD</label>
                </div>
                <div class="abcps-field" style="max-width:200px;">
                    <label><input type="checkbox" name="abc_score" value="1" <?php checked($this->cb_checked($post->ID,'abc_score')); ?> /> SCORE</label>
                </div>
            </div>

            <div class="abcps-row" style="margin-top:10px;">
                <div class="abcps-field" style="max-width:240px;">
                    <label><input type="checkbox" name="abc_pad" value="1" <?php checked($this->cb_checked($post->ID,'abc_pad')); ?> /> PAD</label>
                    <input type="text" name="abc_pad_notes" value="<?php echo esc_attr($this->meta($post->ID,'abc_pad_notes')); ?>" />
                </div>
                <div class="abcps-field" style="max-width:200px;">
                    <label><input type="checkbox" name="abc_ncr" value="1" <?php checked($this->cb_checked($post->ID,'abc_ncr')); ?> /> NCR</label>
                </div>
                <div class="abcps-field" style="max-width:260px;">
                    <label><input type="checkbox" name="abc_spiral" value="1" <?php checked($this->cb_checked($post->ID,'abc_spiral')); ?> /> SPIRAL</label>
                    <input type="text" name="abc_spiral_mm" value="<?php echo esc_attr($this->meta($post->ID,'abc_spiral_mm')); ?>" placeholder="__ mm" />
                </div>
            </div>
        </div>

        <!-- Finish size -->
        <div class="abcps-section">
            <div class="abcps-field">
                <label>Finish Size</label>
                <input type="text" name="abc_finish_size" value="<?php echo $finish_size; ?>" />
            </div>
        </div>

        <!-- Delivery -->
        <div class="abcps-section">
            <div class="abcps-checks">
                <label><input type="checkbox" name="abc_deliver" value="1" <?php checked($this->cb_checked($post->ID,'abc_deliver')); ?> /> DELIVER</label>
                <label><input type="checkbox" name="abc_ship_to_flag" value="1" <?php checked($this->cb_checked($post->ID,'abc_ship_to_flag')); ?> /> SHIP TO:</label>
                <label><input type="checkbox" name="abc_pick_up" value="1" <?php checked($this->cb_checked($post->ID,'abc_pick_up')); ?> /> PICK UP</label>
            </div>
            <div class="abcps-row" style="margin-top:10px;">
                <div class="abcps-field full">
                    <input type="text" name="abc_ship_to" value="<?php echo $ship_to; ?>" placeholder="Shipping address / notes" />
                </div>
            </div>
        </div>

        <!-- Contacted on + PO -->
        <div class="abcps-section">
            <div class="abcps-checks">
                <label><input type="checkbox" name="abc_contact_email" value="1" <?php checked($this->cb_checked($post->ID,'abc_contact_email')); ?> /> EMAIL</label>
                <label><input type="checkbox" name="abc_contact_phone" value="1" <?php checked($this->cb_checked($post->ID,'abc_contact_phone')); ?> /> PHONE</label>
                <label><input type="checkbox" name="abc_contact_voicemail" value="1" <?php checked($this->cb_checked($post->ID,'abc_contact_voicemail')); ?> /> VOICEMAIL</label>
                <label><input type="checkbox" name="abc_contact_po" value="1" <?php checked($this->cb_checked($post->ID,'abc_contact_po')); ?> /> PO</label>
            </div>
            <div class="abcps-row" style="margin-top:10px;">
                <div class="abcps-field" style="max-width:360px;">
                    <label>PO #</label>
                    <input type="text" name="abc_po_number" value="<?php echo $po_number; ?>" />
                </div>
            </div>
        </div>

        <!-- Stage + Proof + Money (kept, but doesn't disrupt printed order) -->
        <div class="abcps-section abcps-money">
            <div class="abcps-grid">
                <div class="abcps-field">
                    <label>Current Stage</label>
                    <select name="abc_status">
                        <option value="estimate" <?php selected($status, 'estimate'); ?>>Draft / Estimate</option>
                        <option value="pending" <?php selected($status, 'pending'); ?>>Pending Approval</option>
                        <option value="production" <?php selected($status, 'production'); ?>>In Production</option>
                        <option value="completed" <?php selected($status, 'completed'); ?>>Completed / Picked Up</option>
                    </select>
                </div>
                <div class="abcps-field">
                    <label>Proof Status</label>
                    <select name="abc_proof_status">
                        <option value="needed" <?php selected($proof_status, 'needed'); ?>>Proof Needed</option>
                        <option value="out" <?php selected($proof_status, 'out'); ?>>Proof Out / Waiting</option>
                        <option value="approved" <?php selected($proof_status, 'approved'); ?>>Proof APPROVED</option>
                        <option value="none" <?php selected($proof_status, 'none'); ?>>No Proof Required</option>
                    </select>
                </div>
                <div class="abcps-field">
                    <label>Total Price ($)</label>
                    <input type="text" name="abc_order_total" value="<?php echo $total; ?>" />
                </div>
                <div class="abcps-field">
                    <label>Payment Status</label>
                    <select name="abc_paid_status">
                        <option value="unpaid" <?php selected($paid_status, 'unpaid'); ?>>Unpaid</option>
                        <option value="deposit" <?php selected($paid_status, 'deposit'); ?>>Deposit Paid</option>
                        <option value="paid" <?php selected($paid_status, 'paid'); ?>>PAID IN FULL</option>
                    </select>
                </div>
            </div>
        </div>

        <input type="hidden" name="abc_estimate_data" value="<?php echo esc_attr($estimate_json); ?>" />

        <p style="margin-top:14px;">
            <a href="<?php echo esc_url(home_url('/?abc_action=print_estimate&id=' . $post->ID)); ?>" target="_blank" class="button button-large">🖨️ Print Job Jacket</a>
        </p>
        <?php
    }

    public function render_history_box($post) {
        $history = get_post_meta($post->ID, 'abc_history_log', true);
        if (!is_array($history)) $history = [];

        echo '<ul style="max-height:300px; overflow-y:auto; padding-left:15px;">';
        if ($history) {
            foreach (array_reverse($history) as $h) {
                $date = isset($h['date']) ? $h['date'] : '';
                $user = isset($h['user']) ? $h['user'] : '';
                $note = isset($h['note']) ? $h['note'] : '';
                echo '<li style="margin-bottom:10px;"><small><strong>' . esc_html($date) . '</strong> (' . esc_html($user) . ')<br>' . esc_html($note) . '</small></li>';
            }
        } else {
            echo '<li>No history yet.</li>';
        }
        echo '</ul>';
        echo '<textarea name="abc_manual_note" placeholder="Add a note (e.g. Customer called, changed paper stock)…" rows="3" style="width:100%"></textarea>';
    }

    public function save_data($post_id, $post) {
        if (!isset($_POST['abc_estimate_nonce']) || !wp_verify_nonce($_POST['abc_estimate_nonce'], 'abc_save_estimate')) return;
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
        if (!current_user_can('edit_post', $post_id)) return;

        $changes = [];

        // Field map: key => type
        $fields = [
            'abc_invoice_number' => 'text',
            'abc_order_date' => 'date',
            'abc_due_date' => 'due_date',
            'abc_ordered_date' => 'date',

            'abc_client_name' => 'text',
            'abc_client_phone' => 'text',

            'abc_flag_new' => 'checkbox',
            'abc_flag_repeat' => 'checkbox',
            'abc_flag_changes' => 'checkbox',
            'abc_flag_print_ready' => 'checkbox',
            'abc_flag_copies' => 'checkbox',
            'abc_flag_notes_back' => 'checkbox',
            'abc_flag_send_proof_to' => 'checkbox',
                        'abc_send_proof_to' => 'text',
            'abc_last_ticket' => 'text',

            'abc_qty' => 'text',
            'abc_job_type_bc' => 'checkbox',
            'abc_job_type_appt_cards' => 'checkbox',
            'abc_job_type_rack_cards' => 'checkbox',
            'abc_job_type_brochures' => 'checkbox',
            'abc_job_name_other' => 'text',

            'abc_job_description' => 'textarea',
            'abc_stock' => 'text',

            'abc_press_two_sided' => 'checkbox',
            'abc_press_color' => 'checkbox',
            'abc_press_bw' => 'checkbox',

            'abc_perf' => 'checkbox',
            'abc_perf_notes' => 'text',
            'abc_foil' => 'checkbox',
            'abc_foil_notes' => 'text',

            'abc_numbering_required' => 'checkbox',
            'abc_numbering_start' => 'text',
            'abc_numbering_stop' => 'text',
            'abc_numbering_black' => 'checkbox',
            'abc_numbering_color_text' => 'text',

            'abc_bindery_saddle' => 'checkbox',
            'abc_bindery_corner' => 'checkbox',
            'abc_bindery_3hole' => 'checkbox',

            'abc_wraparound' => 'checkbox',
            'abc_wraparound_text' => 'text',
            'abc_fold' => 'checkbox',
            'abc_score' => 'checkbox',

            'abc_pad' => 'checkbox',
            'abc_pad_notes' => 'text',
            'abc_ncr' => 'checkbox',
            'abc_spiral' => 'checkbox',
            'abc_spiral_mm' => 'text',

            'abc_finish_size' => 'text',

            'abc_deliver' => 'checkbox',
            'abc_ship_to_flag' => 'checkbox',
            'abc_pick_up' => 'checkbox',
            'abc_ship_to' => 'text',

            'abc_contact_email' => 'checkbox',
            'abc_contact_phone' => 'checkbox',
            'abc_contact_voicemail' => 'checkbox',
            'abc_contact_po' => 'checkbox',
            'abc_po_number' => 'text',

            'abc_status' => 'select_status',
            'abc_proof_status' => 'select_proof',
            'abc_order_total' => 'text',
            'abc_paid_status' => 'select_paid',

            'abc_estimate_data' => 'json',
        ];

        // Rush checkbox (kept separate because it existed before)
        $old_rush = get_post_meta($post_id, 'abc_is_rush', true);
        $new_rush = isset($_POST['abc_is_rush']) ? '1' : '0';
        if ($old_rush !== $new_rush) {
            update_post_meta($post_id, 'abc_is_rush', $new_rush);
            $changes[] = 'rush changed to ' . ($new_rush === '1' ? 'YES' : 'NO');
        }

        foreach ($fields as $key => $type) {
            $old = get_post_meta($post_id, $key, true);

            $new = null;
            switch ($type) {
                case 'checkbox':
                    $new = isset($_POST[$key]) ? '1' : '0';
                    break;
                case 'textarea':
                    $new = isset($_POST[$key]) ? sanitize_textarea_field(wp_unslash($_POST[$key])) : '';
                    break;
                case 'date':
                    $new = isset($_POST[$key]) ? $this->sanitize_date($_POST[$key]) : '';
                    break;
                case 'due_date':
                    $new = isset($_POST[$key]) ? $this->sanitize_due_date($_POST[$key]) : '';
                    break;
                case 'select_status':
                    $allowed = ['estimate','pending','production','completed'];
                    $val = isset($_POST[$key]) ? sanitize_text_field(wp_unslash($_POST[$key])) : 'estimate';
                    $new = in_array($val, $allowed, true) ? $val : 'estimate';
                    break;
                case 'select_proof':
                    $allowed = ['needed','out','approved','none'];
                    $val = isset($_POST[$key]) ? sanitize_text_field(wp_unslash($_POST[$key])) : 'needed';
                    $new = in_array($val, $allowed, true) ? $val : 'needed';
                    break;
                case 'select_paid':
                    $allowed = ['unpaid','deposit','paid'];
                    $val = isset($_POST[$key]) ? sanitize_text_field(wp_unslash($_POST[$key])) : 'unpaid';
                    $new = in_array($val, $allowed, true) ? $val : 'unpaid';
                    break;
                case 'json':
                    $new = isset($_POST[$key]) ? wp_unslash($_POST[$key]) : '[]';
                    break;
                default: // text
                    $new = isset($_POST[$key]) ? sanitize_text_field(wp_unslash($_POST[$key])) : '';
            }

            if ($new !== null && $new !== $old) {
                update_post_meta($post_id, $key, $new);
                $changes[] = str_replace('abc_', '', $key) . ' updated';
            }
        }

        // Manual note
        $manual = !empty($_POST['abc_manual_note']) ? sanitize_textarea_field(wp_unslash($_POST['abc_manual_note'])) : '';

        if ($changes || $manual) {
            $log = get_post_meta($post_id, 'abc_history_log', true);
            if (!is_array($log)) $log = [];
            $user = wp_get_current_user();
            $log[] = [
                'date' => current_time('mysql'),
                'user' => $user ? $user->display_name : 'Unknown',
                'note' => trim(implode(', ', $changes) . ($manual ? " | Note: $manual" : '')),
            ];
            update_post_meta($post_id, 'abc_history_log', array_slice($log, -300));
        }

        $this->update_search_index($post_id);
    }

    private function update_search_index($post_id) {
        $invoice = $this->meta($post_id, 'abc_invoice_number');
        $client  = $this->meta($post_id, 'abc_client_name');
        $desc    = $this->meta($post_id, 'abc_job_description');
        $status  = $this->meta($post_id, 'abc_status');

        $index = trim("Inv:$invoice | $client | $status | $desc");
        $index = preg_replace('/\s+/', ' ', $index);

        // Prevent recursion.
        remove_action('save_post_abc_estimate', [$this, 'save_data'], 10);
        wp_update_post([
            'ID' => $post_id,
            'post_excerpt' => $index,
        ]);
        add_action('save_post_abc_estimate', [$this, 'save_data'], 10, 2);
    }
}
