@extends("theme.$theme.layout")
@section('titulo')
    Cierres de turno gastronomía
@endsection

@section("scripts")
<style>
    .gastro-grilla-conciliacion-wrap {
        max-height: 420px;
        overflow: auto;
        border: 1px solid #dee2e6;
        border-radius: 4px;
    }
    .gastro-grilla-conciliacion-wrap table { margin-bottom: 0; font-size: 0.85rem; white-space: nowrap; }
    .gastro-grilla-conciliacion-wrap th { position: sticky; top: 0; background: #f8f9fa; z-index: 2; }
</style>
<script>
    window.CIERRES_TURNO_GASTRONOMIA = {
        urlApiComprobantes: @json(route('gastronomia_cierres_turno_api_comprobantes')),
        urlApiCanjesPremio: @json(route('gastronomia_cierres_turno_api_canjes_premio')),
        urlApiCanjesFidelidad: @json(route('gastronomia_cierres_turno_api_canjes_fidelidad')),
        urlApiTicketsTarjeta: @json(route('gastronomia_cierres_turno_api_tickets_tarjeta')),
        urlApiArqueoCierre: @json(route('gastronomia_cierres_turno_api_arqueo_cierre')),
        urlApiCorregirArqueoCierre: @json(route('gastronomia_cierres_turno_api_corregir_arqueo_cierre')),
        urlFacturaVerBase: @json(($puede_ver_factura ?? false) ? url('ventas/gastronomia/facturas-dia') : null),
        puedeVerFactura: @json($puede_ver_factura ?? false),
        puedeCorregirArqueo: @json($puede_corregir_arqueo ?? false),
        csrfToken: @json(csrf_token()),
    };
</script>
<script src="{{ asset('assets/pages/scripts/ventas/gastronomia/totales_turno_render.js') }}?v={{ @filemtime(public_path('assets/pages/scripts/ventas/gastronomia/totales_turno_render.js')) }}"></script>
<script src="{{ asset('assets/pages/scripts/ventas/gastronomia/cierres_turno.js') }}?v={{ @filemtime(public_path('assets/pages/scripts/ventas/gastronomia/cierres_turno.js')) }}" type="text/javascript"></script>
<script src="{{ asset('assets/pages/scripts/includes/listado-filtros.js') }}" type="text/javascript"></script>
<script src="{{ asset('assets/pages/scripts/ventas/gastronomia/cierres_turno_filtro.js') }}" type="text/javascript"></script>
<script src="{{asset("assets/pages/scripts/admin/index.js")}}" type="text/javascript"></script>
@endsection

<?php use App\Support\Ventas\GastronomiaCierresTurnoListadoFiltros; ?>

@section('contenido')
<div class="row">
    <div class="col-lg-12">
        @include('includes.mensaje')
        <div class="card card-info">
            <div class="card-header">
                <h3 class="card-title">Cierres de turno gastronomía</h3>
                <div class="card-tools d-flex flex-wrap align-items-center justify-content-end">
                    @include('includes.listado.filtros_toolbar', [
                        'formId' => 'form-filtros-cierres-turno',
                        'filtroValor' => $filtros['valor'] ?? '',
                        'tieneCriterios' => GastronomiaCierresTurnoListadoFiltros::tieneCriteriosAplicados($filtros ?? []),
                        'limpiarUrl' => route('gastronomia_cierres_turno'),
                        'placeholder' => 'Búsqueda rápida (referencia, PV, turno…)',
                        'toggleTarget' => '#panel-filtros-cierres-turno',
                        'toggleId' => 'btn-toggle-filtros-cierres-turno',
                        'inputId' => 'filtro_valor',
                    ])
                    @if ($puede_ver_todas_terminales ?? false)
                        <div class="custom-control custom-checkbox ml-2 mb-0 align-self-center">
                            <input type="checkbox"
                                   class="custom-control-input"
                                   id="todas_terminales"
                                   name="todas_terminales"
                                   value="1"
                                   form="form-filtros-cierres-turno"
                                   @checked($todas_terminales ?? false)>
                            <label class="custom-control-label small text-nowrap"
                                   for="todas_terminales"
                                   title="Incluye cierres de todas las PCs de la empresa seleccionada">
                                Todas las terminales
                            </label>
                        </div>
                    @endif
                    <a href="{{ route('gastronomia_habilitacion_turno') }}" class="btn btn-outline-secondary btn-sm ml-1">
                        <i class="fa fa-key"></i> Habilitación de turno
                    </a>
                </div>
            </div>
            <form method="get" action="{{ route('gastronomia_cierres_turno') }}" id="form-filtros-cierres-turno" class="mb-0">
                @include('ventas.gastronomia.cierres_turno.partials.filtros_listado')
            </form>
            <div class="card-body">
                @if ($todas_terminales ?? false)
                    <div class="alert alert-secondary py-2 mb-3 small">
                        <i class="fa fa-desktop"></i>
                        Mostrando cierres de <strong>todas las terminales</strong> de la empresa
                        @if ((int) ($filtros['empresa_id'] ?? 0) > 0)
                            seleccionada
                        @endif
                        en el rango de fechas indicado.
                    </div>
                @elseif (! ($puede_ver_todas_terminales ?? false))
                    <div class="alert alert-light border py-2 mb-3 small text-muted">
                        <i class="fa fa-desktop"></i>
                        Mostrando cierres de esta terminal:
                        <strong>{{ $filtros['identificador_pc'] ?? $identificador_pc_default }}</strong>
                    </div>
                @endif
                @if (! empty($jornada['jornada_abierta']))
                    <div class="alert alert-info py-2 mb-3" id="alert-jornada-activa">
                        Jornada activa:
                        <strong>{{ $turno_operativo['fecha_jornada_fmt'] ?? $jornada['fecha_jornada_fmt'] ?? $jornada['fecha_jornada'] }}</strong>
                        @if (! empty($jornada['usuario_apertura']))
                            · Abierta por <strong>{{ $jornada['usuario_apertura'] }}</strong>
                            @if (! empty($jornada['apertura_en']))
                                ({{ $jornada['apertura_en'] }})
                            @endif
                        @endif
                    </div>
                @elseif ($jornada !== null && ($empresa_id_jornada ?? 0) > 0)
                    <div class="alert alert-secondary py-2 mb-3">
                        Sin jornada abierta para esta empresa.
                        @if (can('gestionar-jornada-gastronomia', false))
                            <a href="{{ route('gastronomia_jornada', ['empresa_id' => $empresa_id_jornada]) }}" class="alert-link">Abrir jornada</a>
                        @endif
                    </div>
                @endif

                @if ($requiere_habilitacion_turno ?? true)
                    @if (! empty($turno_operativo['turno_habilitado']))
                        <div class="alert alert-success py-2 mb-3">
                            <strong>Turno activo</strong> en <code>{{ $filtros['identificador_pc'] ?? $identificador_pc_default }}</code>:
                            <strong>{{ $turno_operativo['turno_nombre'] ?? '' }}</strong>
                            — {{ $turno_operativo['usuario_habilitado'] ?? '' }}
                            — Jornada <strong>{{ $turno_operativo['fecha_jornada_fmt'] ?? ($turno_operativo['fecha_jornada'] ?? '') }}</strong>
                            — Habilitado {{ $turno_operativo['habilitacion_en_fmt'] ?? ($turno_operativo['habilitacion_en'] ?? '') }}
                            — Monto ${{ number_format((float) ($turno_operativo['monto_habilitacion'] ?? 0), 2, ',', '.') }}
                            — Cierres parciales: {{ (int) ($turno_operativo['cierres_parciales'] ?? 0) }}
                            <a href="{{ route('gastronomia_habilitacion_turno', ['accion' => 'cierre_parcial']) }}" class="alert-link ml-1">Cierre parcial</a>
                            ·
                            <a href="{{ route('gastronomia_habilitacion_turno', ['accion' => 'cierre_definitivo']) }}" class="alert-link ml-1">Cierre definitivo</a>
                        </div>
                    @else
                        <div class="alert alert-warning py-2 mb-3">
                            No hay turno habilitado en esta terminal.
                            <a href="{{ route('gastronomia_habilitacion_turno') }}" class="alert-link">Habilitar turno</a>
                        </div>
                    @endif
                @endif

                <div class="mb-2 px-2 pt-2">
                    @include('includes.exportar-tabla-queryparams', [
                        'ruta' => 'listar_gastronomia_cierres_turno',
                        'queryparams' => $filtrosQuery ?? [],
                    ])
                </div>

                <div class="table-responsive">
                    <table class="table table-striped table-bordered table-hover mb-0" id="tabla-paginada">
                        <thead>
                            <tr>
                                <th>Tipo</th>
                                <th>Fecha / hora</th>
                                <th>Referencia</th>
                                <th>Empresa</th>
                                <th>PC</th>
                                <th>Punto venta</th>
                                <th>Turno</th>
                                <th>Jornada</th>
                                <th>Usuario</th>
                                <th class="text-right" title="Facturado bruto menos notas de crédito (devoluciones) del turno.">
                                    Total final
                                    <small class="text-muted d-block" style="font-weight:normal;">(NC restadas)</small>
                                </th>
                                <th class="width120" data-orderable="false">Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($filas as $f)
                            <tr>
                                <td>{{ $f->tipo_etiqueta }}</td>
                                <td>{{ $f->fecha_hora }}</td>
                                <td>{{ $f->referencia }}</td>
                                <td>{{ $f->nombreempresa }}</td>
                                <td>{{ $f->identificador_pc }}</td>
                                <td><small>{{ $f->puntoventa_etiqueta !== '' ? $f->puntoventa_etiqueta : '—' }}</small></td>
                                <td>{{ $f->turno_nombre }}</td>
                                <td>{{ $f->fecha_jornada }}</td>
                                <td>{{ $f->usuario }}</td>
                                <td class="text-right">${{ number_format((float) $f->total, 2, ',', '.') }}</td>
                                <td class="text-nowrap">
                                    <button type="button"
                                            class="btn-accion-tabla tooltipsC js-ver-comprobantes-cierre mr-1"
                                            data-tipo="{{ $f->tipo }}"
                                            data-id="{{ $f->id }}"
                                            data-referencia="{{ $f->referencia }}"
                                            title="Ver comprobantes facturados en este cierre">
                                        <i class="fas fa-list text-primary"></i>
                                        <span class="small">Comprobantes</span>
                                    </button>
                                    <button type="button"
                                            class="btn-accion-tabla tooltipsC js-ver-canjes-premio-cierre mr-1"
                                            data-tipo="{{ $f->tipo }}"
                                            data-id="{{ $f->id }}"
                                            data-referencia="{{ $f->referencia }}"
                                            title="Canjes de premios Wigos en este cierre">
                                        <i class="fa fa-gift text-warning"></i>
                                    </button>
                                    <button type="button"
                                            class="btn-accion-tabla tooltipsC js-ver-canjes-fidelidad-cierre mr-1"
                                            data-tipo="{{ $f->tipo }}"
                                            data-id="{{ $f->id }}"
                                            data-referencia="{{ $f->referencia }}"
                                            title="Canjes de fidelidad (tarjeta Wigos) en este cierre">
                                        <i class="fa fa-id-card text-warning"></i>
                                    </button>
                                    <button type="button"
                                            class="btn-accion-tabla tooltipsC js-ver-tickets-tarjeta-cierre mr-1"
                                            data-tipo="{{ $f->tipo }}"
                                            data-id="{{ $f->id }}"
                                            data-referencia="{{ $f->referencia }}"
                                            title="Tickets tarjeta canjeados en este cierre">
                                        <i class="fa fa-barcode text-info"></i>
                                    </button>
                                    @if (
                                        ($puede_corregir_arqueo ?? false)
                                        && ($f->mostrar_arqueo_cierre_fila ?? false)
                                    )
                                        <button type="button"
                                                class="btn-accion-tabla tooltipsC js-corregir-arqueo-cierre mr-1"
                                                data-id="{{ $f->id }}"
                                                data-referencia="{{ $f->referencia }}"
                                                data-puede-editar="{{ ($f->puede_corregir_arqueo_fila ?? false) ? '1' : '0' }}"
                                                title="{{ ($f->puede_corregir_arqueo_fila ?? false)
                                                    ? 'Corregir montos contados por medio de pago y ajustes (redondeo / faltante).'
                                                    : ('Ver arqueo (solo lectura). ' . ($f->bloqueo_corregir_arqueo_fila ?? '')) }}">
                                            <i class="fa {{ ($f->puede_corregir_arqueo_fila ?? false) ? 'fa-edit text-success' : 'fa-eye text-secondary' }}"></i>
                                            <span class="small">{{ ($f->puede_corregir_arqueo_fila ?? false) ? 'Arqueo' : 'Ver arqueo' }}</span>
                                        </button>
                                    @endif
                                    @if ($puede_ver_comprobante ?? false)
                                        @if ($f->tipo === 'parcial')
                                            <a href="{{ route('gastronomia_cierre_turno_comprobante_parcial', ['id' => $f->id, 'inline' => 1]) }}"
                                               target="_blank"
                                               rel="noopener"
                                               class="btn-accion-tabla tooltipsC"
                                               title="PDF resumen de cierre parcial">
                                                <i class="fas fa-file-pdf text-danger"></i>
                                                <span class="small">PDF</span>
                                            </a>
                                        @elseif ($f->tipo === 'cierre')
                                            <a href="{{ route('gastronomia_cierre_turno_comprobante_cierre', ['id' => $f->id, 'inline' => 1]) }}"
                                               target="_blank"
                                               rel="noopener"
                                               class="btn-accion-tabla tooltipsC"
                                               title="PDF resumen de cierre definitivo">
                                                <i class="fas fa-file-pdf text-danger"></i>
                                                <span class="small">PDF</span>
                                            </a>
                                        @endif
                                    @endif
                                </td>
                            </tr>
                            @empty
                            <tr>
                                <td colspan="11" class="text-center text-muted py-4">
                                    Sin cierres parciales ni definitivos para los filtros indicados.
                                </td>
                            </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="modal-comprobantes-cierre" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-xl" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="modal-comprobantes-cierre-titulo">Comprobantes del cierre</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Cerrar">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <p class="text-muted small mb-2" id="modal-comprobantes-cierre-subtitulo"></p>
                <div class="d-flex flex-wrap align-items-center mb-2">
                    <label class="mb-0 small mr-3">
                        <input type="checkbox" id="filtro-solo-diferencias-cierre" class="mr-1"/>
                        Solo comprobantes con diferencia de cobranza
                    </label>
                </div>
                <div id="grilla-comprobantes-cierre" class="gastro-grilla-conciliacion-wrap">
                    <p class="text-muted p-3 mb-0"><i class="fa fa-spinner fa-spin"></i> Cargando…</p>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Cerrar</button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="modal-canjes-premio-cierre" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered" role="document">
        <div class="modal-content">
            <div class="modal-header py-2">
                <h6 class="modal-title" id="modal-canjes-premio-cierre-titulo">Canjes de premios</h6>
                <button type="button" class="close" data-dismiss="modal" aria-label="Cerrar">&times;</button>
            </div>
            <div class="modal-body py-2">
                <p class="text-muted small mb-2" id="modal-canjes-premio-cierre-subtitulo"></p>
                <div id="ct-canjes-premio-error" class="alert alert-danger py-2 small d-none"></div>
                <div class="d-flex flex-wrap justify-content-between align-items-center px-2 py-1 border-bottom bg-light small">
                    <span id="ct-canjes-premio-info">—</span>
                    <div id="ct-canjes-premio-paginacion" class="gastro-grilla-paginacion"></div>
                </div>
                <div class="table-responsive gastro-grilla-conciliacion-wrap">
                    <table class="table table-sm table-bordered mb-0">
                        <thead class="thead-light">
                            <tr>
                                <th>Cupón</th>
                                <th>Factura</th>
                                <th>SKU</th>
                                <th>Artículo</th>
                                <th class="text-right">Cant.</th>
                                <th class="text-right">Puntos</th>
                                <th>Mozo</th>
                                <th>Documento</th>
                                <th>Fecha canje</th>
                            </tr>
                        </thead>
                        <tbody id="ct-canjes-premio-body"></tbody>
                    </table>
                </div>
            </div>
            <div class="modal-footer py-2">
                <button type="button" class="btn btn-sm btn-secondary" data-dismiss="modal">Cerrar</button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="modal-canjes-fidelidad-cierre" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered" role="document">
        <div class="modal-content">
            <div class="modal-header py-2">
                <h6 class="modal-title" id="modal-canjes-fidelidad-cierre-titulo">Canjes de fidelidad</h6>
                <button type="button" class="close" data-dismiss="modal" aria-label="Cerrar">&times;</button>
            </div>
            <div class="modal-body py-2">
                <p class="text-muted small mb-2" id="modal-canjes-fidelidad-cierre-subtitulo"></p>
                <div id="ct-canjes-fidelidad-error" class="alert alert-danger py-2 small d-none"></div>
                <div class="d-flex flex-wrap justify-content-between align-items-center px-2 py-1 border-bottom bg-light small">
                    <span id="ct-canjes-fidelidad-info">—</span>
                    <div id="ct-canjes-fidelidad-paginacion" class="gastro-grilla-paginacion"></div>
                </div>
                <div class="table-responsive gastro-grilla-conciliacion-wrap">
                    <table class="table table-sm table-bordered mb-0">
                        <thead class="thead-light">
                            <tr>
                                <th>Nro. tarjeta</th>
                                <th>Trackdata</th>
                                <th>DNI</th>
                                <th>Titular</th>
                                <th>Categoría</th>
                                <th>SKU</th>
                                <th>Artículo canjeado</th>
                                <th>Factura</th>
                                <th>Fecha canje</th>
                            </tr>
                        </thead>
                        <tbody id="ct-canjes-fidelidad-body"></tbody>
                    </table>
                </div>
            </div>
            <div class="modal-footer py-2">
                <button type="button" class="btn btn-sm btn-secondary" data-dismiss="modal">Cerrar</button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="modal-tickets-tarjeta-cierre" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered" role="document">
        <div class="modal-content">
            <div class="modal-header py-2">
                <h6 class="modal-title" id="modal-tickets-tarjeta-cierre-titulo">Tickets tarjeta canjeados</h6>
                <button type="button" class="close" data-dismiss="modal" aria-label="Cerrar">&times;</button>
            </div>
            <div class="modal-body py-2">
                <p class="text-muted small mb-2" id="modal-tickets-tarjeta-cierre-subtitulo"></p>
                <div id="ct-tickets-tarjeta-error" class="alert alert-danger py-2 small d-none"></div>
                <div class="d-flex flex-wrap justify-content-between align-items-center px-2 py-1 border-bottom bg-light small">
                    <span id="ct-tickets-tarjeta-info">—</span>
                    <div id="ct-tickets-tarjeta-paginacion" class="gastro-grilla-paginacion"></div>
                </div>
                <div class="table-responsive gastro-grilla-conciliacion-wrap">
                    <table class="table table-sm table-bordered mb-0">
                        <thead class="thead-light">
                            <tr>
                                <th>Movimiento</th>
                                <th>Nº ticket</th>
                                <th>Factura</th>
                                <th>Documento</th>
                                <th class="text-right">Importe</th>
                                <th>Fecha emisión</th>
                                <th>Canje ERP</th>
                            </tr>
                        </thead>
                        <tbody id="ct-tickets-tarjeta-body"></tbody>
                    </table>
                </div>
            </div>
            <div class="modal-footer py-2">
                <button type="button" class="btn btn-sm btn-secondary" data-dismiss="modal">Cerrar</button>
            </div>
        </div>
    </div>
</div>

@include('ventas.gastronomia.cierres_turno.partials.modal_corregir_arqueo')
@endsection
