<?php
if (!defined('ABSPATH')) { exit; }

/**
 * Registers the Job Jacket CPT and all meta keys.
 */
class ABC_CPT {
    public function __construct() {
        add_action('init', [$this, 'register']);
        add_action('init', [$this, 'register_meta']);
    }

    public function register() {
        register_post_type('abc_estimate', [
            'labels' => [
                'name'          => 'Estimates & Jobs',
                'singular_name' => 'Job',
                'menu_name'     => 'Estimator / Log',
                'add_new_item'  => 'New Job Jacket',
                'edit_item'     => 'Edit Job Jacket',
            ],
            'public'              => false,
            'show_ui'             => true,
            'show_in_menu'        => true,
            'supports'            => ['title', 'revisions', 'author'],
            'menu_icon'           => 'dashicons-media-spreadsheet',
            'map_meta_cap'        => true,
            'capability_type' => 'post',
            'exclude_from_search' => true,
        ]);
    }

    public function register_meta() {
        $keys = [
            // Core
            'abc_invoice_number',
            'abc_order_date',
            'abc_due_date',
            'abc_approval_date',
            'abc_is_rush',
            'abc_status',
            'abc_estimate_data',
            'abc_history_log',
            'abc_is_imported',

            // Customer / Office
            'abc_client_name',
            'abc_client_phone',
            'abc_order_total',
            'abc_paid_status',
            'abc_proof_status',

            // Physical Job Jacket fields (kept in the same order you write them)
            'abc_promised_date',
            'abc_ordered_date',
            'abc_last_ticket',
            'abc_flag_new',
            'abc_flag_repeat',
            'abc_flag_changes',
            'abc_flag_print_ready',
            'abc_flag_copies',
            'abc_flag_notes_back',
            'abc_flag_send_proof_to',
            'abc_send_proof_to',
            'abc_qty',
            'abc_job_type_bc',
            'abc_job_type_appt_cards',
            'abc_job_type_rack_cards',
            'abc_job_type_brochures',
            'abc_job_name_other',
            'abc_job_description',
            'abc_stock',
            'abc_press_two_sided',
            'abc_press_color',
            'abc_press_bw',
            'abc_perf',
            'abc_perf_notes',
            'abc_foil',
            'abc_foil_notes',
            'abc_numbering_required',
            'abc_numbering_color_text',
            'abc_numbering_black',
            'abc_numbering_start',
            'abc_numbering_stop',
            'abc_bindery_saddle',
            'abc_bindery_corner',
            'abc_bindery_3hole',
            'abc_wraparound',
            'abc_wraparound_text',
            'abc_fold',
            'abc_score',
            'abc_pad',
            'abc_pad_notes',
            'abc_ncr',
            'abc_spiral',
            'abc_spiral_mm',
            'abc_finish_size',
            'abc_deliver',
            'abc_ship_to_flag',
            'abc_ship_to',
            'abc_ship_to_text',
            'abc_pick_up',
            'abc_contact_email',
            'abc_contact_phone',
            'abc_contact_voicemail',
            'abc_contact_po',
            'abc_po_number',
        ];

        foreach ($keys as $key) {
            register_post_meta('abc_estimate', $key, [
                'type'          => ($key === 'abc_history_log') ? 'array' : 'string',
                'single'        => true,
                'show_in_rest'  => false,
                'auth_callback' => function () { return current_user_can('edit_posts'); },
            ]);
        }
    }
}
