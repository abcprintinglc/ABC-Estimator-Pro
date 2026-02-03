<?php
if (!defined('ABSPATH')) { exit; }

$post_id = absint($_GET['id'] ?? 0);
$post = get_post($post_id);

$invoice_num   = (string) get_post_meta($post_id, 'abc_invoice_number', true);
$order_date    = (string) get_post_meta($post_id, 'abc_order_date', true);
$due_date      = (string) get_post_meta($post_id, 'abc_due_date', true);
$approval_date = (string) get_post_meta($post_id, 'abc_approval_date', true);
$is_rush       = (string) get_post_meta($post_id, 'abc_is_rush', true);
$status        = (string) get_post_meta($post_id, 'abc_status', true);
$estimate_json = (string) get_post_meta($post_id, 'abc_estimate_data', true);

$items = [];
if ($estimate_json !== '') {
    $decoded = json_decode(wp_unslash($estimate_json), true);
    if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
        $items = $decoded;
    }
}

?><!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title><?php echo esc_html($post ? $post->post_title : 'Estimate'); ?></title>
    <style>
        body{font-family:system-ui,-apple-system,Segoe UI,Roboto,Helvetica,Arial,sans-serif;margin:24px;color:#111}
        h1{margin:0 0 10px;font-size:22px}
        .meta{display:flex;flex-wrap:wrap;gap:14px;margin-bottom:14px}
        .meta .pill{border:1px solid #ddd;border-radius:999px;padding:6px 10px;font-size:12px}
        .rush{border-color:#b32d2e;color:#b32d2e;font-weight:700}
        table{width:100%;border-collapse:collapse;margin-top:14px}
        th,td{border:1px solid #ddd;padding:8px;font-size:12px;vertical-align:top}
        th{background:#f7f7f7;text-align:left}
        .footer{margin-top:18px;font-size:11px;color:#555}
        @media print{
            body{margin:0}
            .no-print{display:none}
        }
    </style>
</head>
<body>

<div class="no-print" style="margin-bottom:12px;">
    <button onclick="window.print()">Print</button>
</div>

<h1><?php echo esc_html($post ? $post->post_title : 'Estimate'); ?></h1>

<div class="meta">
    <?php if ($invoice_num !== ''): ?>
        <div class="pill"><strong>Invoice:</strong> <?php echo esc_html($invoice_num); ?></div>
    <?php endif; ?>
    <?php if ($status !== ''): ?>
        <div class="pill"><strong>Status:</strong> <?php echo esc_html($status); ?></div>
    <?php endif; ?>
    <?php if ($order_date !== ''): ?>
        <div class="pill"><strong>Order Date:</strong> <?php echo esc_html($order_date); ?></div>
    <?php endif; ?>
    <?php if ($approval_date !== ''): ?>
        <div class="pill"><strong>Approval Date:</strong> <?php echo esc_html($approval_date); ?></div>
    <?php endif; ?>
    <?php if ($due_date !== ''): ?>
        <div class="pill"><strong>Due Date:</strong> <?php echo esc_html($due_date); ?></div>
    <?php endif; ?>
    <?php if ($is_rush === '1'): ?>
        <div class="pill rush">RUSH</div>
    <?php endif; ?>
</div>

<?php if (!empty($items) && is_array($items)): ?>
    <table>
        <thead>
            <tr>
                <th style="width:48px;">#</th>
                <th>Item</th>
                <th style="width:90px;">Qty</th>
                <th style="width:120px;">Price</th>
                <th style="width:140px;">Total</th>
            </tr>
        </thead>
        <tbody>
        <?php
        $i = 0;
        foreach ($items as $row) {
            $i++;
            if (!is_array($row)) { continue; }
            $name = isset($row['name']) ? (string)$row['name'] : (isset($row['item']) ? (string)$row['item'] : '');
            $qty  = isset($row['qty']) ? (string)$row['qty'] : (isset($row['quantity']) ? (string)$row['quantity'] : '');
            $price = isset($row['price']) ? (string)$row['price'] : '';
            $total = isset($row['total']) ? (string)$row['total'] : '';
            echo '<tr>';
            echo '<td>' . esc_html((string)$i) . '</td>';
            echo '<td>' . esc_html($name) . '</td>';
            echo '<td>' . esc_html($qty) . '</td>';
            echo '<td>' . esc_html($price) . '</td>';
            echo '<td>' . esc_html($total) . '</td>';
            echo '</tr>';
        }
        ?>
        </tbody>
    </table>
<?php else: ?>
    <p><em>No line items saved yet.</em></p>
<?php endif; ?>

<div class="footer">
    Generated <?php echo esc_html(current_time('Y-m-d H:i')); ?>
</div>

</body>
</html>
