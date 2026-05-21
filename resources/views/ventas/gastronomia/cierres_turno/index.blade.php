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
        urlFacturaVerBase: @json(($puede_ver_factura ?? false) ? url('ventas/gastronomia/facturas-dia') : null),
        puedeVerFactura: @json($puede_ver_factura ?? false),
    };
</script>
<script src="{{ asset('assets/pages/scripts/ventas/gastronomia/totales_turno_render.js') }}?v={{ @filemtime(public_path('assets/pages/scripts/ventas/gastronomia/totales_turno_render.js')) }}"></script>
<script src="{{ asset('assets/pages/scripts/ventas/gastronomia/cierres_turno.js') }}?v={{ @filemtime(public_path('assets/pages/scripts/ventas/gastronomia/cierres_turno.js')) }}" type="text/javascript"></script>
<script src="{{asset("assets/pages/scripts/admin/index.js")}}" type="text/javascript"></script>
@endsection

@section('contenido')
<div class="row">
    <div class="col-lg-12">
        @include('includes.mensaje')
        <div class="card card-info">
            <div class="card-header">
                <h3 class="card-title">Cierres de turno gastronomía</h3>
                <div class="card-tools">
                    <a href="{{ route('gastronomia_habilitacion_turno') }}" class="btn btn-outline-secondary btn-sm">
                        <i class="fa fa-key"></i> Habilitación de turno
                    </a>
                </div>
            </div>
            <div class="card-body">
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

                <form action="{{ route('gastronomia_cierres_turno') }}" method="GET" class="form-row align-items-end mb-3">
                    <div class="form-group col-md-2">
                        <label class="small">Empresa</label>
                        <select name="empresa_id" class="form-control form-control-sm">
                            <option value="">Todas</option>
                            @foreach ($empresa_query as $emp)
                                <option value="{{ $emp->id }}" @selected((int) ($filtros['empresa_id'] ?? 0) === (int) $emp->id)>{{ $emp->nombre }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="form-group col-md-2">
                        <label class="small">PC</label>
                        <input type="text" name="identificador_pc" class="form-control form-control-sm" value="{{ $filtros['identificador_pc'] ?? $identificador_pc_default }}" placeholder="Terminal"/>
                    </div>
                    <div class="form-group col-md-2">
                        <label class="small">Desde</label>
                        <input type="date" name="fecha_desde" class="form-control form-control-sm" value="{{ $filtros['fecha_desde'] ?? '' }}"/>
                    </div>
                    <div class="form-group col-md-2">
                        <label class="small">Hasta</label>
                        <input type="date" name="fecha_hasta" class="form-control form-control-sm" value="{{ $filtros['fecha_hasta'] ?? '' }}"/>
                    </div>
                    <div class="form-group col-md-2">
                        <label class="small">Tipo</label>
                        <select name="tipo" class="form-control form-control-sm">
                            <option value="" @selected(($filtros['tipo'] ?? '') === '')>Todos</option>
                            <option value="parcial" @selected(($filtros['tipo'] ?? '') === 'parcial')>Cierre parcial</option>
                            <option value="cierre" @selected(($filtros['tipo'] ?? '') === 'cierre')>Cierre definitivo</option>
                        </select>
                    </div>
                    <div class="form-group col-md-2">
                        <button type="submit" class="btn btn-primary btn-sm"><i class="fa fa-search"></i> Buscar</button>
                    </div>
                </form>

                <div class="mb-2">
                    @include('includes.exportar-tabla-queryparams', [
                        'ruta' => 'listar_gastronomia_cierres_turno',
                        'queryparams' => $filtros ?? [],
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
                                <th>Turno</th>
                                <th>Jornada</th>
                                <th>Usuario</th>
                                <th class="text-right">Total</th>
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
                                <td colspan="10" class="text-center text-muted py-4">
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
@endsection
