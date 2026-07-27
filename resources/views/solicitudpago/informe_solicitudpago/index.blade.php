@extends("theme.$theme.layout")
@section('titulo')
    Informe solicitudes de pago
@endsection

@section('contenido')
@php
    $esTodas = ($filtros['empresa_scope'] ?? 'una') === 'todas';
    $exportQuery = http_build_query($filtrosQuery ?? []);
    $incluirConcil = ! empty($filtros['incluir_conciliacion_mayor']);
    $puedeVerSp = can('editar-solicitud-pago', false) || can('listar-solicitud-pago', false);
    $puedeVerProveedor = can('editar-proveedor', false) || can('listar-proveedor', false);
@endphp
<div class="row">
    <div class="col-lg-12">
        @include('includes.mensaje')
        <div class="card card-info">
            <div class="card-header">
                <h3 class="card-title">Informe de solicitudes de pago</h3>
                <div class="card-tools">
                    @include('includes.solicitudpago.boton-manual')
                </div>
            </div>
            <div class="card-body">
                <p class="text-muted mb-3">
                    Listado de solicitudes de pago por per&iacute;odo (equivalente Anita <code>l-solpagomae</code>).
                    Filtr&aacute; por empresa, estado, tratamiento y sector. El filtro de estado usa el c&oacute;digo ERP
                    (Autorizada, Suspendida, etc.). El tipo de solicitud por defecto es <strong>Todas</strong>
                    (si eleg&iacute;s &laquo;Sin SP autom&aacute;ticas&raquo; se excluyen madres de plan y puede parecer que
                    &laquo;no trae nada&raquo;). Opcionalmente concili&aacute; contra el mayor Anita las SP
                    <strong>pagadas desde anitaERP</strong> (IE de caja vinculado).
                </p>

                <form method="get" action="{{ route('informe_solicitudpago') }}" id="form-informe-solicitudpago"
                      class="form-horizontal form--label-right mb-3">
                    <input type="hidden" name="consultar" value="1">

                    <div class="form-row align-items-start">
                        <div class="col-lg-6">
                            @include('includes.form-empresa-asignada', [
                                'empresa_query' => $empresa_query,
                                'empresa_id' => $filtros['empresa_id'] ?? null,
                                'required' => false,
                                'permite_vacio' => true,
                                'opcion_vacia' => '— Elegir empresa —',
                                'col_label' => 'col-lg-4',
                                'col_input' => 'col-lg-8',
                            ])
                        </div>
                        <div class="col-lg-3 pt-2">
                            <div class="custom-control custom-checkbox">
                                <input type="checkbox" class="custom-control-input" name="empresa_todas" id="empresa_todas" value="1" {{ $esTodas ? 'checked' : '' }}>
                                <label class="custom-control-label" for="empresa_todas">Todas mis empresas</label>
                            </div>
                        </div>
                    </div>

                    <div class="form-group row mb-2">
                        <label for="fecha_desde" class="col-lg-2 col-form-label text-right">Desde fecha</label>
                        <div class="col-lg-2">
                            <input type="date" name="fecha_desde" id="fecha_desde" class="form-control form-control-sm"
                                   value="{{ $filtros['fecha_desde'] ?? '' }}" required>
                        </div>
                        <label for="fecha_hasta" class="col-lg-2 col-form-label text-right">Hasta fecha</label>
                        <div class="col-lg-2">
                            <input type="date" name="fecha_hasta" id="fecha_hasta" class="form-control form-control-sm"
                                   value="{{ $filtros['fecha_hasta'] ?? '' }}" required>
                        </div>
                    </div>

                    <div class="form-group row mb-2">
                        <label for="estado" class="col-lg-2 col-form-label text-right">Estado</label>
                        <div class="col-lg-3">
                            <select name="estado" id="estado" class="form-control form-control-sm">
                                <option value="TODOS" {{ ($filtros['estado'] ?? 'TODOS') === 'TODOS' ? 'selected' : '' }}>Todos</option>
                                @foreach ($estados as $op)
                                    <option value="{{ $op['valor'] }}" {{ ($filtros['estado'] ?? '') === $op['valor'] ? 'selected' : '' }}>
                                        {{ $op['nombre'] }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <label for="filtro_tratamiento" class="col-lg-2 col-form-label text-right">Filtra sol. tipo</label>
                        <div class="col-lg-3">
                            <select name="filtro_tratamiento" id="filtro_tratamiento" class="form-control form-control-sm">
                                @foreach ($tratamientos_filtro as $op)
                                    <option value="{{ $op['valor'] }}" {{ ($filtros['filtro_tratamiento'] ?? '') === $op['valor'] ? 'selected' : '' }}>
                                        {{ $op['nombre'] }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                    </div>

                    <div class="form-group row mb-2">
                        <label for="sector_desde" class="col-lg-2 col-form-label text-right">Desde sector</label>
                        <div class="col-lg-3">
                            <select name="sector_desde" id="sector_desde" class="form-control form-control-sm">
                                <option value="">— Todos —</option>
                                @foreach ($sectores as $sector)
                                    <option value="{{ $sector->codigo }}" {{ (string) ($filtros['sector_desde'] ?? '') === (string) $sector->codigo ? 'selected' : '' }}>
                                        {{ $sector->codigo }} — {{ $sector->nombre }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <label for="sector_hasta" class="col-lg-2 col-form-label text-right">Hasta sector</label>
                        <div class="col-lg-3">
                            <select name="sector_hasta" id="sector_hasta" class="form-control form-control-sm">
                                <option value="">— Todos —</option>
                                @foreach ($sectores as $sector)
                                    <option value="{{ $sector->codigo }}" {{ (string) ($filtros['sector_hasta'] ?? '') === (string) $sector->codigo ? 'selected' : '' }}>
                                        {{ $sector->codigo }} — {{ $sector->nombre }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                    </div>

                    <div class="form-group row mb-3">
                        <div class="col-lg-2"></div>
                        <div class="col-lg-8">
                            <div class="custom-control custom-checkbox">
                                <input type="checkbox" class="custom-control-input" name="incluir_conciliacion_mayor"
                                       id="incluir_conciliacion_mayor" value="1" {{ $incluirConcil ? 'checked' : '' }}>
                                <label class="custom-control-label" for="incluir_conciliacion_mayor">
                                    Conciliar mayor Anita (solo SP pagadas por anitaERP / IE de caja)
                                </label>
                            </div>
                        </div>
                    </div>

                    <div class="form-group row mb-0">
                        <div class="col-lg-2"></div>
                        <div class="col-lg-10">
                            <button type="submit" class="btn btn-primary btn-sm">
                                <i class="fa fa-search"></i> Consultar
                            </button>
                            <a href="{{ route('informe_solicitudpago') }}" class="btn btn-outline-secondary btn-sm">
                                <i class="fa fa-eraser"></i> Limpiar
                            </a>
                        </div>
                    </div>
                </form>

                @if ($consultado && $datas)
                    <div class="d-flex flex-wrap align-items-center mb-2">
                        <span class="badge badge-info mr-1">Registros: {{ $totales['registros'] ?? 0 }}</span>
                        <span class="badge badge-secondary mr-1">Importe: {{ number_format($totales['monto'] ?? 0, 2, ',', '.') }}</span>
                        @if ($incluirConcil)
                            <span class="badge badge-success mr-1">Concil. OK (p&aacute;g.): {{ $totales['conciliadas_ok'] ?? 0 }}</span>
                            <span class="badge badge-danger mr-1">Concil. DIF (p&aacute;g.): {{ $totales['conciliadas_dif'] ?? 0 }}</span>
                        @endif
                    </div>

                    <div class="mb-3">
                        <a href="{{ route('listar_informe_solicitudpago', ['formato' => 'PDF']) }}{{ $exportQuery ? '?'.$exportQuery : '' }}" class="btn btn-app bg-danger">
                            <i class="fas fa-file-pdf"></i> Pdf
                        </a>
                        <a href="{{ route('listar_informe_solicitudpago', ['formato' => 'EXCEL']) }}{{ $exportQuery ? '?'.$exportQuery : '' }}" class="btn btn-app bg-success">
                            <i class="fas fa-file-excel"></i> Excel
                        </a>
                        <a href="{{ route('listar_informe_solicitudpago', ['formato' => 'CSV']) }}{{ $exportQuery ? '?'.$exportQuery : '' }}" class="btn btn-app bg-warning">
                            <i class="fas fa-file-csv"></i> Csv
                        </a>
                    </div>

                    @php
                        $logosVista = \App\Support\Configuracion\EmpresaLogoArchivo::logosCabeceraDesdeColeccion(collect($datas->items()));
                    @endphp
                    @if (count($logosVista))
                        <div class="border-bottom pb-2 mb-3 d-flex flex-wrap align-items-center">
                            @foreach ($logosVista as $logo)
                                <img src="{{ $logo['uri'] }}" alt="{{ $logo['nombre'] }}" class="mr-2 mb-1" style="max-height: 48px; max-width: 140px;">
                            @endforeach
                            <div class="ml-auto text-muted small">{{ $subtitulo }}</div>
                        </div>
                    @endif

                    <style>
                        #tabla-paginada thead tr { background-color: #85C1E9; color: #17202A; }
                        #tabla-paginada thead th { font-weight: 600; border-color: #7fb3d5; white-space: nowrap; }
                        #tabla-paginada .num { text-align: right; }
                        #tabla-paginada td { font-size: 12px; vertical-align: middle; }
                    </style>
                    <div class="table-responsive">
                        @include('solicitudpago.informe_solicitudpago.partials.tabla_datos', [
                            'filas' => $datas,
                            'muestra_cuota' => $muestra_cuota,
                            'incluir_conciliacion' => $incluirConcil,
                            'puede_ver_sp' => $puedeVerSp,
                            'puede_ver_proveedor' => $puedeVerProveedor,
                            'para_export' => false,
                        ])
                    </div>

                    <div class="mt-2">
                        {{ $datas->appends($filtrosQuery ?? [])->links() }}
                    </div>
                @elseif ($consultado)
                    <div class="alert alert-light border">Sin resultados para los filtros indicados.</div>
                @else
                    <div class="alert alert-light border">Configur&aacute; los filtros y presion&aacute; <strong>Consultar</strong>.</div>
                @endif
            </div>
        </div>
    </div>
</div>
@endsection
