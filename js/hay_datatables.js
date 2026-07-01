/**
 * Tablas con paginación (25 por hoja) y área con scroll.
 * Requiere jQuery + DataTables cargados en dashboard.php.
 */
(function (global) {
    'use strict';

    const PAGE_SIZE_DEFAULT = 25;
    const SCROLL_Y = '65vh';

    const LANG_ES = {
        emptyTable: 'Sin registros',
        info: 'Mostrando _START_ a _END_ de _TOTAL_',
        infoEmpty: 'Sin registros',
        infoFiltered: '(filtrado de _MAX_ en total)',
        lengthMenu: 'Mostrar _MENU_ por página',
        loadingRecords: 'Cargando…',
        processing: 'Procesando…',
        search: 'Buscar:',
        zeroRecords: 'No se encontraron coincidencias',
        paginate: {
            first: 'Primera',
            last: 'Última',
            next: 'Siguiente',
            previous: 'Anterior',
        },
    };

    function wrapPanel(table) {
        if (!table || !table.parentElement) return;
        const wrap = table.closest('.hay-dt-panel, .alumnos-table-wrap, .prereg-table-panel, .catalog-table-wrap');
        if (wrap && !wrap.classList.contains('hay-dt-panel')) {
            wrap.classList.add('hay-dt-panel');
        }
    }

    function fallbackPager(table, pageSize) {
        if (!table || table.dataset.hayFallbackPager === '1') return;
        table.dataset.hayFallbackPager = '1';

        const tbody = table.tBodies[0];
        if (!tbody) return;
        const allRows = [...tbody.querySelectorAll('tr')];
        if (allRows.length <= pageSize) return;

        const panel = table.closest('.hay-dt-panel, .alumnos-table-wrap, .prereg-table-panel, .catalog-table-wrap') || table.parentElement;
        let scrollWrap = table.parentElement;
        if (!scrollWrap.classList.contains('hay-dt-fallback-scroll')) {
            const box = document.createElement('div');
            box.className = 'hay-dt-fallback-scroll';
            table.parentNode.insertBefore(box, table);
            box.appendChild(table);
            scrollWrap = box;
        }

        let page = 0;
        const perPage = pageSize;
        const totalPages = Math.max(1, Math.ceil(allRows.length / perPage));

        const pager = document.createElement('div');
        pager.className = 'hay-dt-fallback-pager';
        pager.setAttribute('role', 'navigation');
        pager.setAttribute('aria-label', 'Paginación de tabla');

        const info = document.createElement('span');
        const btnPrev = document.createElement('button');
        btnPrev.type = 'button';
        btnPrev.textContent = 'Anterior';
        const btnNext = document.createElement('button');
        btnNext.type = 'button';
        btnNext.textContent = 'Siguiente';
        function draw() {
            const start = page * perPage;
            const end = start + perPage;
            allRows.forEach((row, i) => {
                row.style.display = i >= start && i < end ? '' : 'none';
            });
            const shownEnd = Math.min(end, allRows.length);
            info.textContent = `Mostrando ${allRows.length ? start + 1 : 0}–${shownEnd} de ${allRows.length} (página ${page + 1} de ${totalPages})`;
            btnPrev.disabled = page <= 0;
            btnNext.disabled = page >= totalPages - 1;
        }

        btnPrev.addEventListener('click', () => {
            if (page > 0) { page -= 1; draw(); }
        });
        btnNext.addEventListener('click', () => {
            if (page < totalPages - 1) { page += 1; draw(); }
        });

        pager.append(info, btnPrev, btnNext);
        scrollWrap.parentNode.insertBefore(pager, scrollWrap.nextSibling);
        draw();
    }

    function destroyIn(root) {
        const $ = global.jQuery;
        if (!$ || !$.fn.DataTable) return;
        const scope = root && root.querySelectorAll ? root : document;
        scope.querySelectorAll('table').forEach((table) => {
            if ($.fn.DataTable.isDataTable(table)) {
                try {
                    $(table).DataTable().destroy();
                } catch (e) { /* ignore */ }
            }
        });
    }

    function init(selector, options) {
        const $ = global.jQuery;
        const table = typeof selector === 'string'
            ? document.querySelector(selector)
            : selector;
        if (!table) return null;

        wrapPanel(table);

        if (!$ || !$.fn.DataTable) {
            console.warn('HayDataTable: jQuery/DataTables no cargados; paginación básica.');
            fallbackPager(table, (options && options.pageLength) || PAGE_SIZE_DEFAULT);
            return null;
        }

        const $t = $(table);
        if ($.fn.DataTable.isDataTable($t)) {
            $t.DataTable().destroy();
        }

        const defaults = {
            pageLength: PAGE_SIZE_DEFAULT,
            lengthMenu: [[25, 50, 100, 250], [25, 50, 100, 250]],
            deferRender: true,
            scrollY: SCROLL_Y,
            scrollCollapse: true,
            scrollX: true,
            paging: true,
            info: true,
            searching: true,
            language: LANG_ES,
            dom: 'lfrtip',
        };

        const opts = Object.assign({}, defaults, options || {});
        if (!opts.language) opts.language = LANG_ES;
        else opts.language = Object.assign({}, LANG_ES, opts.language);

        return $t.DataTable(opts);
    }

    global.HayDataTable = {
        PAGE_SIZE_DEFAULT,
        LANG_ES,
        init,
        destroyIn,
        fallbackPager,
    };
})(typeof window !== 'undefined' ? window : this);
