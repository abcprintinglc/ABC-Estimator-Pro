/* global jQuery, ABC_ESTIMATOR_PRO */
(function ($) {
  'use strict';

  function escapeHtml(str) {
    if (str === null || str === undefined) return '';
    return String(str)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#039;');
  }

  function renderRows(rows) {
    // Both admin + frontend templates use <tbody id="abc-log-results"></tbody>
    // (Older builds used a wrapper element containing a <tbody>, hence the prior selector.)
    var $tbody = $('#abc-log-results');
    var $noResults = $('#abc-no-results');

    if (!$tbody.length) return;

    $tbody.empty();

    if (!rows || !rows.length) {
      $noResults.show();
      return;
    }

    $noResults.hide();

    rows.forEach(function (row) {
      var due = row.due_date ? escapeHtml(row.due_date) : '—';
      var rush = row.is_rush ? ' <span style="color:#b32d2e; font-weight:bold;">(RUSH)</span>' : '';

      var stage = row.stage ? String(row.stage).toLowerCase() : 'estimate';
      var statusClass = 'status-' + stage;

      var urgencyClass = row.urgency === 'urgent' ? 'abc-row-urgent' : (row.urgency === 'warning' ? 'abc-row-warning' : '');

      var clientOrTitle = row.client && row.client.length ? row.client : row.title;

      var jobJacketBtn = row.edit_url
        ? '<a href="' + escapeHtml(row.edit_url) + '" class="button" target="_blank">Job Jacket</a>'
        : '';

      var printBtn = row.print_url
        ? '<a href="' + escapeHtml(row.print_url) + '" class="button" target="_blank">Print</a>'
        : '';

      var html = '' +
        '<tr class="' + urgencyClass + '">' +
          '<td><strong>' + escapeHtml(row.invoice || '---') + '</strong></td>' +
          '<td>' + escapeHtml(clientOrTitle || '') + '</td>' +
          '<td><span class="abc-pill ' + statusClass + '">' + escapeHtml(row.stage || 'estimate') + '</span></td>' +
          '<td>' + due + rush + '</td>' +
          '<td style="white-space:nowrap;">' + jobJacketBtn + ' ' + printBtn + '</td>' +
        '</tr>';

      $tbody.append(html);
    });
  }

  function fetchResults(term) {
    var $spinner = $('#abc-spinner, #abc-admin-spinner');
    $spinner.addClass('is-active').show();

    return $.post(ABC_ESTIMATOR_PRO.ajax_url, {
      action: 'abc_search_estimates',
      nonce: ABC_ESTIMATOR_PRO.nonce,
      term: term || ''
    }).done(function (res) {
      if (res && res.success) {
        renderRows(res.data);
      } else {
        renderRows([]);
      }
    }).fail(function () {
      // Don't spam alerts in production; just clear.
      renderRows([]);
    }).always(function () {
      $spinner.removeClass('is-active').hide();
    });
  }

  $(document).ready(function () {
    var $input = $('#abc-log-search, #abc-frontend-search');
    if (!$input.length) return;

    var delayTimer;

    $input.on('input', function () {
      clearTimeout(delayTimer);
      var term = $(this).val();
      delayTimer = setTimeout(function () {
        fetchResults(term);
      }, 250);
    });

    // Initial load
    fetchResults('');
  });

})(jQuery);
