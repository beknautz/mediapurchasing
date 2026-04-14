/* ============================================================
   MediaBuy Pro — Application JavaScript
   ============================================================ */

'use strict';

// ----------------------------------------------------------------
// HTMX global config
// ----------------------------------------------------------------
document.addEventListener('DOMContentLoaded', function () {
    htmx.config.defaultSwapStyle    = 'outerHTML';
    htmx.config.defaultSwapDelay    = 0;
    htmx.config.defaultSettleDelay  = 20;
    htmx.config.historyCacheSize    = 0;    // disable history cache for data app
});

// ----------------------------------------------------------------
// Auto-dismiss alerts after 5 seconds
// ----------------------------------------------------------------
document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('.alert-dismissible').forEach(function (el) {
        setTimeout(function () {
            var bsAlert = bootstrap.Alert.getOrCreateInstance(el);
            bsAlert.close();
        }, 5000);
    });
});

// Re-run after HTMX swaps
document.addEventListener('htmx:afterSwap', function () {
    document.querySelectorAll('.alert-dismissible').forEach(function (el) {
        setTimeout(function () {
            var bsAlert = bootstrap.Alert.getOrCreateInstance(el);
            if (bsAlert) bsAlert.close();
        }, 5000);
    });
});

// ----------------------------------------------------------------
// Line item row management (media buy form)
// ----------------------------------------------------------------
window.addLineItem = function () {
    var tbody = document.getElementById('lineItemsBody');
    if (!tbody) return;
    var idx   = tbody.rows.length;
    var row   = document.createElement('tr');
    row.innerHTML = `
        <td><input type="text"   name="items[${idx}][description]" class="form-control form-control-sm" placeholder="Description" required></td>
        <td><input type="text"   name="items[${idx}][placement]"   class="form-control form-control-sm" placeholder="e.g. AM Drive"></td>
        <td><input type="number" name="items[${idx}][spots]"       class="form-control form-control-sm item-spots"    value="1" min="1"></td>
        <td><input type="number" name="items[${idx}][unit_cost]"   class="form-control form-control-sm item-unitcost" step="0.01" value="0.00"></td>
        <td><input type="number" name="items[${idx}][total_cost]"  class="form-control form-control-sm item-total"    step="0.01" value="0.00" readonly></td>
        <td><button type="button" class="btn btn-sm btn-outline-danger" onclick="removeLineItem(this)"><i class="bi bi-trash"></i></button></td>`;
    tbody.appendChild(row);
    row.querySelectorAll('.item-spots, .item-unitcost').forEach(function (inp) {
        inp.addEventListener('input', calcRowTotal);
    });
};

window.removeLineItem = function (btn) {
    btn.closest('tr').remove();
    updateGrandTotal();
};

function calcRowTotal(e) {
    var row   = e.target.closest('tr');
    var spots = parseFloat(row.querySelector('.item-spots').value)    || 0;
    var unit  = parseFloat(row.querySelector('.item-unitcost').value) || 0;
    var total = spots * unit;
    row.querySelector('.item-total').value = total.toFixed(2);
    updateGrandTotal();
}

function updateGrandTotal() {
    var totals = document.querySelectorAll('.item-total');
    var grand  = 0;
    totals.forEach(function (t) { grand += parseFloat(t.value) || 0; });
    var el = document.getElementById('grandTotal');
    if (el) el.textContent = '$' + grand.toLocaleString('en-US', { minimumFractionDigits: 2 });
    var hidden = document.getElementById('originalCostInput');
    if (hidden) hidden.value = grand.toFixed(2);
}

// Bind existing rows on page load
document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('.item-spots, .item-unitcost').forEach(function (inp) {
        inp.addEventListener('input', calcRowTotal);
    });
});

// ----------------------------------------------------------------
// Confirm before destructive actions
// ----------------------------------------------------------------
document.addEventListener('click', function (e) {
    var el = e.target.closest('[data-confirm]');
    if (el) {
        if (!confirm(el.dataset.confirm)) {
            e.preventDefault();
            e.stopPropagation();
        }
    }
});

// ----------------------------------------------------------------
// Client-side search filter for tables
// ----------------------------------------------------------------
window.tableFilter = function (inputId, tableId) {
    var input  = document.getElementById(inputId);
    var table  = document.getElementById(tableId);
    if (!input || !table) return;
    var filter = input.value.toLowerCase();
    table.querySelectorAll('tbody tr').forEach(function (row) {
        var text = row.textContent.toLowerCase();
        row.style.display = text.includes(filter) ? '' : 'none';
    });
};

// ----------------------------------------------------------------
// Template variable preview
// ----------------------------------------------------------------
window.previewTemplate = function (htmlContent, containerEl) {
    if (containerEl) containerEl.innerHTML = htmlContent;
};

// ----------------------------------------------------------------
// Approval portal — reveal reason field on revision_requested
// ----------------------------------------------------------------
document.addEventListener('DOMContentLoaded', function () {
    var responseRadios = document.querySelectorAll('input[name="response"]');
    var notesGroup     = document.getElementById('revisionNotesGroup');
    if (!responseRadios.length || !notesGroup) return;

    function toggleNotes() {
        var selected = document.querySelector('input[name="response"]:checked');
        notesGroup.style.display = (selected && selected.value === 'revision_requested') ? '' : 'none';
    }
    responseRadios.forEach(function (r) { r.addEventListener('change', toggleNotes); });
    toggleNotes();
});
