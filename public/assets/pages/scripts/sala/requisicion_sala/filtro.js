(function () {
    'use strict';
    if (typeof window.inicializarFiltrosListado === 'function') {
        window.inicializarFiltrosListado({
            formId: 'form-filtros-requisicion-sala',
            toggleId: 'btn-toggle-filtros-requisicion-sala',
            panelId: 'panel-filtros-requisicion-sala',
            operadoresPorCampo: window.requisicionSalaOperadoresPorCampo || {},
        });
    }
})();
