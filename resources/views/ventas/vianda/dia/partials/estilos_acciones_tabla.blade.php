<style>
    body .tooltip {
        pointer-events: none;
    }

    .vianda-dia-card-header {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        justify-content: space-between;
        gap: 0.5rem;
    }

    .vianda-dia-card-header .card-title {
        float: none;
        margin: 0;
        flex: 1 1 auto;
        min-width: 0;
    }

    .vianda-dia-header-acciones {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        justify-content: flex-end;
        gap: 0.35rem;
        margin-left: auto;
    }

    .card-info:not(.card-outline) > .vianda-dia-card-header .btn-vianda-header,
    .card-danger:not(.card-outline) > .vianda-dia-card-header .btn-vianda-header {
        color: #fff;
        border: 1px solid rgba(255, 255, 255, 0.75);
        background: rgba(255, 255, 255, 0.08);
        box-shadow: none;
    }

    .card-info:not(.card-outline) > .vianda-dia-card-header .btn-vianda-header:hover,
    .card-info:not(.card-outline) > .vianda-dia-card-header .btn-vianda-header:focus,
    .card-danger:not(.card-outline) > .vianda-dia-card-header .btn-vianda-header:hover,
    .card-danger:not(.card-outline) > .vianda-dia-card-header .btn-vianda-header:focus {
        color: #1e5a8a;
        background: #fff;
        border-color: #fff;
    }

    .card-info:not(.card-outline) > .vianda-dia-card-header .btn-vianda-header.btn-vianda-header-solid,
    .card-danger:not(.card-outline) > .vianda-dia-card-header .btn-vianda-header.btn-vianda-header-solid {
        color: #1e5a8a;
        background: #fff;
        border-color: #fff;
    }

    .card-info:not(.card-outline) > .vianda-dia-card-header .btn-vianda-header.btn-vianda-header-solid:hover,
    .card-info:not(.card-outline) > .vianda-dia-card-header .btn-vianda-header.btn-vianda-header-solid:focus,
    .card-danger:not(.card-outline) > .vianda-dia-card-header .btn-vianda-header.btn-vianda-header-solid:hover,
    .card-danger:not(.card-outline) > .vianda-dia-card-header .btn-vianda-header.btn-vianda-header-solid:focus {
        color: #154a72;
        background: #f0f4f8;
        border-color: #f0f4f8;
    }

    .viandas-dia-tabla-acciones .btn-accion-tabla,
    .viandas-dia-tabla-acciones a.btn-accion-tabla,
    .viandas-dia-tabla-acciones button.btn-accion-tabla,
    .vianda-ver-tabla-acciones .btn-accion-tabla,
    .vianda-ver-tabla-acciones a.btn-accion-tabla,
    .vianda-ver-tabla-acciones button.btn-accion-tabla {
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

    .viandas-dia-tabla-acciones .btn-accion-tabla i,
    .viandas-dia-tabla-acciones .btn-accion-tabla .fas,
    .viandas-dia-tabla-acciones .btn-accion-tabla .fa,
    .vianda-ver-tabla-acciones .btn-accion-tabla i,
    .vianda-ver-tabla-acciones .btn-accion-tabla .fas,
    .vianda-ver-tabla-acciones .btn-accion-tabla .fa {
        pointer-events: none;
    }

    #tabla-viandas-dia thead tr {
        background-color: #85C1E9;
        color: #17202A;
    }

    #tabla-viandas-dia thead th {
        font-weight: 600;
        border-color: #7fb3d5;
    }

    #tabla-lineas thead tr,
    #tabla-movimientos-vianda thead tr {
        background-color: #85C1E9;
        color: #17202A;
    }

    #tabla-lineas thead th,
    #tabla-movimientos-vianda thead th {
        font-weight: 600;
        border-color: #7fb3d5;
    }

    .vianda-dia-filtros-form .form-group {
        flex: 0 0 auto;
    }

    .vianda-dia-filtros-form label {
        color: #495057;
        font-weight: 600;
    }
</style>
