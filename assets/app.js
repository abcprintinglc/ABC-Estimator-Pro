/* global jQuery, ABC_ESTIMATOR_PRO */
(function ($) {
  'use strict';

  // Admin fallback search (legacy) uses #abc-log-search + #abc-log-results as a container.
  // Frontend shortcode uses #abc-frontend-search + <tbody id="abc-log-results">.

  function escapeHtml(str) {
    if (str === null || str === undefined) return '';
    return String(str)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#039;');
  }

  function initFrontendTable() {
    var $input = $('#abc-frontend-search');
    var $tbody = $('#abc-log-results');
    var $spinner = $('#abc-spinner');
    var $noResults = $('#abc-no-results');

    if (!$input.length || !$tbody.length) return;

    var delayTimer = null;

    function fetchResults(term) {
      if ($spinner && $spinner.length) $spinner.show();

      $.post(ABC_ESTIMATOR_PRO.ajax_url, {
        action: 'abc_search_estimates',
        nonce: ABC_ESTIMATOR_PRO.nonce,
        term: term || ''
      })
        .done(function (res) {
          if ($spinner && $spinner.length) $spinner.hide();
          if (res && res.success) {
            renderRows(res.data || []);
          } else {
            renderRows([]);
          }
        })
        .fail(function () {
          if ($spinner && $spinner.length) $spinner.hide();
          renderRows([]);
          // eslint-disable-next-line no-console
          console.error('ABC Estimator Pro: search failed');
        });
    }

    function renderRows(rows) {
      $tbody.empty();

      if (!Array.isArray(rows) || rows.length === 0) {
        if ($noResults && $noResults.length) $noResults.show();
        return;
      }
      if ($noResults && $noResults.length) $noResults.hide();

      rows.forEach(function (row) {
        var stage = (row.stage || 'estimate').toString();
        var urgency = (row.urgency || 'normal').toString();

        var statusClass = 'status-' + stage;
        var trClass = '';
        if (urgency === 'urgent') trClass = 'abc-row-urgent';
        if (urgency === 'warning') trClass = 'abc-row-warning';

        var invoice = escapeHtml(row.invoice || '---');
        var title = escapeHtml(row.title || '');
        var due = escapeHtml(row.due_date || '');
        var isRush = !!row.is_rush;

        var rushHtml = isRush ? ' <span style="color:red; font-weight:bold;">(RUSH)</span>' : '';
        var stageHtml = '<span class="abc-pill ' + escapeHtml(statusClass) + '">' + escapeHtml(stage) + '</span>';

        var actions = '';
        if (row.edit_url) {
          actions += '<a href="' + escapeHtml(row.edit_url) + '" class="button button-small" target="_blank" rel="noopener">Edit</a> ';
        }
        if (row.print_url) {
          actions += '<a href="' + escapeHtml(row.print_url) + '" class="button button-small" target="_blank" rel="noopener">Print</a>';
        }

        var html =
          '<tr class="' + escapeHtml(trClass) + '">' +
            '<td><strong>' + invoice + '</strong></td>' +
            '<td>' + title + '</td>' +
            '<td>' + stageHtml + '</td>' +
            '<td>' + due + rushHtml + '</td>' +
            '<td>' + actions + '</td>' +
          '</tr>';

        $tbody.append(html);
      });
    }

    $input.on('input', function () {
      clearTimeout(delayTimer);
      var term = $(this).val();
      delayTimer = setTimeout(function () {
        fetchResults(term);
      }, 300);
    });

    // Initial load
    fetchResults('');
  }

  function initAdminFastSearch() {
    var $input = $('#abc-log-search');
    var $results = $('#abc-log-results');

    // If frontend is active, don't also run admin renderer against the same element.
    if ($('#abc-frontend-search').length) return;
    if (!$input.length || !$results.length) return;

    var timeout = null;

    function render(rows) {
      if (!Array.isArray(rows) || !rows.length) {
        $results.html('<p><em>No results</em></p>');
        return;
      }

      var html =
        '<table class="widefat striped"><thead><tr>' +
        '<th>Invoice</th><th>Title</th><th>Stage</th><th>Due</th><th>Rush</th><th>Actions</th>' +
        '</tr></thead><tbody>';

      rows.forEach(function (r) {
        html +=
          '<tr class="' + (r.urgency_class || '') + '">' +
          '<td>' + escapeHtml(r.invoice || '') + '</td>' +
          '<td>' + escapeHtml(r.title || '') + '</td>' +
          '<td>' + escapeHtml(r.stage || '') + '</td>' +
          '<td>' + escapeHtml(r.due_date || '') + '</td>' +
          '<td>' + (r.is_rush ? 'Yes' : '') + '</td>' +
          '<td>' +
          (r.edit_url
            ? '<a class="button button-small" href="' + escapeHtml(r.edit_url) + '">Edit</a> '
            : '') +
          (r.print_url
            ? '<a class="button button-small" target="_blank" rel="noopener" href="' + escapeHtml(r.print_url) + '">Print</a>'
            : '') +
          '</td>' +
          '</tr>';
      });

      html += '</tbody></table>';
      $results.html(html);
    }

    function search(term) {
      $.post(ABC_ESTIMATOR_PRO.ajax_url, {
        action: 'abc_search_estimates',
        nonce: ABC_ESTIMATOR_PRO.nonce,
        term: term || ''
      })
        .done(function (res) {
          if (res && res.success) {
            render(res.data || []);
          } else {
            $results.html('<p><em>Error searching.</em></p>');
          }
        })
        .fail(function () {
          $results.html('<p><em>Error searching.</em></p>');
        });
    }

    $input.on('input', function () {
      clearTimeout(timeout);
      var term = $(this).val();
      timeout = setTimeout(function () {
        search(term);
      }, 250);
    });

    search($input.val());
  }

  $(function () {
    initFrontendTable();
    initAdminFastSearch();
  });
})(jQuery);
