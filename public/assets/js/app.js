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
            row.querySelectorAll('input').forEach(function (i) { i.value = i.name === 'item_quantity[]' ? '1' : ''; });
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

    // PWA-Installation aktiv anbieten (eigener Hinweis statt nur Browser-Default).
    (function () {
        var banner = document.getElementById('nova-install');
        if (!banner) return;
        var btn = document.getElementById('nova-install-btn');
        var closeBtn = banner.querySelector('.install-close');
        var textEl = banner.querySelector('.install-text');

        var standalone = window.matchMedia('(display-mode: standalone)').matches || window.navigator.standalone === true;
        if (standalone) return;                                   // läuft bereits als App
        if (sessionStorage.getItem('nova-install-hidden')) return; // in dieser Sitzung ausgeblendet

        var deferred = null;
        var show = function () { banner.classList.remove('hidden'); };

        // Android/Chrome: Event abfangen und eigenen Button zeigen.
        window.addEventListener('beforeinstallprompt', function (e) {
            e.preventDefault();
            deferred = e;
            show();
        });
        if (btn) {
            btn.addEventListener('click', function () {
                if (!deferred) return;
                deferred.prompt();
                deferred.userChoice.then(function () {
                    deferred = null;
                    banner.classList.add('hidden');
                });
            });
        }
        window.addEventListener('appinstalled', function () { banner.classList.add('hidden'); });

        // iOS/Safari: kein Prompt möglich -> Hinweis anzeigen.
        var ua = window.navigator.userAgent.toLowerCase();
        var isIOS = /iphone|ipad|ipod/.test(ua);
        var isSafari = isIOS && !/crios|fxios/.test(ua);
        if (isIOS && isSafari) {
            if (textEl) textEl.textContent = '📲 Installieren: unten auf „Teilen" tippen und „Zum Home-Bildschirm".';
            if (btn) btn.style.display = 'none';
            show();
        }

        if (closeBtn) {
            closeBtn.addEventListener('click', function () {
                banner.classList.add('hidden');
                try { sessionStorage.setItem('nova-install-hidden', '1'); } catch (e) {}
            });
        }
    })();

    // Hell/Dunkel-Umschaltung; Auswahl in localStorage merken.
    window.novaToggleTheme = function () {
        var cur = document.documentElement.getAttribute('data-theme') === 'dark' ? 'dark' : 'light';
        var next = cur === 'dark' ? 'light' : 'dark';
        document.documentElement.setAttribute('data-theme', next);
        try { localStorage.setItem('nova-theme', next); } catch (e) {}
    };

    // Position aus dem Leistungskatalog als neue Zeile einfügen.
    window.novaCatalogAdd = function (sel) {
        var opt = sel.options[sel.selectedIndex];
        if (!opt || !opt.value) return;
        var tpl = document.getElementById('item-row-template');
        var body = document.getElementById('items-body');
        if (!tpl || !body) return;
        var frag = tpl.content.cloneNode(true);
        var row = frag.querySelector('tr');
        var d = row.querySelector('input[name="item_description[]"]');
        var u = row.querySelector('input[name="item_unit[]"]');
        var p = row.querySelector('input[name="item_unit_price[]"]');
        if (d) d.value = opt.value;
        if (u) u.value = opt.getAttribute('data-unit') || 'Stk';
        if (p) p.value = opt.getAttribute('data-price') || '0,00';
        body.appendChild(frag);
        sel.value = '';
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
