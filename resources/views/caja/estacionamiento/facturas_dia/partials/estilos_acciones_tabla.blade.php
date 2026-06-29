<style>
    body .tooltip {
        pointer-events: none;
    }

    .facturas-dia-tabla-acciones .btn-accion-tabla,
    .facturas-dia-tabla-acciones a.btn-accion-tabla,
    .facturas-dia-tabla-acciones button.btn-accion-tabla {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        min-width: 1.85rem;
        min-height: 1.85rem;
        padding: 0.25rem 0.35rem !important;
        margin: 0 1px;
        vertical-align: middle;
        line-height: 1;
        position: relative;
        z-index: 1;
    }

    .facturas-dia-tabla-acciones .btn-accion-tabla i,
    .facturas-dia-tabla-acciones .btn-accion-tabla .fas,
    .facturas-dia-tabla-acciones .btn-accion-tabla .fa {
        pointer-events: none;
    }

    .est-col-monto,
    th.est-col-monto,
    td.est-col-monto {
        min-width: 6.85rem;
        max-width: 10rem;
        white-space: nowrap;
        text-align: right !important;
        font-variant-numeric: tabular-nums;
        font-feature-settings: "tnum";
    }

    .est-fd-filtros-form .form-group {
        flex: 0 0 auto;
    }

    .est-fd-filtros-form #fecha_fd {
        width: 10.5rem;
    }

    .est-fd-filtros-form .est-fd-campo-item {
        min-width: 9rem;
        max-width: 14rem;
    }

    .est-fd-filtros-form .est-fd-campo-turno {
        min-width: 11rem;
        max-width: 18rem;
    }

    .est-fd-filtros-form #busqueda_fd {
        min-width: 9rem;
        max-width: 12rem;
    }
</style>
