// Nova – minimales Vanilla-JS (kein Framework, kein Build).
(function () {
    'use strict';

    // Live-Filterung von Tabellen über ein Suchfeld mit data-table-filter="<tableId>".
    document.querySelectorAll('[data-table-filter]').forEach(function (input) {
        var table = document.getElementById(input.getAttribute('data-table-filter'));
        if (!table) return;
        input.addEventListener('input', function () {
            var q = input.value.toLowerCase().trim();
            table.querySelectorAll('tbody tr').forEach(function (row) {
                row.style.display = row.textContent.toLowerCase().indexOf(q) !== -1 ? '' : 'none';
            });
        });
    });

    // Positionszeilen in Angebots-/Rechnungsformularen hinzufügen/entfernen.
    window.novaAddRow = function () {
        var tpl = document.getElementById('item-row-template');
        var body = document.getElementById('items-body');
        if (!tpl || !body) return;
        body.appendChild(tpl.content.cloneNode(true));
    };
    window.novaRemoveRow = function (btn) {
        var body = document.getElementById('items-body');
        var row = btn.closest('tr');
        if (body && body.querySelectorAll('.item-row').length > 1) {
            row.remove();
        } else {
            // Letzte Zeile nicht entfernen, nur leeren.
            row.querySelectorAll('input').forEach(function (i) { i.value = i.name === 'item_quantity[]' ? '1' : (i.name === 'item_unit[]' ? 'Stk' : ''); });
        }
    };

    // Ausgewählte Dateinamen neben einem (versteckten) Datei-Input anzeigen.
    window.novaShowFiles = function (input, targetId) {
        var el = document.getElementById(targetId);
        if (!el) return;
        var names = Array.prototype.map.call(input.files || [], function (f) { return f.name; });
        el.textContent = names.length ? names.join(', ') : '';
        el.classList.toggle('hidden', names.length === 0);
    };

    // Zahlart-Select: Freitextfeld bei „Andere…" ein-/ausblenden.
    window.novaMethodToggle = function (select, customId) {
        var el = document.getElementById(customId);
        if (!el) return;
        var custom = select.value === '__custom__';
        el.classList.toggle('hidden', !custom);
        if (custom) { el.focus(); } else { el.value = ''; }
    };

    // Hell/Dunkel-Umschaltung; Auswahl in localStorage merken.
    window.novaToggleTheme = function () {
        var cur = document.documentElement.getAttribute('data-theme') === 'dark' ? 'dark' : 'light';
        var next = cur === 'dark' ? 'light' : 'dark';
        document.documentElement.setAttribute('data-theme', next);
        try { localStorage.setItem('nova-theme', next); } catch (e) {}
    };

    // Bestätigungsdialog für Formulare/Buttons mit data-confirm="Text".
    document.querySelectorAll('[data-confirm]').forEach(function (el) {
        el.addEventListener('submit', function (e) {
            if (!window.confirm(el.getAttribute('data-confirm'))) {
                e.preventDefault();
            }
        });
        el.addEventListener('click', function (e) {
            if (el.tagName !== 'FORM' && !window.confirm(el.getAttribute('data-confirm'))) {
                e.preventDefault();
            }
        });
    });
})();
