@extends("theme.$theme.layout")
@section('titulo')
    Saldos de vacaciones
@endsection

<?php use App\Support\Sueldos\EmpleadoEstados; ?>

@section('scripts')
<script src="{{ asset('assets/pages/scripts/reportes/empresas_checkboxes.js') }}"></script>
@endsection

@section('contenido')
@php
    $anioActual = (int) date('Y');
    $filasVista = collect($datas->items());
    $logosVista = \App\Support\Configuracion\EmpresaLogoArchivo::logosCabeceraDesdeColeccion($filasVista);
    $puedeVerEmpleado = can('editar-empleado-sueldos', false) || can('listar-empleado-sueldos', false);
    $empresasSelVac = $filtros['empresa_ids'] ?? (
        ($filtros['empresa_scope'] ?? 'una') === 'todas'
            ? ($empresa_query ?? collect())->pluck('id')->map(fn ($id) => (int) $id)->all()
            : array_filter([(int) ($filtros['empresa_id'] ?? 0)])
    );
@endphp
<div class="row">
    <div class="col-lg-12">
        @include('includes.mensaje')
        <div class="card card-info">
            <div class="card-header">
                <h3 class="card-title">Saldos de vacaciones</h3>
            </div>
            <div class="card-body">
                <p class="text-muted mb-3">
                    Saldo de d&iacute;as de vacaciones por empleado, calculado desde el ledger
                    (devengado por antig&uuml;edad LCT menos lo consumido/liquidado). Si un empleado
                    no tiene movimientos, us&aacute; <strong>Recalcular saldos</strong> para devengar seg&uacute;n antig&uuml;edad.
                </p>

                <form method="get" action="{{ route('saldo_vacaciones_sueldos') }}" id="form-saldo-vacaciones" class="mb-3">
                    @include('includes.reportes.asignacion_empresas_checkboxes', [
                        'empresa_query' => $empresa_query,
                        'empresa_ids_seleccionados' => $empresasSelVac,
                        'consolidar_empresas' => $filtros['consolidar_empresas'] ?? true,
                        'id_prefix' => 'saldo_vac',
                        'col_label' => 'col-lg-2',
                        'col_body' => 'col-lg-10',
                    ])
                    <div class="form-row mt-2">
                        <div class="form-group col-lg-3 col-sm-6 mb-2">
                            <label for="filtro_estado" class="small mb-1">Estado</label>
                            <select name="filtro_estado" id="filtro_estado" class="form-control form-control-sm">
                                <option value="{{ EmpleadoEstados::ACTIVO }}" {{ ($filtros['estado'] ?? '') === EmpleadoEstados::ACTIVO ? 'selected' : '' }}>Activo</option>
                                <option value="{{ EmpleadoEstados::PROVISORIO }}" {{ ($filtros['estado'] ?? '') === EmpleadoEstados::PROVISORIO ? 'selected' : '' }}>Alta provisoria</option>
                                <option value="{{ EmpleadoEstados::BAJA }}" {{ ($filtros['estado'] ?? '') === EmpleadoEstados::BAJA ? 'selected' : '' }}>Baja</option>
                                <option value="TODOS" {{ ($filtros['estado'] ?? '') === 'TODOS' ? 'selected' : '' }}>Todos</option>
                            </select>
                        </div>
                        <div class="form-group col-lg-2 col-sm-6 mb-2">
                            <label for="anio" class="small mb-1">Período (año)</label>
                            <input type="number" name="anio" id="anio" class="form-control form-control-sm" min="1990" max="{{ $anioActual + 1 }}"
                                   value="{{ $filtros['anio'] ?? '' }}" placeholder="Todos">
                        </div>
                        <div class="form-group col-lg-4 col-sm-8 mb-2">
                            <label for="filtro_valor" class="small mb-1">Empleado (legajo / nombre)</label>
                            <input type="text" name="filtro_valor" id="filtro_valor" class="form-control form-control-sm"
                                   value="{{ $filtros['valor'] ?? '' }}" placeholder="Texto o legajo" autocomplete="off">
                        </div>
                        <div class="form-group col-lg-3 col-sm-4 mb-2 d-flex align-items-end">
                            <div class="custom-control custom-checkbox">
                                <input type="checkbox" class="custom-control-input" name="solo_con_saldo" id="solo_con_saldo" value="1" {{ !empty($filtros['solo_con_saldo']) ? 'checked' : '' }}>
                                <label class="custom-control-label" for="solo_con_saldo">Solo con saldo &gt; 0</label>
                            </div>
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="col-lg-12">
                            <button type="submit" class="btn btn-primary btn-sm">
                                <i class="fa fa-search"></i> Consultar
                            </button>
                            <a href="{{ route('saldo_vacaciones_sueldos') }}" class="btn btn-outline-secondary btn-sm">
                                <i class="fa fa-eraser"></i> Limpiar
                            </a>
                        </div>
                    </div>
                </form>

                <div class="d-flex flex-wrap align-items-center mb-2">
                    <span class="badge badge-info mr-1">Empleados: {{ $totales['empleados'] ?? 0 }}</span>
                    <span class="badge badge-secondary mr-1">Devengado: {{ number_format($totales['devengado'] ?? 0, 2, ',', '.') }}</span>
                    <span class="badge badge-secondary mr-1">Consumido: {{ number_format($totales['consumido'] ?? 0, 2, ',', '.') }}</span>
                    <span class="badge badge-success mr-3">Saldo: {{ number_format($totales['saldo'] ?? 0, 2, ',', '.') }}</span>

                    @if (can('listar-saldo-vacaciones-sueldos', false))
                        <form method="post" action="{{ route('recalcular_saldo_vacaciones_sueldos', $filtrosQuery ?? []) }}"
                              id="form-recalcular-saldos" class="d-inline ml-auto"
                              onsubmit="return confirm('¿Recalcular el devengamiento de vacaciones (LCT) para los empleados del filtro actual? Puede tardar según la cantidad.');">
                            @csrf
                            <button type="submit" class="btn btn-outline-primary btn-sm">
                                <i class="fa fa-sync"></i> Recalcular saldos
                            </button>
                        </form>
                    @endif
                </div>

                <div class="mb-3">
                    @php
                        $exportQuery = http_build_query($filtrosQuery ?? []);
                    @endphp
                    <a href="{{ route('listar_saldo_vacaciones_sueldos', ['formato' => 'PDF']) }}{{ $exportQuery ? '?'.$exportQuery : '' }}" class="btn btn-app bg-danger">
                        <i class="fas fa-file-pdf"></i> Pdf
                    </a>
                    <a href="{{ route('listar_saldo_vacaciones_sueldos', ['formato' => 'EXCEL']) }}{{ $exportQuery ? '?'.$exportQuery : '' }}" class="btn btn-app bg-success">
                        <i class="fas fa-file-excel"></i> Excel
                    </a>
                    <a href="{{ route('listar_saldo_vacaciones_sueldos', ['formato' => 'CSV']) }}{{ $exportQuery ? '?'.$exportQuery : '' }}" class="btn btn-app bg-warning">
                        <i class="fas fa-file-csv"></i> Csv
                    </a>
                </div>

                @if (count($logosVista))
                    <div class="border-bottom pb-2 mb-3 d-flex flex-wrap align-items-center">
                        @foreach ($logosVista as $logo)
                            <img src="{{ $logo['uri'] }}" alt="{{ $logo['nombre'] }}" class="mr-2 mb-1" style="max-height: 48px; max-width: 140px;">
                        @endforeach
                    </div>
                @endif

                <style>
                    #tabla-saldo-vacaciones thead tr { background-color: #85C1E9; color: #17202A; }
                    #tabla-saldo-vacaciones thead th { font-weight: 600; border-color: #7fb3d5; }
                    #tabla-saldo-vacaciones .num { text-align: right; }
                </style>
                <div class="table-responsive">
                    @include('sueldos.saldo_vacaciones.partials.tabla_datos', [
                        'datas' => $datas,
                        'puede_ver_empleado' => $puedeVerEmpleado,
                    ])
                </div>
            </div>
        </div>
        {{ $datas->appends($filtrosQuery ?? [])->links() }}
    </div>
</div>
@endsection
