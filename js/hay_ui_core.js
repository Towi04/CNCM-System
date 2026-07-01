/**

 * Respaldo mínimo del panel si navigation.js no enlazó el menú.

 * No duplica dropdowns ni clics ya manejados por navigation.js.

 */

(function () {

    'use strict';



    function bindNavFallback() {

        if (typeof window.cargarSeccion !== 'function') return;

        document.querySelectorAll('.sidebar-menu .nav-item[data-seccion]').forEach((el) => {

            if (el.dataset.bound === '1' || el.classList.contains('has-flyout')) return;

            el.dataset.boundCore = '1';

            el.addEventListener('click', () => {

                const s = el.dataset.seccion;

                if (s) window.cargarSeccion(s);

            });

        });

    }



    function init() {

        setTimeout(bindNavFallback, 0);

    }



    if (document.readyState === 'loading') {

        document.addEventListener('DOMContentLoaded', init);

    } else {

        init();

    }

})();


