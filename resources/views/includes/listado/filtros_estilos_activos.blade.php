@once('listado-filtros-estilos-activos')
<style>
    .listado-filtros-input-activo {
        border: 2px solid #ffc107 !important;
        box-shadow: 0 0 0 0.15rem rgba(255, 193, 7, 0.45);
        background-color: #fffdf5;
    }
    .listado-filtros-icono-activo {
        color: #ffc107;
        font-size: 1.15rem;
        vertical-align: middle;
        line-height: 1;
    }
    .listado-filtros-btn-limpiar {
        border: 1px solid #e0a800;
    }
    .listado-filtros-btn-limpiar:hover,
    .listado-filtros-btn-limpiar:focus {
        color: #1a1a1a !important;
        background-color: #ffca2c;
        border-color: #d39e00;
    }
    .listado-filtros-toggle-activo {
        border: 2px solid #ffc107 !important;
        box-shadow: 0 0 0 0.1rem rgba(255, 193, 7, 0.35);
    }
    .listado-filtros-aviso-panel {
        background-color: #fff9e6;
        border: 1px solid #ffc107;
        border-radius: 0.25rem;
        padding: 0.35rem 0.65rem;
    }
    /* Misma altura entre select, input date/text y botones en filas de filtros */
    [data-listado-filtros-panel] .form-control-sm,
    [data-listado-filtros-externos] .form-control-sm {
        height: calc(1.8125rem + 2px);
        min-height: calc(1.8125rem + 2px);
        padding-top: 0.25rem;
        padding-bottom: 0.25rem;
        line-height: 1.5;
        box-sizing: border-box;
    }
    [data-listado-filtros-panel] select.form-control-sm,
    [data-listado-filtros-externos] select.form-control-sm {
        padding-right: 1.75rem;
    }
    [data-listado-filtros-panel] .btn-sm,
    [data-listado-filtros-externos] .btn-sm {
        height: calc(1.8125rem + 2px);
        display: inline-flex;
        align-items: center;
        justify-content: center;
        box-sizing: border-box;
        padding-top: 0;
        padding-bottom: 0;
    }
    .listado-filtros-label-spacer {
        visibility: hidden;
        user-select: none;
        pointer-events: none;
    }
</style>
@endonce
