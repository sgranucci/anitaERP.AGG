@extends("theme.$theme.layout")
@section('titulo')
Bandeja de legajos
@endsection

@section('styles')
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
</style>
@endsection

@section('scripts')
<script src="{{ asset('assets/pages/scripts/includes/listado-filtros.js') }}" type="text/javascript"></script>
<script src="{{ asset('assets/pages/scripts/compras/ordencompra/cambiar_sector_legajo.js') }}?v={{ @filemtime(public_path('assets/pages/scripts/compras/ordencompra/cambiar_sector_legajo.js')) ?: time() }}" type="text/javascript"></script>
<script src="{{ asset('assets/pages/scripts/compras/ordencompra/enviar_gastronomia_firmante.js') }}?v={{ @filemtime(public_path('assets/pages/scripts/compras/ordencompra/enviar_gastronomia_firmante.js')) ?: time() }}" type="text/javascript"></script>
<script src="{{ asset('assets/pages/scripts/compras/ordencompra/asignar_factura_legajo.js') }}?v={{ @filemtime(public_path('assets/pages/scripts/compras/ordencompra/asignar_factura_legajo.js')) ?: time() }}" type="text/javascript"></script>
<script src="{{ asset('assets/pages/scripts/compras/legajo_bandeja/bandeja.js') }}?v={{ @filemtime(public_path('assets/pages/scripts/compras/legajo_bandeja/bandeja.js')) ?: time() }}" type="text/javascript"></script>
@endsection

@section('contenido')
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
@if (!empty($puede_devolver_cxp))
<div class="modal fade" id="modalBandejaDevolverCxp" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <form method="POST" id="formBandejaDevolverCxp" action="">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title">Devolver a Cuentas a pagar</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                </div>
                <div class="modal-body">
                    <p class="text-muted small">Vuelve el legajo de Pagos a <strong>CUENTAS A PAGAR</strong>. El comentario es obligatorio.</p>
                    <div class="form-group">
                        <label for="bandeja_dev_cxp_obs">Comentario / motivo</label>
                        <input type="text" name="observacion" id="bandeja_dev_cxp_obs" class="form-control" maxlength="255" required>
                    </div>
                    <div class="form-group">
                        <label for="bandeja_dev_cxp_leyenda">Detalle</label>
                        <textarea name="leyenda" id="bandeja_dev_cxp_leyenda" class="form-control" rows="3" maxlength="2000"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-warning">Devolver a Cuentas a pagar</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endif
@if (!empty($puede_devolver_compras))
<div class="modal fade" id="modalBandejaDevolverCompras" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <form method="POST" id="formBandejaDevolverCompras" action="">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title">Devolver a Compras</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                </div>
                <div class="modal-body">
                    <p class="text-muted small">Vuelve el legajo de Cuentas a pagar a <strong>COMPRAS</strong>. El comentario es obligatorio.</p>
                    <div class="form-group">
                        <label for="bandeja_dev_com_obs">Comentario / motivo</label>
                        <input type="text" name="observacion" id="bandeja_dev_com_obs" class="form-control" maxlength="255" required>
                    </div>
                    <div class="form-group">
                        <label for="bandeja_dev_com_leyenda">Detalle</label>
                        <textarea name="leyenda" id="bandeja_dev_com_leyenda" class="form-control" rows="3" maxlength="2000"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-warning">Devolver a Compras</button>
                </div>
            </form>
        </div>
    </div>
</div>
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

<div class="row">
    <div class="col-lg-12">
        @include('includes.mensaje')
        <div class="card card-info">
            <div class="card-header">
                <h3 class="card-title">Bandeja de legajos</h3>
                <div class="card-tools d-flex flex-wrap align-items-center justify-content-end">
                    @include('includes.ayuda.boton-guia', [
                        'slug' => 'bandeja-legajos',
                        'titulo' => 'Guía: bandeja de legajos (COM y envío)',
                        'clase' => 'btn btn-outline-light btn-sm mr-1',
                    ])
                    @if (can('listar-seguimiento-legajo-compra', false))
                    <a href="{{ route('consultar_seguimiento_legajo_compra') }}" class="btn btn-outline-light btn-sm mr-1">
                        <i class="fa fa-search"></i> Seguimiento
                    </a>
                    @endif
                    <a href="{{ route('consultar_ordencompra') }}" class="btn btn-outline-light btn-sm mr-1">
                        <i class="fa fa-file-text-o"></i> Órdenes de compra
                    </a>
                    @include('includes.listado.filtros_toolbar', [
                        'formId' => 'form-filtros-legajo',
                        'filtroValor' => $filtros['valor'] ?? '',
                        'tieneCriterios' => OrdencompraListadoFiltros::tieneCriteriosTexto($filtros ?? [])
                            || !empty($filtros['nro_oc'])
                            || !empty($filtros['nro_factura'])
                            || !empty($filtros['nro_com'])
                            || !empty($filtros['nro_op'])
                            || !empty($atajo),
                        'limpiarUrl' => $limpiarUrl,
                        'placeholder' => 'Nº OC, proveedor, factura, COM u OP…',
                        'toggleTarget' => '#panel-filtros-ordencompra',
                        'toggleId' => 'btn-toggle-filtros-ordencompra',
                        'inputId' => 'filtro_valor',
                    ])
                </div>
            </div>
            <form method="get" action="{{ route('consultar_legajo_compra') }}" id="form-filtros-legajo" class="mb-0">
                <input type="hidden" name="vista" value="{{ $vista }}">
                <input type="hidden" name="tab" value="{{ $tab }}">
                @if ($atajo !== '')
                    <input type="hidden" name="atajo" value="{{ $atajo }}">
                @endif
                @include('compras.ordencompra.partials.filtros_listado', ['limpiarUrl' => $limpiarUrl])
                <div class="card-body py-2 border-bottom bg-light">
                    <div class="form-row align-items-end">
                        <div class="form-group col-md-2 col-sm-6 mb-2">
                            <label class="small mb-1" for="nro_oc">Nº OC</label>
                            <input type="text" name="nro_oc" id="nro_oc" class="form-control form-control-sm"
                                   value="{{ $filtros['nro_oc'] ?? '' }}" placeholder="Orden de compra" autocomplete="off">
                        </div>
                        <div class="form-group col-md-2 col-sm-6 mb-2">
                            <label class="small mb-1" for="nro_factura">Nº factura</label>
                            <input type="text" name="nro_factura" id="nro_factura" class="form-control form-control-sm"
                                   value="{{ $filtros['nro_factura'] ?? '' }}" placeholder="Número o dígitos" autocomplete="off">
                        </div>
                        <div class="form-group col-md-2 col-sm-6 mb-2">
                            <label class="small mb-1" for="nro_com">Nº COM</label>
                            <input type="text" name="nro_com" id="nro_com" class="form-control form-control-sm"
                                   value="{{ $filtros['nro_com'] ?? '' }}" placeholder="Número o ID" autocomplete="off">
                        </div>
                        <div class="form-group col-md-3 col-sm-6 mb-2">
                            <label class="small mb-1" for="nro_op">Nº orden de pago</label>
                            <input type="text" name="nro_op" id="nro_op" class="form-control form-control-sm"
                                   value="{{ $filtros['nro_op'] ?? '' }}" placeholder="Cuando el legajo está pago" autocomplete="off">
                        </div>
                        <div class="form-group col-md-3 col-sm-6 mb-2">
                            <button type="submit" class="btn btn-primary btn-sm">
                                <i class="fa fa-search"></i> Buscar
                            </button>
                            <a href="{{ $limpiarUrl }}" class="btn btn-outline-secondary btn-sm">Limpiar</a>
                        </div>
                    </div>
                </div>
            </form>
            @include('compras.ordencompra.partials.filtros_externos', [
                'rutaIndex' => 'consultar_legajo_compra',
                'filtros' => $filtros,
                'filtrosQuery' => $filtrosQuery ?? [],
                'empresa_query' => $empresa_query ?? collect(),
                'exportRuta' => 'listar_legajo_compra',
                'exportQueryparams' => $filtrosQuery ?? [],
            ])
            <div class="card-body py-2">
                <p class="text-muted small mb-2">
                    El legajo es la OC (sector, historia, factura y COM).
                    Compras envía; Cuentas a pagar carga la factura y envía a Pagos; Pagos ve solo lo que está en Pagos y archiva.
                    <strong>Listo para cargar</strong> abre Cuentas a pagar y muestra las OC con al menos una factura aún pendiente (aunque otras de la misma OC ya estén cargadas).
                    La columna Facturas muestra solo lo pendiente; las ya ingresadas en CxP se consultan con
                    <strong>N ya en CxP</strong> o el ícono PDF (abre el legajo: facturas, COM y OC).
                </p>
                @if (!empty($alcanceSector))
                    <p class="text-muted small mb-2">{{ $alcanceSector }}</p>
                @endif
                <div class="d-flex flex-wrap align-items-center">
                    <div class="d-flex flex-wrap align-items-center mb-2">
                        <span class="text-muted small mr-2 mb-0"><i class="fa fa-filter"></i> Estado:</span>
                        <div class="btn-group btn-group-sm flex-wrap" role="group" aria-label="Estado del legajo">
                            @foreach ($vistas as $key => $label)
                                <a href="{{ $qs(['vista' => $key]) }}"
                                   class="btn {{ $vista === $key ? 'btn-primary' : 'btn-outline-primary' }}">{{ $label }}</a>
                            @endforeach
                        </div>
                    </div>
                    <div class="d-none d-md-block mx-3 mb-2 align-self-center" style="width:1px;height:28px;background:#dee2e6;"></div>
                    <div class="d-flex flex-wrap align-items-center mb-2">
                        <span class="text-muted small mr-2 mb-0"><i class="fa fa-cutlery"></i> Ámbito:</span>
                        <div class="btn-group btn-group-sm" role="group" aria-label="Ámbito gastronomía">
                            <a href="{{ $qs(['tab' => OrdencompraLegajoBandejaFiltros::TAB_TODOS]) }}"
                               class="btn {{ $tab === OrdencompraLegajoBandejaFiltros::TAB_TODOS ? 'btn-info' : 'btn-outline-info' }}">Todas</a>
                            <a href="{{ $qs(['tab' => OrdencompraLegajoBandejaFiltros::TAB_GASTRONOMIA]) }}"
                               class="btn {{ $tab === OrdencompraLegajoBandejaFiltros::TAB_GASTRONOMIA ? 'btn-info' : 'btn-outline-info' }}">Gastronomía</a>
                            <a href="{{ $qs(['tab' => OrdencompraLegajoBandejaFiltros::TAB_RESTO]) }}"
                               class="btn {{ $tab === OrdencompraLegajoBandejaFiltros::TAB_RESTO ? 'btn-info' : 'btn-outline-info' }}">Resto</a>
                        </div>
                    </div>
                </div>
                <div class="d-flex flex-wrap align-items-center">
                    <span class="text-muted small mr-2 mb-1">Atajos:</span>
                    <div class="btn-group btn-group-sm flex-wrap mb-1" role="group" aria-label="Atajos de documentos">
                        @foreach ($atajos as $key => $label)
                            <a href="{{ $qsAtajo($key) }}"
                               class="btn {{ $atajo === $key ? 'btn-secondary' : 'btn-outline-secondary' }}">{{ $label }}</a>
                        @endforeach
                    </div>
                </div>
            </div>
            <div class="card-body table-responsive p-0">
                <table class="table table-striped table-bordered table-hover">
                    <thead>
                        <tr>
                            <th>Id</th>
                            <th>OC</th>
                            <th>Fecha</th>
                            <th>Empresa</th>
                            <th>Proveedor</th>
                            <th>Centro de costo</th>
                            <th>Sector</th>
                            <th>Días</th>
                            <th>Paquete</th>
                            <th class="bandeja-col-facturas">Facturas</th>
                            @if ($vista !== OrdencompraLegajoBandejaFiltros::VISTA_PENDIENTES)
                                <th>Decisión</th>
                            @endif
                            <th>Herramientas</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($filas as $row)
                            @php
                                $nCargadasFac = (int) ($row['facturas_cargadas_count'] ?? 0);
                                $hayFacPendiente = ! empty($row['facturas_legajo']);
                                $puedeConsultarFacturas = ! empty($row['url_paquete'])
                                    && ($hayFacPendiente || $nCargadasFac > 0 || ! empty($row['url_factura']));
                            @endphp
                            <tr>
                                <td>{{ $row['id'] }}</td>
                                <td>
                                    <a href="{{ $row['url_oc'] }}" target="_blank" rel="noopener">{{ $row['numero'] }}</a>
                                    @if (!empty($row['es_anticipada']))
                                        <span class="badge badge-warning" title="Legajo anticipado (factura antes de recepción)">
                                            <i class="fa fa-clock-o"></i> Anticipado
                                        </span>
                                    @endif
                                    @if (!empty($row['es_gastronomia']))
                                        <span class="badge badge-info">Gastro</span>
                                    @endif
                                </td>
                                <td>{{ $row['fecha'] }}</td>
                                <td><small>{{ $row['empresa'] }}</small></td>
                                <td><small>{{ $row['proveedor'] }}</small></td>
                                <td><small>{{ $row['centrocosto'] }}</small></td>
                                <td><small>{{ $row['sector'] }}</small></td>
                                <td>
                                    @if ((int) $row['dias'] >= (int) ($dias_recordatorio ?? 3))
                                        <span class="badge badge-warning" title="{{ $row['fecha_ubicacion'] }}">{{ $row['dias'] }}</span>
                                    @else
                                        <span title="{{ $row['fecha_ubicacion'] }}">{{ $row['dias'] }}</span>
                                    @endif
                                </td>
                                <td>
                                    @if (!empty($row['paquete_ok']))
                                        <span class="badge badge-success" title="{{ $row['paquete_titulo'] ?? '' }}">{{ $row['paquete_etiqueta'] ?? ((!empty($row['exige_com']) || !array_key_exists('exige_com', $row)) ? 'FC + COM' : 'FC (contrato sin COM)') }}</span>
                                    @else
                                        @if (!empty($row['tiene_factura']))
                                            <span class="badge badge-secondary">FC</span>
                                        @endif
                                        @if (!empty($row['tiene_com']))
                                            <span class="badge badge-secondary">COM</span>
                                        @endif
                                        @if (empty($row['tiene_factura']) && empty($row['tiene_com']))
                                            <span class="text-muted">—</span>
                                        @endif
                                    @endif
                                    @if (!empty($row['tiene_com_asignada']))
                                        <span class="badge badge-primary" title="COM asignada a la factura">asignada</span>
                                    @endif
                                    @if (!empty($row['tiene_comprobante']))
                                        <span class="badge badge-info" title="Todas las facturas y NC del legajo están en CxP">cargada</span>
                                    @elseif (!empty($row['tiene_comprobante_parcial']))
                                        <span class="badge badge-warning" title="Hay comprobantes en CxP, pero quedan documentos pendientes">parcial</span>
                                    @endif
                                    @if (!empty($row['tiene_pendiente_entrega']))
                                        <span class="badge badge-secondary" title="Hay facturas retenidas hasta que llegue la mercadería">pend. entrega</span>
                                    @endif
                                    @if (!empty($row['tiene_pago']))
                                        <span class="badge badge-success" title="Orden de pago">{{ !empty($row['etiqueta_pago']) ? $row['etiqueta_pago'] : 'OP' }}</span>
                                    @endif
                                </td>
                                <td class="small bandeja-col-facturas">
                                    @if ($hayFacPendiente)
                                        @foreach ($row['facturas_legajo'] as $facLeg)
                                            <div class="bandeja-fac-item">
                                                @if (!empty($facLeg['url_pdf']))
                                                    <a href="{{ $facLeg['url_pdf'] }}" class="text-primary" target="_blank" rel="noopener"
                                                       title="Abrir PDF en pantalla completa">{{ $facLeg['numero'] ?? '' }}</a>
                                                @else
                                                    <span>{{ $facLeg['numero'] ?? '' }}</span>
                                                @endif
                                                @if (!empty($facLeg['estado']))
                                                    @php
                                                        $estadoFac = (string) ($facLeg['estado'] ?? '');
                                                        if ($estadoFac === 'en_anita') {
                                                            $badgeFac = 'badge-success';
                                                            $textoFac = 'en Anita';
                                                        } elseif ($estadoFac === 'pendiente_entrega') {
                                                            $badgeFac = 'badge-secondary';
                                                            $textoFac = 'pend. entrega';
                                                        } else {
                                                            $badgeFac = 'badge-warning';
                                                            $textoFac = 'pendiente';
                                                        }
                                                    @endphp
                                                    <span class="badge {{ $badgeFac }}">
                                                        {{ $textoFac }}
                                                    </span>
                                                @endif
                                                @if (!empty($facLeg['origen']))
                                                    <span class="bandeja-fac-origen">{{ $facLeg['origen'] }}</span>
                                                @endif
                                            </div>
                                        @endforeach
                                    @endif
                                    @if ($nCargadasFac > 0)
                                        <div class="bandeja-fac-item">
                                            <button type="button"
                                                    class="btn btn-link bandeja-fac-consultar js-bandeja-ver-legajo"
                                                    data-url-paquete="{{ $row['url_paquete'] }}"
                                                    data-numero="{{ $row['numero'] }}"
                                                    data-tab="facturas"
                                                    title="Consultar el legajo (facturas, COM y OC)">
                                                {{ $nCargadasFac }} ya en CxP
                                            </button>
                                        </div>
                                    @elseif (! $hayFacPendiente)
                                        <span class="text-muted">—</span>
                                    @endif
                                </td>
                                @if ($vista !== OrdencompraLegajoBandejaFiltros::VISTA_PENDIENTES)
                                    <td>
                                        @if (($row['decision'] ?? '') === 'Aprobado')
                                            <span class="badge badge-success">Aprobado</span>
                                        @elseif (($row['decision'] ?? '') === 'Rechazado')
                                            <span class="badge badge-danger">Rechazado</span>
                                        @endif
                                        @if (!empty($row['firmante']))
                                            <div><small>{{ $row['firmante'] }}</small></div>
                                        @endif
                                        @if (!empty($row['fecha_decision']))
                                            <div><small>{{ $row['fecha_decision'] }}</small></div>
                                        @endif
                                        @if (!empty($row['comentario_decision']))
                                            <div><small class="text-muted">{{ $row['comentario_decision'] }}</small></div>
                                        @endif
                                    </td>
                                @endif
                                <td class="text-nowrap">
                                    @if (! $esSectorCxp)
                                    <a href="{{ $row['url_oc'] }}" class="btn btn-xs btn-info" title="Ver orden de compra" target="_blank" rel="noopener">
                                        <i class="fa fa-file-text-o"></i>
                                    </a>
                                    @endif
                                    @if (!empty($puede_actualizar) && !empty($row['url_asignar_factura']))
                                        <button type="button" class="btn btn-xs btn-outline-danger js-oc-asignar-factura"
                                                data-url="{{ $row['url_asignar_factura'] }}"
                                                data-numero="{{ $row['numero'] }}"
                                                data-proveedor="{{ $row['proveedor'] }}"
                                                title="Asignar PDF de factura al legajo">
                                            <i class="fa fa-cloud-upload"></i>
                                        </button>
                                    @endif
                                    @if ($puedeConsultarFacturas)
                                        <button type="button" class="btn btn-xs btn-outline-danger js-bandeja-ver-legajo"
                                                data-url-pdf="{{ $row['url_factura'] ?? '' }}"
                                                data-url-paquete="{{ $row['url_paquete'] }}"
                                                data-numero="{{ $row['numero'] }}"
                                                data-tab="facturas"
                                                title="Consultar facturas del legajo">
                                            <i class="fa fa-file-pdf-o"></i>
                                        </button>
                                    @else
                                        <button type="button" class="btn btn-xs btn-outline-secondary" disabled title="Sin PDF de factura">
                                            <i class="fa fa-file-pdf-o"></i>
                                        </button>
                                    @endif
                                    @if (! $esSectorCxp)
                                    @if (!empty($row['tiene_com']))
                                        <button type="button" class="btn btn-xs btn-outline-dark js-bandeja-ver-legajo"
                                                data-url-pdf="{{ $row['url_com'] }}"
                                                data-url-paquete="{{ $row['url_paquete'] }}"
                                                data-numero="{{ $row['numero'] }}"
                                                data-tab="coms"
                                                title="Consultar COM del legajo">
                                            <i class="fa fa-cubes"></i>
                                        </button>
                                    @else
                                        <button type="button" class="btn btn-xs btn-outline-secondary" disabled title="Sin COM">
                                            <i class="fa fa-cubes"></i>
                                        </button>
                                    @endif
                                    @endif
                                    <button type="button" class="btn btn-xs btn-outline-info js-bandeja-historia"
                                            data-url="{{ $row['url_historia'] }}"
                                            data-numero="{{ $row['numero'] }}"
                                            title="Historia de asignación del legajo">
                                        <i class="fa fa-history"></i>
                                    </button>
                                    <button type="button"
                                            class="btn btn-xs js-bandeja-nota {{ !empty($row['tiene_nota']) ? 'btn-warning' : 'btn-outline-secondary' }}"
                                            data-url="{{ $row['url_nota'] }}"
                                            data-numero="{{ $row['numero'] }}"
                                            data-nota="{{ $row['nota_legajo'] ?? '' }}"
                                            title="{{ !empty($row['tiene_nota']) ? ('Nota: '.$row['nota_legajo']) : 'Agregar nota al legajo' }}">
                                        <i class="fa fa-sticky-note{{ !empty($row['tiene_nota']) ? '' : '-o' }}"></i>
                                    </button>
                                    @if (! $esSectorCxp && !empty($puede_asignar_com) && !empty($row['tiene_factura']) && (!empty($row['tiene_com']) || !empty($row['tiene_pendiente_entrega'])))
                                        <button type="button" class="btn btn-xs btn-outline-primary js-bandeja-asignar-com"
                                                data-url-asignar="{{ $row['url_asignar_com'] }}"
                                                data-url-paquete="{{ $row['url_paquete'] }}"
                                                data-numero="{{ $row['numero'] }}"
                                                title="{{ !empty($row['tiene_pendiente_entrega']) && empty($row['tiene_com']) ? 'Gestionar facturas pendientes de entrega' : 'Asignar COM a la factura' }}">
                                            <i class="fa fa-link"></i>
                                        </button>
                                    @endif
                                    @if (!empty($puede_cargar_cxp) && !empty($row['url_cargar_cxp']))
                                        <a href="{{ $row['url_cargar_cxp'] }}" class="btn btn-xs btn-primary"
                                           title="Cargar {{ $row['siguiente_pendiente'] ?? 'comprobante' }} en Cuentas a pagar">
                                            <i class="fa fa-plus"></i>
                                        </a>
                                    @endif
                                    @if (! $esSectorCxp && !empty($puede_ver_comprobante) && !empty($row['url_comprobante']))
                                        <a href="{{ $row['url_comprobante'] }}" class="btn btn-xs btn-outline-info" title="Ver comprobante cargado" target="_blank" rel="noopener">
                                            <i class="fa fa-check-square-o"></i>
                                        </a>
                                    @endif
                                    @if (!empty($puede_ver_pago) && !empty($row['url_pago']))
                                        @if (!empty($row['url_paquete']))
                                            <button type="button" class="btn btn-xs btn-outline-success js-bandeja-ver-legajo"
                                                    data-url-paquete="{{ $row['url_paquete'] }}"
                                                    data-numero="{{ $row['numero'] }}"
                                                    data-tab="pagos"
                                                    title="Ver pagos del legajo ({{ $row['etiqueta_pago'] ?? 'OP' }})">
                                                <i class="fa fa-money"></i>
                                            </button>
                                        @else
                                            <a href="{{ $row['url_pago'] }}" class="btn btn-xs btn-outline-success" title="Orden de pago {{ $row['etiqueta_pago'] ?? '' }}" target="_blank" rel="noopener">
                                                <i class="fa fa-money"></i>
                                            </a>
                                        @endif
                                    @endif
                                    @if (!empty($puede_actualizar) && !empty($row['puede_enviar']))
                                        <button type="button" class="btn btn-xs btn-outline-primary js-bandeja-enviar-gastro"
                                                data-url="{{ $row['url_enviar'] }}"
                                                data-ordencompra-id="{{ $row['id'] }}"
                                                data-url-paquete="{{ $row['url_paquete'] }}"
                                                data-url-asignar="{{ $row['url_asignar_com'] }}"
                                                data-numero="{{ $row['numero'] }}"
                                                title="Enviar a Gastronomía">
                                            <i class="fa fa-cutlery"></i>
                                        </button>
                                    @endif
                                    @if (!empty($puede_actualizar) && !empty($row['puede_enviar_cxp']))
                                        <button type="button" class="btn btn-xs btn-outline-primary js-bandeja-enviar-cxp"
                                                data-url="{{ $row['url_enviar_cxp'] }}"
                                                data-ordencompra-id="{{ $row['id'] }}"
                                                data-url-paquete="{{ $row['url_paquete'] }}"
                                                data-url-asignar="{{ $row['url_asignar_com'] }}"
                                                data-numero="{{ $row['numero'] }}"
                                                title="{{ ((int) ($row['pendientes_carga'] ?? 0) > 0 && (int) ($row['sector_id'] ?? 0) > 0) ? 'Enviar FC/NC pendientes a Cuentas a pagar' : 'Enviar a Cuentas a pagar' }}">
                                            <i class="fa fa-share"></i>
                                        </button>
                                    @endif
                                    @if (!empty($puede_enviar_pagos) && !empty($row['puede_enviar_pagos']))
                                        <button type="button" class="btn btn-xs btn-outline-success js-bandeja-enviar-pagos"
                                                data-url="{{ $row['url_enviar_pagos'] }}"
                                                title="Enviar a Pagos">
                                            <i class="fa fa-share-square-o"></i>
                                        </button>
                                    @endif
                                    @if (!empty($puede_devolver_cxp) && !empty($row['puede_devolver_cxp']))
                                        <button type="button" class="btn btn-xs btn-outline-warning js-bandeja-devolver-cxp"
                                                data-url="{{ $row['url_devolver_cxp'] }}"
                                                title="Devolver a Cuentas a pagar">
                                            <i class="fa fa-undo"></i>
                                        </button>
                                    @endif
                                    @if (!empty($puede_devolver_compras) && !empty($row['puede_devolver_compras']))
                                        <button type="button" class="btn btn-xs btn-outline-warning js-bandeja-devolver-compras"
                                                data-url="{{ $row['url_devolver_compras'] }}"
                                                title="Devolver a Compras">
                                            <i class="fa fa-reply"></i>
                                        </button>
                                    @endif
                                    @if (!empty($puede_archivar) && !empty($row['puede_finalizar']))
                                        <form method="POST" action="{{ $row['url_finalizar'] }}" class="d-inline"
                                              onsubmit="return confirm('¿Archivar el legajo OC {{ $row['numero'] }}?');">
                                            @csrf
                                            <button type="submit" class="btn btn-xs btn-outline-success" title="Archivar legajo">
                                                <i class="fa fa-archive"></i>
                                            </button>
                                        </form>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="{{ $vista !== OrdencompraLegajoBandejaFiltros::VISTA_PENDIENTES ? 12 : 11 }}" class="text-center text-muted">
                                    @if (!empty($sinSectorAsignado))
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
            @if (method_exists($filas, 'links'))
                <div class="card-footer">
                    {{ $filas->links() }}
                </div>
            @endif
        </div>
    </div>
</div>
@endsection
