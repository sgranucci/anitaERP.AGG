@extends("theme.$theme.layout")

@section('titulo')
    Cta. cte. fallos
@endsection

@section('scripts')
<script>
window.empleadoSueldosConsultaUrls = {
    buscar: @json(route('consulta_operativa_empleado_sueldos')),
    resolver: @json(route('resolver_operativo_empleado_sueldos'))
};
</script>
<script src="{{ asset('assets/pages/scripts/sueldos/empleado/consulta.js') }}?v={{ @filemtime(public_path('assets/pages/scripts/sueldos/empleado/consulta.js')) ?: time() }}"></script>
@endsection

@section('contenido')
<div class="row">
    <div class="col-lg-12">
        @include('includes.mensaje')
        <div class="card card-info">
            <div class="card-header">
                <h3 class="card-title">Cuenta corriente de fallos</h3>
                <div class="card-tools">
                    @if (can('listar-dtofallo-sueldos', false))
                        <a href="{{ route('consultar_dtofallo_sueldos') }}" class="btn btn-outline-info btn-sm">
                            <i class="fa fa-cogs"></i> Proceso dto. fallo
                        </a>
                    @endif
                </div>
            </div>
            <div class="card-body">
                <form method="get" action="{{ route('fallo_reporte_sueldos') }}" class="mb-3">
                    <input type="hidden" name="consultar" value="1">
                    <div class="form-row">
                        <div class="form-group col-md-3">
                            <label class="small mb-1">Empresa</label>
                            <select name="empresa_id" id="fallo_reporte_empresa_id" class="form-control form-control-sm" required>
                                <option value="">-- Elija --</option>
                                @foreach ($empresa_query as $emp)
                                    <option value="{{ $emp->id }}" @selected((int)($filtros['empresa_id'] ?? 0) === (int)$emp->id)>
                                        {{ $emp->nombre }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="form-group col-md-2">
                            <label class="small mb-1">Desde</label>
                            <input type="date" name="fecha_desde" class="form-control form-control-sm" value="{{ $filtros['fecha_desde'] }}" required>
                        </div>
                        <div class="form-group col-md-2">
                            <label class="small mb-1">Hasta</label>
                            <input type="date" name="fecha_hasta" class="form-control form-control-sm" value="{{ $filtros['fecha_hasta'] }}" required>
                        </div>
                        <div class="form-group col-md-2">
                            <label class="small mb-1">Detalle</label>
                            <select name="modo" class="form-control form-control-sm">
                                <option value="movimientos" @selected(($filtros['modo'] ?? '') === 'movimientos')>Movimientos</option>
                                <option value="totales" @selected(($filtros['modo'] ?? '') === 'totales')>Totales x empleado</option>
                            </select>
                        </div>
                        <div class="col-md-4 mb-3">
                            @include('sueldos.partials.campo_consulta_empleado_sueldos', [
                                'prefix' => 'fallo_reporte_legajo_desde',
                                'inputName' => 'legajo_desde',
                                'legajo' => $filtros['legajo_desde'],
                                'label' => 'Empleado desde',
                                'nextFocus' => '#fallo_reporte_legajo_hasta_legajo',
                            ])
                        </div>
                        <div class="col-md-4 mb-3">
                            @include('sueldos.partials.campo_consulta_empleado_sueldos', [
                                'prefix' => 'fallo_reporte_legajo_hasta',
                                'inputName' => 'legajo_hasta',
                                'legajo' => $filtros['legajo_hasta'],
                                'label' => 'Empleado hasta',
                                'nextFocus' => '#fallo_reporte_consultar',
                            ])
                        </div>
                        <div class="form-group col-md-3 d-flex align-items-end">
                            <button class="btn btn-primary btn-sm mr-2" id="fallo_reporte_consultar" type="submit">
                                <i class="fa fa-search"></i> Consultar
                            </button>
                            <a href="{{ route('fallo_reporte_sueldos') }}" class="btn btn-outline-secondary btn-sm"
                               title="Limpiar filtros">
                                <i class="fa fa-eraser"></i> Limpiar
                            </a>
                        </div>
                    </div>
                </form>

                @if ($consultado && $resultado)
                    <div class="small text-muted mb-2">
                        {{ $resultado['subtitulo'] }} ·
                        {{ $resultado['total_empleados'] }} emp. ·
                        Debe $ {{ number_format($resultado['total_debe'], 2, ',', '.') }} ·
                        Haber $ {{ number_format($resultado['total_haber'], 2, ',', '.') }} ·
                        Saldo $ {{ number_format($resultado['total_saldo'], 2, ',', '.') }}
                    </div>

                    <div class="mb-3">
                        @include('includes.exportar-tabla-queryparams', [
                            'ruta' => 'listar_fallo_reporte_sueldos',
                            'queryparams' => $filtrosQuery,
                        ])
                    </div>

                    @include('sueldos.fallo_reporte.partials.tabla_datos', ['filas' => $filasPaginadas])
                    <div class="mt-2">{{ $filasPaginadas->links() }}</div>
                @endif
            </div>
        </div>
    </div>
</div>
@include('includes.sueldos.modalconsultaempleado_sueldos')
@endsection
