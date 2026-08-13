@extends("theme.$theme.layout")
@section('titulo')
    Asignar remitos a facturas
@endsection

@section('scripts')
<style>
    .arf-workbench {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 1rem;
        align-items: stretch;
    }
    @media (max-width: 1199px) {
        .arf-workbench { grid-template-columns: 1fr; }
    }
    .arf-panel thead th {
        background: #85C1E9;
        color: #17202A;
        font-size: 0.78rem;
        white-space: nowrap;
    }
    .arf-panel tbody td {
        vertical-align: top;
        font-size: 0.8rem;
    }
    .arf-panel .table-wrap {
        max-height: 28rem;
        overflow: auto;
    }
    .arf-row {
        cursor: pointer;
    }
    .arf-row.is-selected {
        background: #d6eaf8 !important;
        box-shadow: inset 4px 0 0 #2471a3;
    }
    .arf-row.is-match {
        background: #eafaf1 !important;
    }
    .arf-row.is-used {
        opacity: 0.45;
        pointer-events: none;
    }
    .arf-badge-origen {
        font-size: 0.68rem;
        font-weight: 600;
    }
    .arf-articulos {
        font-size: 0.72rem;
        color: #566573;
        max-width: 14rem;
        line-height: 1.25;
    }
    .arf-pares {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
        gap: 0.75rem;
    }
    .arf-par {
        border: 1px solid #d6eaf8;
        border-radius: 6px;
        background: #f8fbfc;
        padding: 0.75rem;
        position: relative;
    }
    .arf-par.is-excelente { border-color: #27ae60; background: #eafaf1; }
    .arf-par.is-bueno { border-color: #5dade2; background: #ebf5fb; }
    .arf-par.is-regular { border-color: #f4d03f; background: #fef9e7; }
    .arf-par.is-distinto { border-color: #e74c3c; background: #fdedec; }
    .arf-par-flujo {
        display: grid;
        grid-template-columns: 1fr auto 1fr;
        gap: 0.4rem;
        align-items: center;
    }
    .arf-par-lado {
        background: #fff;
        border-radius: 4px;
        padding: 0.45rem 0.5rem;
        min-width: 0;
    }
    .arf-par-lado .lbl {
        font-size: 0.65rem;
        color: #566573;
        text-transform: uppercase;
        letter-spacing: 0.02em;
        display: block;
    }
    .arf-par-lado .val {
        font-weight: 700;
        color: #17202A;
        font-size: 0.82rem;
        display: block;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }
    .arf-par-lado .meta {
        font-size: 0.72rem;
        color: #5d6d7e;
    }
    .arf-flecha {
        color: #2471a3;
        font-size: 1.25rem;
    }
    .arf-acciones-fijas {
        position: sticky;
        bottom: 0;
        z-index: 1040;
        background: #fff;
        border-top: 2px solid #85C1E9 !important;
        box-shadow: 0 -4px 14px rgba(23, 32, 42, 0.12);
        padding: 0.75rem 1rem;
    }
    .arf-preview {
        display: none;
        border: 1px dashed #5dade2;
        border-radius: 6px;
        background: #f4f9fc;
        padding: 0.75rem 1rem;
        margin-bottom: 1rem;
    }
    .arf-preview.is-visible { display: block; }
    .arf-preview-grid {
        display: grid;
        grid-template-columns: 1fr auto 1fr;
        gap: 0.75rem;
        align-items: start;
    }
    @media (max-width: 767px) {
        .arf-preview-grid { grid-template-columns: 1fr; }
        .arf-preview-flecha { display: none; }
    }
    .arf-hint { font-size: 0.8rem; color: #5d6d7e; margin: 0; }
    .arf-empty {
        text-align: center;
        color: #7f8c8d;
        padding: 1.5rem 0.5rem;
    }
    .arf-vista .btn {
        font-weight: 600;
    }
    .arf-contador {
        font-size: 0.78rem;
        font-weight: 600;
        color: #1a5276;
    }
</style>
<script>
window.arfConfig = {
    urlConsultar: @json(route('consultar_asignacion_remito_factura')),
    urlConfirmar: @json(route('confirmar_asignacion_remito_factura')),
    puedeEjecutar: @json(!empty($puedeEjecutar)),
    puedeEditarRemito: @json(!empty($puedeEditarRemito)),
    puedeListarRemito: @json(!empty($puedeListarRemito)),
    puedeEditarFactura: @json(!empty($puedeEditarFactura)),
    puedeListarFactura: @json(!empty($puedeListarFactura)),
};
</script>
<script src="{{ asset('assets/pages/scripts/ventas/asignacion_remito_factura/index.js') }}?v={{ @filemtime(public_path('assets/pages/scripts/ventas/asignacion_remito_factura/index.js')) ?: time() }}" type="text/javascript"></script>
@endsection

@section('contenido')
<meta name="csrf-token" content="{{ csrf_token() }}">
<div class="row">
    <div class="col-lg-12">
        @include('includes.mensaje')
        <div class="card card-primary">
            <div class="card-header">
                <h3 class="card-title">Asignar remitos a facturas</h3>
                <div class="card-tools">
                    <a href="{{ route('remito') }}" class="btn btn-outline-info btn-sm" style="background:#fff;color:#1a5276;">
                        <i class="fa fa-reply-all"></i> Remitos
                    </a>
                    <a href="{{ route('factura') }}" class="btn btn-outline-info btn-sm" style="background:#fff;color:#1a5276;">
                        <i class="fa fa-file-invoice"></i> Facturas
                    </a>
                </div>
            </div>
            <form id="form-arf-consultar" class="mb-0" onsubmit="return false;">
                <div class="card-body pb-2">
                    <p class="arf-hint mb-3">
                        Toma remitos (reparto 101 / F5) y facturas de <strong>una empresa</strong> desde la fecha indicada.
                        Así no se mezclan comprobantes de la división (p. ej. PV 15 Villafranca) con El Bierzo.
                        Por defecto muestra huérfanos: remitos sin factura y facturas sin remito.
                        Al confirmar, el remito conserva su número y pasa a ser el remito de la factura (cliente, fecha y artículos).
                    </p>
                    @include('includes.form-empresa-asignada', [
                        'empresa_query' => $empresa_query ?? collect(),
                        'empresa_id' => $filtros['empresa_id'] ?? 0,
                        'label' => 'Empresa',
                        'col_label' => 'col-lg-2 text-right pr-2',
                        'col_input' => 'col-lg-4',
                        'required' => true,
                        'id' => 'arf_empresa_id',
                        'name' => 'empresa_id',
                    ])
                    <div class="form-group row mb-2">
                        <label for="arf_fecha_desde" class="col-lg-2 control-label text-right pr-2 requerido">Desde</label>
                        <div class="col-lg-2">
                            <input type="date" name="fecha_desde" id="arf_fecha_desde" class="form-control"
                                   value="{{ $filtros['fecha_desde'] ?? date('Y-m-d') }}" required>
                        </div>
                        <label for="arf_fecha_hasta" class="col-lg-2 control-label text-right pr-2">Hasta (opcional)</label>
                        <div class="col-lg-2">
                            <input type="date" name="fecha_hasta" id="arf_fecha_hasta" class="form-control"
                                   value="{{ $filtros['fecha_hasta'] ?? '' }}">
                        </div>
                        <label for="arf_filtro_reparto" class="col-lg-2 control-label text-right pr-2">Reparto</label>
                        <div class="col-lg-2">
                            <input type="text" name="filtro_reparto" id="arf_filtro_reparto" class="form-control"
                                   value="{{ $filtros['filtro_reparto'] ?? '' }}"
                                   placeholder="Ej: 101"
                                   autocomplete="off">
                        </div>
                    </div>
                    <div class="form-group row mb-0">
                        <label class="col-lg-2 control-label text-right pr-2">Vista</label>
                        <div class="col-lg-6 arf-vista">
                            <div class="btn-group btn-group-sm" role="group">
                                <button type="button" class="btn btn-info js-arf-vista" data-vista="huerfanos" id="arf-vista-huerfanos">
                                    Huérfanos
                                </button>
                                <button type="button" class="btn btn-outline-secondary js-arf-vista" data-vista="todos" id="arf-vista-todos">
                                    Todos (descendente)
                                </button>
                            </div>
                            <input type="hidden" name="vista" id="arf_vista" value="{{ $filtros['vista'] ?? 'huerfanos' }}">
                        </div>
                        <div class="col-lg-4 text-right">
                            <button type="button" class="btn btn-primary" id="btn-arf-consultar">
                                <i class="fa fa-search"></i> Consultar
                            </button>
                        </div>
                    </div>
                </div>
            </form>
        </div>

        <div id="arf-preview" class="arf-preview" aria-live="polite">
            <div class="d-flex justify-content-between align-items-center mb-2">
                <strong>Vista previa de la asignación</strong>
                <span id="arf-preview-nivel" class="badge badge-info"></span>
            </div>
            <div class="arf-preview-grid">
                <div>
                    <div class="small text-muted mb-1">Factura (destino)</div>
                    <div id="arf-preview-factura"></div>
                </div>
                <div class="arf-preview-flecha text-center pt-4">
                    <i class="fa fa-arrow-left fa-lg text-primary"></i>
                    <div class="small text-muted">el remito se convierte</div>
                </div>
                <div>
                    <div class="small text-muted mb-1">Remito (número que se conserva)</div>
                    <div id="arf-preview-remito"></div>
                </div>
            </div>
            <p class="arf-hint mt-2 mb-0" id="arf-preview-ayuda"></p>
        </div>

        <div class="arf-workbench">
            <div class="card card-outline card-info arf-panel mb-0" id="arf-panel-facturas">
                <div class="card-header">
                    <h3 class="card-title">Facturas</h3>
                    <span class="arf-contador ml-2" id="arf-count-facturas"></span>
                    <div class="card-tools">
                        <input type="search" class="form-control form-control-sm" id="arf_busqueda_factura"
                               placeholder="Buscar factura o cliente…" style="width: 200px;">
                    </div>
                </div>
                <div class="card-body p-0 table-wrap">
                    <table class="table table-sm table-striped table-hover mb-0">
                        <thead>
                            <tr>
                                <th></th>
                                <th>Comprobante</th>
                                <th>Fecha</th>
                                <th>Cliente</th>
                                <th class="text-right">Kg</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody id="arf-tbody-facturas">
                            <tr>
                                <td colspan="6" class="arf-empty">Consultá para listar facturas sin remito.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <div class="card-footer py-1 d-flex justify-content-between align-items-center">
                    <small class="text-muted" id="arf-pag-facturas-info"></small>
                    <div>
                        <button type="button" class="btn btn-sm btn-outline-secondary" id="arf-pag-facturas-prev" disabled>&laquo;</button>
                        <button type="button" class="btn btn-sm btn-outline-secondary" id="arf-pag-facturas-next" disabled>&raquo;</button>
                    </div>
                </div>
            </div>

            <div class="card card-outline card-info arf-panel mb-0" id="arf-panel-remitos">
                <div class="card-header">
                    <h3 class="card-title">Remitos</h3>
                    <span class="arf-contador ml-2" id="arf-count-remitos"></span>
                    <div class="card-tools">
                        <input type="search" class="form-control form-control-sm" id="arf_busqueda_remito"
                               placeholder="Buscar remito o cliente…" style="width: 200px;">
                    </div>
                </div>
                <div class="card-body p-0 table-wrap">
                    <table class="table table-sm table-striped table-hover mb-0">
                        <thead>
                            <tr>
                                <th></th>
                                <th>Código</th>
                                <th>Fecha</th>
                                <th>Cliente</th>
                                <th>Origen</th>
                                <th class="text-right">Kg</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody id="arf-tbody-remitos">
                            <tr>
                                <td colspan="7" class="arf-empty">Consultá para listar remitos huérfanos.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <div class="card-footer py-1 d-flex justify-content-between align-items-center">
                    <small class="text-muted" id="arf-pag-remitos-info"></small>
                    <div>
                        <button type="button" class="btn btn-sm btn-outline-secondary" id="arf-pag-remitos-prev" disabled>&laquo;</button>
                        <button type="button" class="btn btn-sm btn-outline-secondary" id="arf-pag-remitos-next" disabled>&raquo;</button>
                    </div>
                </div>
            </div>
        </div>

        <div class="card card-outline card-success mt-3 mb-5">
            <div class="card-header">
                <h3 class="card-title">Emparejamientos pendientes</h3>
                <span class="arf-contador ml-2" id="arf-count-pares">0</span>
            </div>
            <div class="card-body">
                <div id="arf-pares-vacio" class="arf-empty py-3">
                    Elegí una factura a la izquierda y un remito a la derecha, luego pulsá Vincular.
                    El remito conservará su número y se convertirá en el remito de esa factura.
                </div>
                <div id="arf-pares" class="arf-pares"></div>
            </div>
        </div>
    </div>
</div>

<div class="arf-acciones-fijas">
    <div class="d-flex flex-wrap align-items-center justify-content-between">
        <p class="arf-hint mb-0 mr-3" id="arf-footer-hint">
            Asigná de a un par o acumulá varios y confirmá todo junto.
        </p>
        <div class="d-flex flex-wrap align-items-center" style="gap: 0.4rem;">
            <button type="button" class="btn btn-outline-info btn-sm" id="btn-arf-sugerir" disabled>
                <i class="fa fa-magic"></i> Sugerir en esta página
            </button>
            <button type="button" class="btn btn-outline-primary btn-sm" id="btn-arf-vincular" disabled>
                <i class="fa fa-link"></i> Vincular seleccionados
            </button>
            @if ($puedeEjecutar)
                <button type="button" class="btn btn-outline-success btn-sm" id="btn-arf-confirmar-uno" disabled>
                    <i class="fa fa-check"></i> Confirmar este par
                </button>
                <button type="button" class="btn btn-success btn-sm" id="btn-arf-confirmar-todas" disabled>
                    <i class="fa fa-check-double"></i> Confirmar todas las asignaciones
                </button>
            @endif
        </div>
    </div>
</div>

@include('includes.proceso_overlay_aviso', [
    'overlayId' => 'arf-overlay',
    'tituloId' => 'arf-overlay-titulo',
    'subtituloId' => 'arf-overlay-subtitulo',
    'titulo' => 'Consultando…',
    'subtitulo' => 'Puede demorar según el período. No cierre la página.',
])
@endsection
