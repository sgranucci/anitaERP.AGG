@extends("theme.$theme.layout")
@section('titulo')
Bandeja de legajos
@endsection

@section('styles')
<link rel="stylesheet" href="{{ asset('assets/css/listado-workbench.css') }}?v={{ filemtime(public_path('assets/css/listado-workbench.css')) }}">
<link rel="stylesheet" href="{{ asset('assets/css/tabla-ancha-reporte.css') }}?v={{ @filemtime(public_path('assets/css/tabla-ancha-reporte.css')) ?: time() }}">
<link rel="stylesheet" href="{{ asset('assets/pages/css/compras/ordencompra/asignar_factura_legajo.css') }}?v={{ @filemtime(public_path('assets/pages/css/compras/ordencompra/asignar_factura_legajo.css')) ?: time() }}">
@include('includes.tabs-activas-estilos')
<style>
    .bandeja-col-facturas {
        max-width: 10.5rem;
        min-width: 7rem;
        white-space: normal;
        word-wrap: break-word;
        overflow-wrap: anywhere;
        line-height: 1.3;
        vertical-align: top;
    }
    .bandeja-fac-item + .bandeja-fac-item {
        margin-top: 0.35rem;
        padding-top: 0.35rem;
        border-top: 1px dashed #dee2e6;
    }
    .bandeja-fac-origen {
        display: block;
        font-size: 0.78em;
        color: #6c757d;
        line-height: 1.2;
    }
    .bandeja-fac-consultar {
        display: inline;
        padding: 0;
        font-size: 0.82em;
        line-height: 1.2;
        vertical-align: baseline;
    }
    .oc-empresas-export {
        white-space: nowrap;
    }
    #modalBandejaLegajo thead th {
        position: sticky;
        top: 0;
        background: #85C1E9;
        color: #17202A;
        z-index: 1;
    }
    #modalBandejaLegajo td {
        font-size: 0.82rem;
        padding: 0.28rem 0.4rem;
        vertical-align: middle;
    }

    /* ——— Modal Asignar COM ——— */
    #modalBandejaAsignarCom .modal-body {
        padding-top: 0.85rem;
    }
    #modalBandejaAsignarCom .bandeja-asig-hint {
        margin: 0 0 0.85rem;
        padding: 0.55rem 0.75rem;
        background: #f7f9fb;
        border: 1px solid #e8eef3;
        border-radius: 6px;
        font-size: 0.8rem;
        color: #5a6a7a;
        line-height: 1.4;
    }
    #modalBandejaAsignarCom .bandeja-asig-col-label {
        display: block;
        margin-bottom: 0.4rem;
        font-size: 0.72rem;
        font-weight: 700;
        letter-spacing: 0.04em;
        text-transform: uppercase;
        color: #6c7a89;
    }
    #modalBandejaAsignarCom #bandejaAsignarPrecarga {
        border: 1px solid #e2e8f0;
        border-radius: 8px;
        overflow: hidden;
        max-height: 52vh;
        background: #fff;
    }
    #modalBandejaAsignarCom #bandejaAsignarPrecarga .list-group-item {
        border: 0;
        border-bottom: 1px solid #eef2f6;
        padding: 0.65rem 0.75rem;
        color: inherit;
    }
    #modalBandejaAsignarCom #bandejaAsignarPrecarga .list-group-item:last-child {
        border-bottom: 0;
    }
    #modalBandejaAsignarCom #bandejaAsignarPrecarga .list-group-item.active,
    #modalBandejaAsignarCom #bandejaAsignarPrecarga .js-bandeja-asig-doc.active {
        background: #eef6ff;
        color: #1a2332;
        box-shadow: inset 3px 0 0 #2f80ed;
    }
    #modalBandejaAsignarCom #bandejaAsignarPrecarga .js-bandeja-asig-doc.active small {
        color: #6c7a89 !important;
    }
    #modalBandejaAsignarCom .bandeja-asig-sec {
        padding: 0.4rem 0.75rem;
        background: #f4f7fa;
        border-bottom: 1px solid #e8eef3;
        font-size: 0.72rem;
        font-weight: 700;
        letter-spacing: 0.03em;
        text-transform: uppercase;
        color: #6c7a89;
    }
    #modalBandejaAsignarCom .bandeja-asig-doc-main {
        font-size: 0.88rem;
        font-weight: 600;
        line-height: 1.3;
        color: #1a2332;
    }
    #modalBandejaAsignarCom .bandeja-asig-doc-meta {
        margin-top: 0.15rem;
        font-size: 0.75rem;
        color: #6c7a89;
        line-height: 1.3;
    }
    #modalBandejaAsignarCom .bandeja-asig-badge-sin {
        background: #fff4e5;
        color: #9a5b00;
        border: 1px solid #f0d4a8;
        font-weight: 600;
    }
    #modalBandejaAsignarCom .bandeja-asig-badge-ok {
        background: #e8f1ff;
        color: #1d5bbf;
        border: 1px solid #c5d8f8;
        font-weight: 600;
    }
    #modalBandejaAsignarCom .bandeja-asig-badge-sug {
        background: #e8f7f5;
        color: #0d6e63;
        border: 1px solid #bfe6e0;
        font-weight: 600;
    }
    #modalBandejaAsignarCom #bandejaAsignarComs {
        border: 1px solid #e2e8f0;
        border-radius: 8px;
        padding: 0;
        min-height: 8rem;
        max-height: 52vh;
        overflow: auto;
        background: #fff;
    }
    #modalBandejaAsignarCom .bandeja-asig-panel-head {
        padding: 0.65rem 0.75rem;
        border-bottom: 1px solid #eef2f6;
        background: #fafbfc;
    }
    #modalBandejaAsignarCom .bandeja-asig-panel-head .form-group {
        margin-bottom: 0.45rem;
    }
    #modalBandejaAsignarCom .bandeja-asig-panel-head .form-text {
        margin-top: 0.2rem;
        font-size: 0.72rem;
    }
    #modalBandejaAsignarCom .bandeja-asig-importes {
        display: flex;
        flex-wrap: wrap;
        gap: 0.35rem 1rem;
        margin: 0;
        font-size: 0.8rem;
        color: #5a6a7a;
    }
    #modalBandejaAsignarCom .bandeja-asig-importes strong {
        color: #1a2332;
        font-weight: 600;
    }
    #modalBandejaAsignarCom .bandeja-asig-com-list {
        padding: 0.55rem 0.65rem 0.75rem;
    }
    #modalBandejaAsignarCom .bandeja-asig-grupo {
        margin: 0 0 0.65rem;
        font-size: 0.7rem;
        font-weight: 700;
        letter-spacing: 0.04em;
        text-transform: uppercase;
        color: #6c7a89;
    }
    #modalBandejaAsignarCom .bandeja-asig-match {
        margin: 0 0 0.55rem;
        padding: 0.45rem 0.6rem;
        border-radius: 6px;
        background: #e7f6f3;
        border: 1px solid #b6e0d8;
        color: #0d5c54;
        font-size: 0.82rem;
        line-height: 1.35;
    }
    #modalBandejaAsignarCom .bandeja-asig-match strong {
        font-weight: 700;
    }
    #modalBandejaAsignarCom .bandeja-asig-com-row {
        display: flex;
        align-items: flex-start;
        gap: 0.7rem;
        padding: 0.75rem 0.85rem 0.75rem 0.85rem;
        margin: 0 0 0.5rem;
        border: 1px solid #d9e2ec;
        border-radius: 8px;
        background: #fff;
        box-sizing: border-box;
    }
    #modalBandejaAsignarCom .bandeja-asig-com-row.is-checked {
        border-color: #2f80ed;
        background: #eef5ff;
        box-shadow: 0 0 0 1px rgba(47, 128, 237, 0.15);
    }
    #modalBandejaAsignarCom .bandeja-asig-com-row.is-sugerida {
        border-color: #1a9b8a;
        border-width: 2px;
        background: #e8faf6;
        box-shadow: 0 2px 8px rgba(26, 155, 138, 0.12);
    }
    #modalBandejaAsignarCom .bandeja-asig-com-row.is-sugerida.is-checked {
        border-color: #1a9b8a;
        background: #dff7f1;
        box-shadow: 0 2px 10px rgba(26, 155, 138, 0.18);
    }
    #modalBandejaAsignarCom .bandeja-asig-com-row.is-bloqueada {
        display: flex;
        align-items: center;
        min-height: 0;
        padding: 0.4rem 0.65rem;
        margin: 0 0 0.25rem;
        border-style: solid;
        border-width: 1px;
        border-color: #e5e9ef;
        background: #f5f7f9;
        box-shadow: none;
        opacity: 1;
    }
    #modalBandejaAsignarCom .bandeja-asig-com-row.is-bloqueada .bandeja-asig-com-title {
        font-size: 0.8rem;
        font-weight: 500;
        color: #5a6a7a;
    }
    #modalBandejaAsignarCom .bandeja-asig-com-row.is-bloqueada .bandeja-asig-com-meta,
    #modalBandejaAsignarCom .bandeja-asig-com-row.is-bloqueada .bandeja-asig-com-nota {
        display: none;
    }
    #modalBandejaAsignarCom .bandeja-asig-com-row.is-bloqueada .bandeja-asig-com-title .badge {
        display: none;
    }
    #modalBandejaAsignarCom .bandeja-asig-com-row.is-bloqueada .bandeja-asig-tomada {
        display: inline;
        margin-left: 0.35rem;
        font-size: 0.72rem;
        color: #7a8794;
        font-weight: 400;
    }
    #modalBandejaAsignarCom .bandeja-asig-com-row .form-check-input {
        position: static;
        float: none;
        margin: 0.2rem 0 0;
        flex: 0 0 1.15rem;
        width: 1.15rem;
        height: 1.15rem;
        transform: none;
        align-self: flex-start;
    }
    #modalBandejaAsignarCom .bandeja-asig-com-body {
        flex: 1 1 auto;
        min-width: 0;
        padding-left: 0;
        margin: 0;
        cursor: pointer;
    }
    #modalBandejaAsignarCom .bandeja-asig-com-title {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        gap: 0.35rem 0.5rem;
        margin: 0;
        font-size: 1rem;
        font-weight: 700;
        line-height: 1.35;
        color: #122033;
    }
    #modalBandejaAsignarCom .bandeja-asig-com-title .badge {
        font-weight: 700;
        font-size: 0.7rem;
        letter-spacing: 0.03em;
        text-transform: uppercase;
        padding: 0.28em 0.55em;
        white-space: nowrap;
    }
    #modalBandejaAsignarCom .bandeja-asig-com-meta {
        display: grid;
        grid-template-columns: repeat(3, minmax(0, 1fr));
        gap: 0.35rem 0.5rem;
        margin-top: 0.45rem;
        padding-top: 0.45rem;
        border-top: 1px solid rgba(0,0,0,0.06);
        font-size: 0.78rem;
        color: #4a5b6c;
        line-height: 1.25;
    }
    @media (max-width: 767px) {
        #modalBandejaAsignarCom .bandeja-asig-com-meta {
            grid-template-columns: 1fr;
        }
    }
    #modalBandejaAsignarCom .bandeja-asig-com-meta span {
        display: block;
    }
    #modalBandejaAsignarCom .bandeja-asig-com-meta b {
        display: block;
        font-size: 0.65rem;
        font-weight: 700;
        letter-spacing: 0.04em;
        text-transform: uppercase;
        color: #8a97a5;
        margin-bottom: 0.1rem;
    }
    #modalBandejaAsignarCom .bandeja-asig-com-nota {
        display: none;
    }

    /* Herramienta de pagos del legajo */
    .bandeja-pagos-tool {
        --bp-ink: #1a2332;
        --bp-muted: #5b6b7c;
        --bp-line: #e2e8f0;
        --bp-soft: #f4f7fa;
        --bp-accent: #0d7a6f;
        --bp-accent-soft: #e6f5f3;
        --bp-warn: #b45309;
        --bp-warn-soft: #fff7ed;
        --bp-ok: #047857;
        --bp-ok-soft: #ecfdf5;
        font-family: "Segoe UI", system-ui, sans-serif;
        color: var(--bp-ink);
    }
    .bandeja-pagos-kpis {
        display: grid;
        grid-template-columns: repeat(3, minmax(0, 1fr));
        gap: 0.75rem;
        margin-bottom: 1rem;
    }
    @media (max-width: 767px) {
        .bandeja-pagos-kpis { grid-template-columns: 1fr; }
    }
    .bandeja-pagos-kpi {
        background: linear-gradient(145deg, #ffffff 0%, var(--bp-soft) 100%);
        border: 1px solid var(--bp-line);
        border-radius: 12px;
        padding: 0.85rem 1rem;
        min-height: 4.5rem;
    }
    .bandeja-pagos-kpi .kpi-label {
        display: block;
        font-size: 0.72rem;
        letter-spacing: 0.04em;
        text-transform: uppercase;
        color: var(--bp-muted);
        margin-bottom: 0.25rem;
    }
    .bandeja-pagos-kpi .kpi-valor {
        font-size: 1.35rem;
        font-weight: 650;
        line-height: 1.15;
        letter-spacing: -0.02em;
    }
    .bandeja-pagos-kpi .kpi-hint {
        font-size: 0.75rem;
        color: var(--bp-muted);
        margin-top: 0.15rem;
    }
    .bandeja-pagos-layout {
        display: grid;
        grid-template-columns: minmax(240px, 34%) 1fr;
        gap: 0.85rem;
        min-height: 52vh;
    }
    @media (max-width: 991px) {
        .bandeja-pagos-layout { grid-template-columns: 1fr; min-height: 0; }
    }
    .bandeja-pagos-lista,
    .bandeja-pagos-panel {
        border: 1px solid var(--bp-line);
        border-radius: 14px;
        background: #fff;
        overflow: hidden;
        display: flex;
        flex-direction: column;
    }
    .bandeja-pagos-lista-head,
    .bandeja-pagos-panel-head {
        padding: 0.7rem 0.9rem;
        border-bottom: 1px solid var(--bp-line);
        background: var(--bp-soft);
        font-size: 0.8rem;
        font-weight: 600;
        color: var(--bp-muted);
        letter-spacing: 0.02em;
    }
    .bandeja-pagos-lista-body {
        overflow: auto;
        max-height: 56vh;
        flex: 1;
    }
    .bandeja-pagos-fac {
        width: 100%;
        text-align: left;
        border: 0;
        border-bottom: 1px solid var(--bp-line);
        background: #fff;
        padding: 0.75rem 0.9rem;
        cursor: pointer;
        transition: background 0.15s ease, box-shadow 0.15s ease;
    }
    .bandeja-pagos-fac:hover { background: #f8fafc; }
    .bandeja-pagos-fac.is-active {
        background: var(--bp-accent-soft);
        box-shadow: inset 3px 0 0 var(--bp-accent);
    }
    .bandeja-pagos-fac-top {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        gap: 0.5rem;
    }
    .bandeja-pagos-fac-titulo {
        font-weight: 600;
        font-size: 0.86rem;
        line-height: 1.25;
    }
    .bandeja-pagos-fac-meta {
        margin-top: 0.35rem;
        font-size: 0.75rem;
        color: var(--bp-muted);
    }
    .bandeja-pagos-chip {
        display: inline-flex;
        align-items: center;
        gap: 0.25rem;
        border-radius: 999px;
        padding: 0.15rem 0.55rem;
        font-size: 0.68rem;
        font-weight: 650;
        letter-spacing: 0.02em;
        white-space: nowrap;
    }
    .bandeja-pagos-chip-ok { background: var(--bp-ok-soft); color: var(--bp-ok); }
    .bandeja-pagos-chip-warn { background: var(--bp-warn-soft); color: var(--bp-warn); }
    .bandeja-pagos-chip-mute { background: #eef2f6; color: var(--bp-muted); }
    .bandeja-pagos-panel-body {
        padding: 0.9rem;
        overflow: auto;
        max-height: 56vh;
        flex: 1;
    }
    .bandeja-pagos-empty {
        text-align: center;
        padding: 2.5rem 1.25rem;
        color: var(--bp-muted);
    }
    .bandeja-pagos-empty .empty-ico {
        width: 3.25rem;
        height: 3.25rem;
        margin: 0 auto 0.85rem;
        border-radius: 50%;
        background: var(--bp-soft);
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.35rem;
        color: var(--bp-accent);
    }
    .bandeja-pagos-op {
        border: 1px solid var(--bp-line);
        border-radius: 12px;
        padding: 0.85rem 0.95rem;
        margin-bottom: 0.65rem;
        background: linear-gradient(180deg, #fff 0%, #fafbfc 100%);
        transition: border-color 0.15s ease, transform 0.15s ease;
    }
    .bandeja-pagos-op:hover {
        border-color: #b8c9d6;
        transform: translateY(-1px);
    }
    .bandeja-pagos-op-top {
        display: flex;
        justify-content: space-between;
        align-items: center;
        gap: 0.75rem;
        flex-wrap: wrap;
    }
    .bandeja-pagos-op-etiqueta {
        font-weight: 700;
        font-size: 0.95rem;
        color: var(--bp-accent);
        text-decoration: none;
    }
    .bandeja-pagos-op-etiqueta:hover { text-decoration: underline; color: #0a5f56; }
    .bandeja-pagos-op-meta {
        margin-top: 0.35rem;
        font-size: 0.78rem;
        color: var(--bp-muted);
        display: flex;
        flex-wrap: wrap;
        gap: 0.35rem 0.85rem;
    }
    .bandeja-pagos-op-actions {
        display: flex;
        gap: 0.35rem;
        flex-shrink: 0;
    }
    .bandeja-pagos-op-actions .btn {
        border-radius: 8px;
        font-size: 0.75rem;
    }
    .bandeja-pagos-rets {
        margin-top: 0.55rem;
        padding-top: 0.45rem;
        border-top: 1px dashed var(--bp-line);
    }
    .bandeja-pagos-rets-titulo {
        font-size: 0.68rem;
        font-weight: 700;
        letter-spacing: 0.04em;
        text-transform: uppercase;
        color: var(--bp-muted);
        margin-bottom: 0.25rem;
    }
    .bandeja-pagos-ret {
        display: flex;
        justify-content: space-between;
        gap: 0.75rem;
        font-size: 0.8rem;
        padding: 0.12rem 0;
    }
    .bandeja-pagos-estado {
        display: inline-block;
        border-radius: 6px;
        padding: 0.12rem 0.45rem;
        font-size: 0.68rem;
        font-weight: 700;
        letter-spacing: 0.03em;
        text-transform: uppercase;
    }
    .bandeja-pagos-estado-confirmada,
    .bandeja-pagos-estado-pagada,
    .bandeja-pagos-estado-conciliada { background: var(--bp-ok-soft); color: var(--bp-ok); }
    .bandeja-pagos-estado-pre-carga { background: #eff6ff; color: #1d4ed8; }
    .bandeja-pagos-estado-revertida,
    .bandeja-pagos-estado-baja { background: #fef2f2; color: #b91c1c; }
    .bandeja-fac-pago-badge {
        display: inline-block;
        margin-left: 0.25rem;
        font-size: 0.68rem;
        vertical-align: middle;
    }
    .bandeja-fac-ver-pagos {
        cursor: pointer;
        border: 0;
        background: transparent;
        color: var(--bp-accent, #0d7a6f);
        padding: 0;
        font-size: 0.72rem;
        text-decoration: underline;
    }
    /* Select de vista: tamaño chico (no el ancho pill por defecto del workbench) */
    .lw-workbench .lw-vista-select {
        width: 9.5rem;
        min-width: 9.5rem;
        max-width: 11rem;
        border-radius: 0.25rem;
        padding-left: 0.4rem;
        padding-right: 1.4rem;
        font-size: 0.8125rem;
    }
    /* Un bloque: búsqueda rápida + chips originales + QBE colapsado */
    .lw-workbench .lw-filtros-bloque {
        background: #fff;
        border-bottom: 1px solid #d6e0ea;
    }
    .lw-workbench .lw-filtros-bloque > .lw-filtros-busqueda {
        padding-left: 0.75rem;
        padding-right: 0.75rem;
    }
    .lw-workbench .lw-busqueda-fila {
        gap: 0.45rem 0.65rem;
        width: 100%;
    }
    .lw-workbench .lw-busqueda-campo {
        flex: 1 1 0;
        min-width: 7rem;
        margin-bottom: 0;
    }
    .lw-workbench .lw-busqueda-campo.lw-busqueda-texto {
        flex: 2.2 1 0;
        min-width: 12rem;
    }
    .lw-workbench .lw-busqueda-campo label {
        display: block;
        white-space: nowrap;
    }
    .lw-workbench .lw-busqueda-acciones {
        display: inline-flex;
        flex: 0 0 auto;
        align-items: center;
        gap: 0.35rem;
        padding-bottom: 1px;
        margin-bottom: 0;
        margin-left: auto;
    }
    .lw-workbench .lw-filtros-bloque > .oc-empresas {
        border-bottom: 1px solid #eef2f6 !important;
        background: #fff !important;
        padding: 0.45rem 0.75rem !important;
    }
    .lw-workbench .lw-filtros-bloque > .lw-filtros-prefijados {
        padding: 0.55rem 0.75rem 0.65rem;
        border-bottom: 1px solid #eef2f6;
        background: #fff;
    }
    .lw-workbench .lw-filtros-bloque > .lw-qbe {
        border-bottom: 0;
        background: #f4f8fb;
        padding: 0.65rem 0.75rem 0.75rem;
    }
    .lw-workbench .lw-filtros-bloque > .lw-qbe.collapse:not(.show) {
        display: none !important;
    }
    .lw-workbench #lw-toolbar-panel.collapse:not(.show) {
        display: none !important;
    }
    /*
     * Contener el overflow: sin esto, la tabla (width:max-content) ensancha el card
     * y Acciones sticky queda fuera del viewport (parecía “por sacar Decisión”).
     */
    .bandeja-legajos-row,
    .bandeja-legajos-row > [class*="col-"],
    .lw-workbench.card {
        max-width: 100%;
        min-width: 0;
    }
    .lw-workbench .bandeja-grilla-ancha {
        --tabla-ancha-der: 10.75rem;
        max-width: 100%;
        min-width: 0;
        width: 100%;
    }
    .lw-workbench .bandeja-grilla-ancha .tabla-ancha-wrap {
        max-width: 100%;
        max-height: calc(100vh - 280px);
        overflow-x: auto;
    }
    .lw-workbench .bandeja-grilla-ancha .bandeja-col-facturas {
        white-space: normal;
    }
    .lw-workbench .bandeja-grilla-ancha th.lw-col-acciones,
    .lw-workbench .bandeja-grilla-ancha td.lw-col-acciones {
        white-space: normal;
        vertical-align: middle;
        text-align: center;
        padding-left: 0.3rem;
        padding-right: 0.3rem;
    }
    /* Dentro de la columna fija: wrap local (como cuando entraba con Decisión) */
    .lw-workbench .bandeja-acciones {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        justify-content: center;
        gap: 0.12rem;
        max-width: 100%;
    }
    .lw-workbench .bandeja-acciones .btn {
        margin: 0;
        padding: 0.18rem 0.34rem;
        font-size: 0.82rem;
        line-height: 1.15;
        min-width: 1.6rem;
    }
    .lw-workbench .bandeja-acciones .btn > .fa {
        font-size: 0.95em;
    }
</style>
@endsection

@section('scripts')
<script src="{{ asset('assets/pages/scripts/includes/listado-filtros.js') }}" type="text/javascript"></script>
<script src="{{ asset('assets/pages/scripts/compras/ordencompra/filtro.js') }}?v={{ @filemtime(public_path('assets/pages/scripts/compras/ordencompra/filtro.js')) ?: time() }}" type="text/javascript"></script>
<script src="{{ asset('assets/pages/scripts/compras/devolver_legajo.js') }}?v={{ @filemtime(public_path('assets/pages/scripts/compras/devolver_legajo.js')) ?: time() }}" type="text/javascript"></script>
<script src="{{ asset('assets/pages/scripts/compras/ordencompra/cambiar_sector_legajo.js') }}?v={{ @filemtime(public_path('assets/pages/scripts/compras/ordencompra/cambiar_sector_legajo.js')) ?: time() }}" type="text/javascript"></script>
<script src="{{ asset('assets/pages/scripts/compras/ordencompra/enviar_gastronomia_firmante.js') }}?v={{ @filemtime(public_path('assets/pages/scripts/compras/ordencompra/enviar_gastronomia_firmante.js')) ?: time() }}" type="text/javascript"></script>
<script src="{{ asset('assets/pages/scripts/compras/ordencompra/asignar_factura_legajo.js') }}?v={{ @filemtime(public_path('assets/pages/scripts/compras/ordencompra/asignar_factura_legajo.js')) ?: time() }}" type="text/javascript"></script>
<script>
    window.bandejaPuedeCorregirTipo = {{ (!empty($puede_cargar_cxp) || !empty($puede_asignar_com)) ? 'true' : 'false' }};
</script>

@php
    $qbeGruposJs = public_path('assets/pages/scripts/listado/workbench-qbe-grupos.js');
    $ordenJs = public_path('assets/pages/scripts/listado/workbench-orden.js');
    $agruparJs = public_path('assets/pages/scripts/listado/workbench-agrupar.js');
    $disenadorJs = public_path('assets/pages/scripts/listado/workbench-disenador-preview.js');
    $vistaGuardarJs = public_path('assets/pages/scripts/listado/workbench-vista-guardar.js');
    $workbenchJs = public_path('assets/pages/scripts/compras/legajo_bandeja/workbench.js');
    $columnasDndJs = public_path('assets/pages/scripts/listado/workbench-columnas-dnd.js');
    $tablaAnchaJs = public_path('assets/pages/scripts/admin/tabla-ancha-reporte.js');
@endphp
<script src="{{ asset('assets/pages/scripts/listado/workbench-qbe-grupos.js') }}?v={{ file_exists($qbeGruposJs) ? filemtime($qbeGruposJs) : time() }}"></script>
<script src="{{ asset('assets/pages/scripts/listado/workbench-orden.js') }}?v={{ file_exists($ordenJs) ? filemtime($ordenJs) : time() }}"></script>
<script src="{{ asset('assets/pages/scripts/listado/workbench-agrupar.js') }}?v={{ file_exists($agruparJs) ? filemtime($agruparJs) : time() }}"></script>
<script src="{{ asset('assets/pages/scripts/listado/workbench-disenador-preview.js') }}?v={{ file_exists($disenadorJs) ? filemtime($disenadorJs) : time() }}"></script>
<script src="{{ asset('assets/pages/scripts/listado/workbench-vista-guardar.js') }}?v={{ file_exists($vistaGuardarJs) ? filemtime($vistaGuardarJs) : time() }}"></script>
<script src="{{ asset('assets/pages/scripts/compras/legajo_bandeja/workbench.js') }}?v={{ file_exists($workbenchJs) ? filemtime($workbenchJs) : time() }}"></script>
<script src="{{ asset('assets/pages/scripts/listado/workbench-columnas-dnd.js') }}?v={{ file_exists($columnasDndJs) ? filemtime($columnasDndJs) : time() }}"></script>
<script src="{{ asset('assets/pages/scripts/admin/tabla-ancha-reporte.js') }}?v={{ file_exists($tablaAnchaJs) ? filemtime($tablaAnchaJs) : time() }}"></script>
<script src="{{ asset('assets/pages/scripts/compras/legajo_bandeja/bandeja.js') }}?v={{ @filemtime(public_path('assets/pages/scripts/compras/legajo_bandeja/bandeja.js')) ?: time() }}" type="text/javascript"></script>
@endsection

@section('contenido')
@include('includes.contable.modalconsultacuentacontable')
@include('compras.ordencompra.partials.modal_asignar_factura_legajo')
@include('compras.ordencompra.partials.modal_firmante_gastronomia_arbol')
@php
    use App\Support\Compras\OrdencompraLegajoBandejaFiltros;
    use App\Support\Compras\OrdencompraListadoFiltros;
    use App\Support\Compras\OrdencompraSectorVisibilidadSupport;
    $vista = $filtros['vista'] ?? OrdencompraLegajoBandejaFiltros::VISTA_PENDIENTES;
    $tab = $filtros['tab'] ?? OrdencompraLegajoBandejaFiltros::TAB_TODOS;
    $atajo = $filtros['atajo'] ?? '';
    $esSectorCxp = OrdencompraSectorVisibilidadSupport::esUsuarioSectorCuentasAPagar();
    $limpiarUrl = route('consultar_legajo_compra', OrdencompraLegajoBandejaFiltros::paraQueryStringEmpresaYVista($filtros));
    $camposOrdenablesThead = OrdencompraLegajoBandejaFiltros::camposOrdenables();
    $ordenActualThead = \App\Support\Listado\ListadoOrdenamientoSupport::normalizar(
        $filtros['sort'] ?? [],
        $camposOrdenablesThead
    );
    $qsQuitarOrden = $filtrosQuery ?? [];
    unset($qsQuitarOrden['sort']);
    $qsQuitarOrden['quitar_orden'] = 1;
    $urlQuitarOrden = route('consultar_legajo_compra', $qsQuitarOrden);
    $qbeActivo = \App\Support\Listado\ListadoQbeSupport::tieneCriterios($filtros['qbe'] ?? []);
    // Siempre colapsado al cargar: no cambia la bandeja cotidiana; se abre con «Consulta avanzada».
    $qbeAbierto = false;
    $qs = function (array $extra = []) use ($filtros) {
        return route('consultar_legajo_compra', OrdencompraLegajoBandejaFiltros::paraQueryString(array_merge($filtros, $extra)));
    };
    $qsAtajo = function (string $nuevo) use ($filtros, $atajo) {
        $extra = [
            'atajo' => $atajo === $nuevo ? '' : $nuevo,
        ];
        if ($atajo !== $nuevo && $nuevo === OrdencompraLegajoBandejaFiltros::ATAJO_LISTO_CARGAR) {
            $extra['vista'] = OrdencompraLegajoBandejaFiltros::VISTA_CXP;
        }
        if ($atajo !== $nuevo && $nuevo === OrdencompraLegajoBandejaFiltros::ATAJO_PENDIENTE_ENTREGA) {
            $extra['vista'] = OrdencompraLegajoBandejaFiltros::VISTA_ESTADOS;
        }

        return route('consultar_legajo_compra', OrdencompraLegajoBandejaFiltros::paraQueryString(array_merge($filtros, $extra)));
    };
    $vistasTodas = [
        OrdencompraLegajoBandejaFiltros::VISTA_PENDIENTES => 'Pendientes',
        OrdencompraLegajoBandejaFiltros::VISTA_ESTADOS => 'En circuito',
        OrdencompraLegajoBandejaFiltros::VISTA_CXP => 'Cuentas a pagar',
        OrdencompraLegajoBandejaFiltros::VISTA_PAGOS => 'Pagos',
        OrdencompraLegajoBandejaFiltros::VISTA_ARCHIVADOS => 'Archivados',
        OrdencompraLegajoBandejaFiltros::VISTA_HISTORICO => 'Histórico',
    ];
    $vistasPermitidas = $vistasPermitidas ?? null;
    $vistas = $vistasTodas;
    if (is_array($vistasPermitidas) && $vistasPermitidas !== []) {
        $vistas = array_intersect_key($vistasTodas, array_flip($vistasPermitidas));
        if ($vistas === []) {
            $vistas = $vistasTodas;
        }
    }
    $atajos = [
        OrdencompraLegajoBandejaFiltros::ATAJO_SIN_FACTURA => 'Sin factura',
        OrdencompraLegajoBandejaFiltros::ATAJO_SIN_COM => 'Sin COM',
        OrdencompraLegajoBandejaFiltros::ATAJO_COM_SIN_ASIGNAR => 'COM sin asignar',
        OrdencompraLegajoBandejaFiltros::ATAJO_PENDIENTE_ENTREGA => 'Pendiente entrega',
        OrdencompraLegajoBandejaFiltros::ATAJO_LISTO_CARGAR => 'Listo para cargar',
        OrdencompraLegajoBandejaFiltros::ATAJO_FC_CARGADA => 'FC cargada',
        OrdencompraLegajoBandejaFiltros::ATAJO_CON_PAGO => 'Con orden de pago',
    ];
@endphp

@if (!empty($puede_actualizar))
<div class="modal fade" id="modalBandejaEnviarGastro" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <form method="POST" id="formBandejaEnviarGastro" action="" enctype="multipart/form-data"
                  data-ordencompra-id=""
                  data-sector-gastronomia-id="{{ (int) \App\Support\Compras\OrdencompraLegajoGastronomiaSupport::sectorGastronomiaId() }}">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title">Enviar a Gastronomía</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                </div>
                <div class="modal-body">
                    <p class="text-muted small">El referente verá la factura, la OC y la recepción. Al autorizar, el legajo pasa a Cuentas a pagar.</p>
                    @include('compras.ordencompra.partials.bloque_factura_legajo_sector', [
                        'prefix' => 'bandeja_ocg',
                        'tituloBloque' => 'Factura y recepción del legajo',
                    ])
                    <div class="form-group">
                        <label for="bandeja_ocg_obs">Comentario al referente</label>
                        <input type="text" name="observacion" id="bandeja_ocg_obs" class="form-control" maxlength="255" placeholder="Opcional">
                    </div>
                    <div class="form-group">
                        <label for="bandeja_ocg_leyenda">Leyenda / detalle</label>
                        <textarea name="leyenda" id="bandeja_ocg_leyenda" class="form-control" rows="2" maxlength="2000"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary">Enviar a Gastronomía</button>
                </div>
            </form>
        </div>
    </div>
</div>
<div class="modal fade" id="modalBandejaEnviarCxp" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <form method="POST" id="formBandejaEnviarCxp" action="" enctype="multipart/form-data" data-ordencompra-id="">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title">Enviar a Cuentas a pagar</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                </div>
                <div class="modal-body">
                    <p class="text-muted small">Pasa el legajo a Cuentas a pagar. Exige factura. La COM es obligatoria según la empresa o el contrato.</p>
                    @include('compras.ordencompra.partials.bloque_factura_legajo_sector', [
                        'prefix' => 'bandeja_ocx',
                        'tituloBloque' => 'Factura y recepción del legajo',
                    ])
                    <div class="form-group">
                        <label for="bandeja_ocx_obs">Observación</label>
                        <input type="text" name="observacion" id="bandeja_ocx_obs" class="form-control" maxlength="255">
                    </div>
                    <div class="form-group">
                        <label for="bandeja_ocx_leyenda">Leyenda / detalle</label>
                        <textarea name="leyenda" id="bandeja_ocx_leyenda" class="form-control" rows="2" maxlength="2000"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary">Enviar a Cuentas a pagar</button>
                </div>
            </form>
        </div>
    </div>
</div>
<div class="modal fade" id="modalBandejaPendienteEntrega" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Facturas sin COM</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
            </div>
            <div class="modal-body">
                <p class="text-muted small mb-2">
                    Para enviar el legajo (Gastronomía o Cuentas a pagar), cada factura que exige COM debe tener recepción asignada
                    <strong>o</strong> quedar marcada como pendiente de entrega (mercadería que llega después).
                    Las retenidas no se cargan en CxP y no bloquean el envío a Pagos del resto.
                </p>
                <div id="bandejaPendienteEntregaLista"></div>
                <p class="small text-muted mb-0 mt-2" id="bandejaPendienteEntregaHint"></p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-outline-primary" id="btnBandejaAbrirAsignarCom">Asignar COM…</button>
                <button type="button" class="btn btn-warning" id="btnBandejaMarcarPendienteEntrega">Marcar seleccionadas y continuar</button>
            </div>
        </div>
    </div>
</div>
@endif
@if (!empty($puede_enviar_pagos))
<div class="modal fade" id="modalBandejaEnviarPagos" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <form method="POST" id="formBandejaEnviarPagos" action="">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title">Enviar a Pagos</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                </div>
                <div class="modal-body">
                    <p class="text-muted small">Pasa el legajo de Cuentas a pagar a <strong>PAGOS</strong>. Requiere la factura ya cargada.</p>
                    <div class="form-group">
                        <label for="bandeja_ocp_obs">Observación</label>
                        <input type="text" name="observacion" id="bandeja_ocp_obs" class="form-control" maxlength="255">
                    </div>
                    <div class="form-group">
                        <label for="bandeja_ocp_leyenda">Leyenda / detalle</label>
                        <textarea name="leyenda" id="bandeja_ocp_leyenda" class="form-control" rows="2" maxlength="2000"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary">Enviar a Pagos</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endif
@if (!empty($puede_devolver_cxp) || !empty($puede_devolver_compras))
@include('compras.partials.modal_devolver_legajo', [
    'modalId' => 'modalBandejaDevolverLegajo',
    'formId' => 'formBandejaDevolverLegajo',
    'puedeCxp' => !empty($puede_devolver_cxp),
    'puedeCompras' => !empty($puede_devolver_compras),
    'urlCxp' => '',
    'urlCompras' => '',
    'dinamico' => true,
])
@endif

<div class="modal fade" id="modalBandejaHistoria" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Historia del legajo</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
            </div>
            <div class="modal-body p-0">
                <table class="table table-sm table-striped mb-0" id="tablaBandejaHistoria">
                    <thead><tr><th>Fecha</th><th>Sector</th><th>Observación</th><th>Leyenda</th><th>Usuario</th></tr></thead>
                    <tbody></tbody>
                </table>
            </div>
        </div>
    </div>
</div>
<div class="modal fade" id="modalBandejaNota" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <form method="POST" id="formBandejaNota" action="">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title">Nota del legajo</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                </div>
                <div class="modal-body">
                    <label for="bandeja_nota_texto" class="small font-weight-bold">Nota interna</label>
                    <textarea name="nota_legajo" id="bandeja_nota_texto" class="form-control" rows="5" maxlength="4000"
                              placeholder="Escriba una nota visible para quienes trabajan el legajo…"></textarea>
                    <p class="text-muted small mb-0 mt-2">Deje vacío y guarde para quitar la nota. El ícono queda destacado mientras haya texto.</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-warning">Guardar nota</button>
                </div>
            </form>
        </div>
    </div>
</div>
<div class="modal fade" id="modalBandejaLegajo" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered" role="document" style="max-width: 96vw;">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="bandejaLegajoTitulo">Legajo</h5>
                <a id="bandejaLegajoOc" href="#" class="btn btn-sm btn-outline-info ml-2" target="_blank" rel="noopener" style="display:none;">
                    <i class="fa fa-file-text-o"></i> Abrir OC
                </a>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
            </div>
            <div class="modal-body">
                <div class="tabs-activas mb-2">
                    <ul class="nav nav-tabs" id="tabs-bandeja-legajo" role="tablist">
                        <li class="nav-item">
                            <a class="nav-link active" id="bandeja-tab-facturas" data-toggle="tab" href="#tab-bandeja-facturas" role="tab">
                                <i class="fa fa-file-pdf-o"></i> Facturas
                                <span class="badge badge-secondary" id="bandejaLegajoNFac">0</span>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" id="bandeja-tab-coms" data-toggle="tab" href="#tab-bandeja-coms" role="tab">
                                <i class="fa fa-cubes"></i> COM
                                <span class="badge badge-secondary" id="bandejaLegajoNCom">0</span>
                            </a>
                        </li>
                        <li class="nav-item" id="bandeja-tab-pagos-item">
                            <a class="nav-link" id="bandeja-tab-pagos" data-toggle="tab" href="#tab-bandeja-pagos" role="tab">
                                <i class="fa fa-money"></i> Pagos
                                <span class="badge badge-secondary" id="bandejaLegajoNPago">0</span>
                            </a>
                        </li>
                    </ul>
                </div>
                <div class="tab-content">
                    <div class="tab-pane fade show active" id="tab-bandeja-facturas" role="tabpanel">
                        <div class="row">
                            <div class="col-lg-4 mb-2">
                                <div class="table-responsive" style="max-height: 70vh; overflow:auto;">
                                    <table class="table table-sm table-striped table-hover mb-0" id="tablaBandejaFacturas">
                                        <thead><tr><th>Comprobante</th><th>Fecha</th><th>Origen</th><th>COM</th><th>Estado</th><th>Pago</th></tr></thead>
                                        <tbody></tbody>
                                    </table>
                                </div>
                            </div>
                            <div class="col-lg-8">
                                <iframe id="bandejaFacturaPdf" title="Factura" style="width:100%; height:70vh; border:1px solid #dee2e6; background:#f8f9fa;"></iframe>
                            </div>
                        </div>
                    </div>
                    <div class="tab-pane fade" id="tab-bandeja-coms" role="tabpanel">
                        <div class="row">
                            <div class="col-lg-4 mb-2">
                                <div class="table-responsive" style="max-height: 70vh; overflow:auto;">
                                    <table class="table table-sm table-striped table-hover mb-0" id="tablaBandejaComs">
                                        <thead><tr><th>Documento</th><th>Neto</th><th>Estado</th><th>Asignada / sugerida</th></tr></thead>
                                        <tbody></tbody>
                                    </table>
                                </div>
                            </div>
                            <div class="col-lg-8">
                                <iframe id="bandejaComPdf" title="COM" style="width:100%; height:70vh; border:1px solid #dee2e6; background:#f8f9fa;"></iframe>
                            </div>
                        </div>
                    </div>
                    <div class="tab-pane fade" id="tab-bandeja-pagos" role="tabpanel">
                        <div id="bandejaLegajoPagos" class="bandeja-pagos-tool p-1"></div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@if (!empty($puede_asignar_com))
<div class="modal fade" id="modalBandejaAsignarCom" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <form method="POST" id="formBandejaAsignarCom" action="">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title">Asignar COM a los comprobantes</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                </div>
                <div class="modal-body">
                    <p class="bandeja-asig-hint">
                        Solo pendientes (aún no cargados en CxP). Corregí el tipo a la derecha si hace falta.
                        NC/ND no exigen COM. Un escaneo de Anita que no corresponda se descarta con la cruz (queda registrado y se puede deshacer).
                    </p>
                    <div class="row">
                        <div class="col-md-5 mb-3">
                            <span class="bandeja-asig-col-label">Comprobantes del legajo</span>
                            <div id="bandejaAsignarPrecarga" class="list-group list-group-flush"></div>
                            <div id="bandejaScansDescartados" class="mt-2" style="display:none;"></div>
                        </div>
                        <div class="col-md-7 mb-3">
                            <span class="bandeja-asig-col-label">COM del comprobante seleccionado</span>
                            <div id="bandejaAsignarComs"></div>
                        </div>
                    </div>
                    <div id="bandejaAsignarAtajos" class="mt-2"></div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary">Guardar asignaciones</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endif


<div class="row bandeja-legajos-row">
    <div class="col-lg-12">
        @include('includes.mensaje')
        <div class="card card-info lw-workbench shadow-sm">
            <div class="card-header lw-header d-flex flex-wrap align-items-center justify-content-between">
                <h3 class="card-title mb-0">Bandeja de legajos</h3>
                <div class="card-tools ml-auto d-flex flex-wrap align-items-center justify-content-end" style="gap:.35rem;">
                    @include('includes.ayuda.boton-guia', [
                        'slug' => 'bandeja-legajos',
                        'titulo' => 'Guía: bandeja de legajos (COM y envío)',
                        'clase' => 'btn btn-outline-light btn-sm',
                    ])
                    @if (can('listar-seguimiento-legajo-compra', false))
                        <a href="{{ route('consultar_seguimiento_legajo_compra') }}" class="btn btn-outline-light btn-sm">
                            <i class="fa fa-search"></i> Seguimiento
                        </a>
                    @endif
                    <a href="{{ route('consultar_ordencompra') }}" class="btn btn-outline-light btn-sm">
                        <i class="fa fa-file-text-o"></i> Órdenes de compra
                    </a>
                    <button type="button"
                            class="btn btn-outline-light btn-sm collapsed"
                            id="btn-lw-toolbar-toggle"
                            data-toggle="collapse"
                            data-target="#lw-toolbar-panel"
                            aria-expanded="false"
                            aria-controls="lw-toolbar-panel"
                            title="Vistas guardadas, diseñar columnas y consulta QBE">
                        <i class="fa fa-th-large"></i> Vista y diseño
                        @if (($vistaActiva ?? null) || ($qbeActivo ?? false) || (($ordenActualThead ?? []) !== []))
                            <span class="badge badge-warning ml-1" title="Hay vista, orden o QBE activo">●</span>
                        @endif
                    </button>
                </div>
            </div>
            @if (! ($workbenchListo ?? false))
                <div class="alert alert-warning lw-aviso-migracion mb-0">
                    <strong>Migración pendiente.</strong>
                    Para vistas y configuración de grilla hace falta la tabla <code>listado_vista</code>.
                </div>
            @endif
            <form method="get" action="{{ route('consultar_legajo_compra') }}" id="form-filtros-legajo-bandeja" class="mb-0">
                <input type="hidden" name="vista" value="{{ $vista }}">
                <input type="hidden" name="tab" value="{{ $tab }}">
                @if ($atajo !== '')
                    <input type="hidden" name="atajo" value="{{ $atajo }}">
                @endif
                <input type="hidden" name="filtro_busqueda_rapida" id="filtro_busqueda_rapida" value="">
                <input type="hidden" name="filtro_modo" id="filtro_modo" value="{{ $filtros['modo'] ?? 'todos' }}">
                <input type="hidden" name="columnas" id="lw_columnas_csv" value="{{ implode(',', $columnasVisibles ?? []) }}">
                @if (($filtros['empresa_scope'] ?? '') === 'todas')
                    <input type="hidden" name="empresa_todas" value="1">
                @elseif (! empty($filtros['empresa_id']))
                    <input type="hidden" name="empresa_id" value="{{ $filtros['empresa_id'] }}">
                @endif
                @if (! empty($filtros['presupuesto']))
                    <input type="hidden" name="presupuesto" value="{{ $filtros['presupuesto'] }}">
                @endif
                @if ($vistaActiva ?? null)
                    <input type="hidden" name="vista_id" value="{{ $vistaActiva->id }}">
                @elseif (! empty($filtros['vista_estandar']))
                    <input type="hidden" name="vista_estandar" value="1">
                @endif
                {{-- Workbench (vista / diseñar / QBE / defaults): colapsado por defecto. --}}
                <div class="collapse" id="lw-toolbar-panel">
                    <div class="lw-toolbar">
                        <div class="lw-toolbar-left">
                            <select id="lw-vista-select" class="form-control form-control-sm lw-vista-select"
                                    data-base-url="{{ route('consultar_legajo_compra') }}"
                                    title="Vistas guardadas"
                                    @if (! ($workbenchListo ?? false)) disabled @endif>
                                <option value="">Vista estándar</option>
                                @foreach (($vistasListado ?? []) as $vistaGuardada)
                                    <option value="{{ $vistaGuardada->id }}" @if (($vistaActiva ?? null) && (int) $vistaActiva->id === (int) $vistaGuardada->id) selected @endif>
                                        {{ $vistaGuardada->nombre }}
                                        @if ($vistaGuardada->es_default) ★ @endif
                                        @if ($vistaGuardada->compartida) (compartida) @endif
                                    </option>
                                @endforeach
                            </select>
                            <button type="button" class="btn btn-sm btn-primary" data-toggle="modal" data-target="#modal-lw-grilla"
                                    @if (! ($workbenchListo ?? false)) disabled title="Requiere migración" @endif>
                                <i class="fa fa-th"></i> Diseñar vista
                            </button>
                            <button type="button"
                                    class="btn btn-sm {{ ($qbeActivo ?? false) ? 'btn-info' : 'btn-outline-info' }} {{ ($qbeAbierto ?? false) ? '' : 'collapsed' }}"
                                    data-toggle="collapse" data-target="#lw-qbe-panel"
                                    aria-expanded="{{ ($qbeAbierto ?? false) ? 'true' : 'false' }}"
                                    aria-controls="lw-qbe-panel"
                                    title="{{ ($qbeActivo ?? false) ? 'Hay criterios QBE aplicados' : 'Abrir criterios QBE (filtros de arriba siguen visibles)' }}">
                                <i class="fa fa-filter"></i> Consulta avanzada
                            </button>
                            <button type="button" class="btn btn-sm btn-outline-secondary" data-toggle="modal" data-target="#modal-lw-etiquetas"
                                    @if (! ($workbenchListo ?? false)) disabled title="Requiere migración" @endif>
                                <i class="fa fa-font"></i> Defaults instalación
                            </button>
                        </div>
                        <div class="lw-toolbar-right">
                            @if (($ordenActualThead ?? []) !== [])
                                @php
                                    $partesOrdenBarra = [];
                                    foreach ($ordenActualThead as $criterioOrdenBarra) {
                                        $campoOrdenBarra = (string) ($criterioOrdenBarra['campo'] ?? '');
                                        $etiquetaOrdenBarra = ($etiquetasColumnas ?? [])[$campoOrdenBarra]
                                            ?? ($camposOrdenablesThead[$campoOrdenBarra]['label'] ?? $campoOrdenBarra);
                                        $flechaOrdenBarra = ($criterioOrdenBarra['dir'] ?? '') === 'desc' ? '↓' : '↑';
                                        $partesOrdenBarra[] = trim($etiquetaOrdenBarra.' '.$flechaOrdenBarra);
                                    }
                                    $textoOrdenBarra = implode(' · ', $partesOrdenBarra);
                                @endphp
                                <span class="btn btn-sm btn-info lw-orden-recordado" title="Este orden queda recordado para su usuario">
                                    <i class="fa fa-sort-amount-down"></i> {{ $textoOrdenBarra }}
                                </span>
                                <a href="{{ $urlQuitarOrden }}" class="btn btn-sm btn-outline-secondary"
                                   title="Olvida el orden elegido y vuelve al orden estándar de la bandeja">
                                    <i class="fa fa-undo"></i> Orden estándar
                                </a>
                            @endif
                        </div>
                    </div>
                </div>
                {{-- Búsqueda rápida + empresa + chips originales + QBE (AND). --}}
                <div class="lw-filtros-bloque">
                    @include('compras.legajo_bandeja.partials.filtros_busqueda')
                    @include('compras.ordencompra.partials.filtros_externos', [
                        'rutaIndex' => 'consultar_legajo_compra',
                        'filtros' => $filtros,
                        'filtrosQuery' => $filtrosQuery ?? [],
                        'empresa_query' => $empresa_query ?? collect(),
                        'exportRuta' => 'listar_legajo_compra',
                        'exportQueryparams' => $filtrosQuery ?? [],
                    ])
                    @include('compras.legajo_bandeja.partials.chips_circuito')
                    @include('compras.legajo_bandeja.partials.workbench_qbe')
                </div>
            </form>
            <div class="px-3 pt-2">
                @include('includes.listado.workbench_cortes', ['cortes' => $cortes ?? []])
            </div>
            <div class="card-body py-2">
                <p class="text-muted small mb-1">
                    El legajo es la OC (sector, historia, factura y COM).
                    Compras envía; Cuentas a pagar carga la factura y envía a Pagos; Pagos ve solo lo que está en Pagos y archiva.
                    <strong>Listo para cargar</strong> abre Cuentas a pagar con facturas pendientes.
                </p>
                @if (! empty($alcanceSector))
                    <p class="text-muted small mb-0">{{ $alcanceSector }}</p>
                @endif
            </div>
            <div class="tabla-ancha-grilla bandeja-grilla-ancha" data-tabla-ancha>
                <div class="tabla-ancha-scroll-top" hidden>
                    <div class="tabla-ancha-scroll-top-inner"></div>
                </div>
                <div class="tabla-ancha-wrap p-0">
                <table class="table table-striped table-bordered table-hover tabla-ancha mb-0" id="tabla-paginada">
                    <thead style="background-color:#85C1E9;color:#17202A;">
                        <tr>
                            @foreach (($columnasVisibles ?? []) as $keyColumna)
                                @include('compras.legajo_bandeja.partials.workbench_th', [
                                    'key' => $keyColumna,
                                    'camposOrdenablesThead' => $camposOrdenablesThead,
                                    'ordenActualThead' => $ordenActualThead,
                                ])
                            @endforeach
                            <th class="lw-col-acciones col-fija-der-1 text-center" data-orderable="false">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        @php
                            $agruparActivo = \App\Support\Listado\ListadoAgrupacionSupport::normalizar(
                                $filtros['agrupar'] ?? [],
                                \App\Support\Compras\OrdencompraLegajoBandejaFiltros::camposOrdenables()
                            );
                            $filasVista = \App\Support\Listado\ListadoAgrupacionSupport::segmentar(
                                $filas,
                                $agruparActivo,
                                static fn ($row, string $campo): string => \App\Support\Compras\OrdencompraLegajoBandejaListadoColumnas::valorCelda($row, $campo),
                                $etiquetasColumnas ?? [],
                                ! empty($cortes['por_clave']) ? $cortes['por_clave'] : null
                            );
                            $colspanGrilla = count($columnasVisibles ?? []) + 1;
                        @endphp
                        @forelse ($filasVista as $filaVista)
                            @if (($filaVista['type'] ?? '') === 'header')
                                <tr class="lw-group-header lw-group-nivel-{{ (int) ($filaVista['nivel'] ?? 0) }}">
                                    <td colspan="{{ $colspanGrilla }}">
                                        <i class="fa fa-folder-open-o"></i>
                                        <strong>{{ $filaVista['label'] }}:</strong>
                                        {{ $filaVista['valor'] !== '' ? $filaVista['valor'] : '(vacío)' }}
                                        <span class="lw-group-count">
                                            {{ number_format((int) ($filaVista['count'] ?? 0), 0, ',', '.') }}
                                        </span>
                                    </td>
                                </tr>
                            @else
                                @php
                                    $row = $filaVista['row'] ?? $filaVista;
                                    if (is_object($row) && method_exists($row, 'toArray')) {
                                        $row = $row->toArray();
                                    } elseif (is_object($row)) {
                                        $row = (array) $row;
                                    }
                                    $nCargadasFac = (int) ($row['facturas_cargadas_count'] ?? 0);
                                    $hayFacPendiente = ! empty($row['facturas_legajo']);
                                    $puedeConsultarFacturas = ! empty($row['url_paquete'])
                                        && ($hayFacPendiente || $nCargadasFac > 0 || ! empty($row['url_factura']));
                                @endphp
                                <tr>
                                    @foreach (($columnasVisibles ?? []) as $keyColumna)
                                        @include('compras.legajo_bandeja.partials.workbench_celda', ['key' => $keyColumna, 'row' => $row])
                                    @endforeach
                                    @include('compras.legajo_bandeja.partials.workbench_herramientas', ['row' => $row])
                                </tr>
                            @endif
                        @empty
                            <tr>
                                <td colspan="{{ $colspanGrilla }}" class="text-center text-muted">
                                    @if (! empty($sinSectorAsignado))
                                        No tiene sector de legajo asignado. No se muestran registros.
                                    @else
                                        No hay legajos para estos filtros.
                                    @endif
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
                </div>
            </div>
            @if (method_exists($filas, 'links'))
                <div class="card-footer">
                    {{ $filas->appends($filtrosQuery ?? [])->links() }}
                </div>
            @endif
        </div>
    </div>
</div>

@include('includes.listado.workbench_columnas_dnd', [
    'rutaGuardarColumnas' => 'guardar_columnas_listado_legajo_bandeja',
    'grillaLayout' => $grillaLayout ?? [],
    'filtros' => $filtros ?? [],
    'filtrosQuery' => $filtrosQuery ?? [],
    'vistaActiva' => $vistaActiva ?? null,
    'paraQueryStringDnd' => [\App\Support\Compras\OrdencompraLegajoBandejaFiltros::class, 'paraQueryString'],
])
@include('compras.legajo_bandeja.partials.workbench_modales')

@endsection
