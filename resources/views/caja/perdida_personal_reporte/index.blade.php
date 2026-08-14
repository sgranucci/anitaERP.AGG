@extends("theme.$theme.layout")

@section('titulo')
    Reporte p&eacute;rdidas de empleados
@endsection

@section('contenido')
<div class="row">
    <div class="col-lg-12">
        @include('includes.form-error')
        @include('includes.mensaje')
        <div class="card card-info">
            <div class="card-header">
                <h3 class="card-title">Reporte de p&eacute;rdidas de empleados</h3>
                <div class="card-tools">
                    <a href="{{ route('perdida_personal') }}" class="btn btn-outline-info btn-sm">
                        <i class="fa fa-reply-all"></i> Volver al ABM
                    </a>
                </div>
            </div>
            <div class="card-body">
                <form method="get" action="{{ route('perdida_personal_reporte') }}" class="mb-3">
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
                            <input type="date" name="fecha_desde" class="form-control form-control-sm"
                                   value="{{ $filtros['fecha_desde'] }}" required>
                        </div>
                        <div class="form-group col-md-2">
                            <label class="small mb-1">Hasta</label>
                            <input type="date" name="fecha_hasta" class="form-control form-control-sm"
                                   value="{{ $filtros['fecha_hasta'] }}" required>
                        </div>
                        <div class="form-group col-md-3">
                            <label class="small mb-1">Concepto</label>
                            <select name="concepto_perdida_id" class="form-control form-control-sm">
                                <option value="0">Todos los faltantes</option>
                                @foreach ($conceptos as $c)
                                    <option value="{{ $c->id }}" @selected((int)($filtros['concepto_perdida_id'] ?? 0) === (int)$c->id)>
                                        {{ $c->codigo }} — {{ $c->nombre }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="form-group col-md-2">
                            <label class="small mb-1">Orden</label>
                            <select name="orden" class="form-control form-control-sm">
                                <option value="legajo" @selected(($filtros['orden'] ?? '') === 'legajo')>Por legajo</option>
                                <option value="alfabetico" @selected(($filtros['orden'] ?? '') === 'alfabetico')>Alfab&eacute;tico</option>
                            </select>
                        </div>
                        <div class="form-group col-md-2">
                            <label class="small mb-1">Empleados</label>
                            <select name="filtro_empleado" class="form-control form-control-sm">
                                <option value="activos" @selected(($filtros['filtro_empleado'] ?? '') === 'activos')>Activos</option>
                                <option value="bajas" @selected(($filtros['filtro_empleado'] ?? '') === 'bajas')>Bajas</option>
                                <option value="todos" @selected(($filtros['filtro_empleado'] ?? '') === 'todos')>Sin filtro</option>
                            </select>
                        </div>
                        <div class="form-group col-md-2">
                            <label class="small mb-1">Detalle</label>
                            <select name="modo" class="form-control form-control-sm">
                                <option value="movimientos" @selected(($filtros['modo'] ?? '') === 'movimientos')>Movimientos</option>
                                <option value="totales" @selected(($filtros['modo'] ?? '') === 'totales')>Totales x empleado</option>
                            </select>
                        </div>
                        <div class="form-group col-md-2">
                            <label class="small mb-1">Legajo desde</label>
                            <input type="number" name="legajo_desde" class="form-control form-control-sm"
                                   value="{{ $filtros['legajo_desde'] }}">
                        </div>
                        <div class="form-group col-md-2">
                            <label class="small mb-1">Legajo hasta</label>
                            <input type="number" name="legajo_hasta" class="form-control form-control-sm"
                                   value="{{ $filtros['legajo_hasta'] }}">
                        </div>
                        <div class="form-group col-md-2 d-flex align-items-end">
                            <button type="submit" class="btn btn-primary btn-sm btn-block">
                                <i class="fa fa-search"></i> Consultar
                            </button>
                        </div>
                    </div>
                </form>

                @if ($consultado && $resultado)
                    <div class="mb-2 d-flex flex-wrap justify-content-between align-items-center">
                        <div class="small text-muted">
                            {{ $resultado['subtitulo'] }} ·
                            {{ $resultado['total_empleados'] }} empleado(s) ·
                            {{ $resultado['total_registros'] }} movimiento(s) ·
                            Total $ {{ number_format($resultado['total_importe'], 2, ',', '.') }}
                        </div>
                        @include('includes.exportar-tabla-queryparams', [
                            'ruta' => 'listar_perdida_personal_reporte',
                            'queryparams' => $filtrosQuery,
                        ])
                    </div>

                    @include('caja.perdida_personal_reporte.partials.tabla_datos', [
                        'filas' => $filasPaginadas,
                        'puede_ver_perdida' => $puede_ver_perdida,
                        'puede_ver_empleado' => $puede_ver_empleado,
                    ])

                    <div class="mt-2">
                        {{ $filasPaginadas->links() }}
                    </div>
                @endif
            </div>
        </div>
    </div>
</div>
@endsection
