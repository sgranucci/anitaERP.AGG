@extends("theme.$theme.layout")

@section('titulo')
    Cta. cte. fallos
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
                            <select name="empresa_id" class="form-control form-control-sm" required>
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
                        <div class="form-group col-md-1">
                            <label class="small mb-1">Leg. dsd</label>
                            <input type="number" name="legajo_desde" class="form-control form-control-sm" value="{{ $filtros['legajo_desde'] }}">
                        </div>
                        <div class="form-group col-md-1">
                            <label class="small mb-1">Leg. hst</label>
                            <input type="number" name="legajo_hasta" class="form-control form-control-sm" value="{{ $filtros['legajo_hasta'] }}">
                        </div>
                        <div class="form-group col-md-1 d-flex align-items-end">
                            <button class="btn btn-primary btn-sm btn-block" type="submit"><i class="fa fa-search"></i></button>
                        </div>
                    </div>
                </form>

                @if ($consultado && $resultado)
                    <div class="mb-2 d-flex justify-content-between flex-wrap">
                        <div class="small text-muted">
                            {{ $resultado['subtitulo'] }} ·
                            {{ $resultado['total_empleados'] }} emp. ·
                            Debe $ {{ number_format($resultado['total_debe'], 2, ',', '.') }} ·
                            Haber $ {{ number_format($resultado['total_haber'], 2, ',', '.') }} ·
                            Saldo $ {{ number_format($resultado['total_saldo'], 2, ',', '.') }}
                        </div>
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
@endsection
