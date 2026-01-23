ABC Estimator Pro v1.7.3

Includes:
- Custom Post Type: abc_estimate (Estimates & Jobs)
- Job Jacket Meta Box (Invoice, Order Date, Approval Date, Due Date, Rush, Status, Line Item JSON)
- Workflow Status meta (estimate/pending/production/completed)
- Manual history notes input (append-only)
- Search indexing into post_excerpt for fast searching
- Duplicate row action (Save as New)
- CSV import duplicate prevention by invoice #
- History / Change Log (meta changes tracked separately from WP revisions)
- Clean Print View: /?abc_action=print_estimate&id={post_id}
- AJAX Fast Search endpoint: action=abc_search_estimates
- Admin Log Book Dashboard (submenu: Estimator / Log → Log Book) with fast, highlighted search table
- Frontend Log Book Shortcode: [abc_estimator_pro] (searchable table + “New Estimate” button)
- CSV Import + Bulk Delete tools (submenu: Estimator / Log → Import / Data Tools)

Notes:
- Line item grid UI is intended to mount into #abc-react-estimate-builder-mount and store JSON in #abc_estimate_data.