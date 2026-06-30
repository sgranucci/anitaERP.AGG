@extends("theme.$theme.layout")
@section('titulo')
    Movimientos por bien de uso
@endsection

@section('contenido')
<div class="row">
    <div class="col-lg-12">
        @include('includes.mensaje')
        <div class="card card-info">
            <div class="card-header">
                <h3 class="card-title">Reporte de movimientos por bien de uso</h3>
                <div class="card-tools">
                    <a href="{{ route('reporte_movimientos_bien_uso') }}" class="btn btn-outline-secondary btn-sm">
                        <i class="fa fa-eraser"></i> Limpiar
                    </a>
                </div>
            </div>
            <form method="get" action="{{ route('reporte_movimientos_bien_uso') }}" class="mb-0">
                <div class="card-body pb-2">
                    <p class="text-muted small mb-3">
                        Lista asignaciones y desasignaciones registradas en <code>articulo_movimiento</code> con bien de uso.
                        Cantidad positiva = asignaci&oacute;n al bien; negativa = desasignaci&oacute;n (movimiento inverso).
                    </p>
                    <div class="form-group row">
                        <label for="bien_uso_id" class="col-lg-2 control-label">Bien de uso</label>
                        <div class="col-lg-4">
                            <select name="bien_uso_id" id="bien_uso_id" class="form-control">
                                <option value="">— Todos —</option>
                                @foreach ($bienesUso as $bien)
                                    <option value="{{ $bien->id }}" @selected((int) ($filtros['bien_uso_id'] ?? 0) === (int) $bien->id)>
                                        {{ $bien->etiqueta() }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <label for="efecto" class="col-lg-2 control-label">Efecto</label>
                        <div class="col-lg-3">
                            <select name="efecto" id="efecto" class="form-control">
                                @foreach ($efectos as $valor => $label)
                                    <option value="{{ $valor }}" @selected(($filtros['efecto'] ?? '') === $valor)>{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    <div class="form-group row">
                        <label for="fecha_desde" class="col-lg-2 control-label">Desde fecha</label>
                        <div class="col-lg-3">
                            <input type="date" name="fecha_desde" id="fecha_desde" class="form-control"
                                value="{{ $filtros['fecha_desde'] ?? '' }}">
                        </div>
                        <label for="fecha_hasta" class="col-lg-2 control-label">Hasta fecha</label>
                        <div class="col-lg-3">
                            <input type="date" name="fecha_hasta" id="fecha_hasta" class="form-control"
                                value="{{ $filtros['fecha_hasta'] ?? '' }}">
                        </div>
                    </div>
                    <div class="form-group row mb-0">
                        <div class="col-lg-2"></div>
                        <div class="col-lg-10">
                            <input type="hidden" name="consultar" value="1">
                            <button type="submit" class="btn btn-primary btn-sm">
                                <i class="fa fa-search"></i> Consultar
                            </button>
                        </div>
                    </div>
                </div>
            </form>

            @if ($consultado)
                <div class="card-body pt-0">
                    @if ($totales)
                        <div class="mb-2">
                            <span class="badge badge-info mr-1">Registros: {{ $totales['total_registros'] ?? 0 }}</span>
                            <span class="badge badge-success mr-1">Asignado: {{ number_format($totales['total_asignaciones'] ?? 0, 4, ',', '.') }}</span>
                            <span class="badge badge-warning">Desasignado: {{ number_format($totales['total_desasignaciones'] ?? 0, 4, ',', '.') }}</span>
                        </div>
                    @endif

                    @include('includes.exportar-tabla-queryparams', [
                        'ruta' => 'listar_reporte_movimientos_bien_uso',
                        'queryparams' => $filtrosQuery ?? [],
                    ])

                    <div class="table-responsive">
                        @include('stock.bien_uso_movimiento_reporte.partials.tabla_datos', [
                            'filas' => $filas,
                            'puede_ver_articulo' => $puede_ver_articulo ?? false,
                            'puede_ver_bien_uso' => $puede_ver_bien_uso ?? false,
                        ])
                    </div>

                    @if ($filas instanceof \Illuminate\Pagination\LengthAwarePaginator)
                        <div class="mt-2">
                            Mostrando {{ $filas->firstItem() }}–{{ $filas->lastItem() }} de {{ $filas->total() }}
                            {{ $filas->appends($filtrosQuery ?? [])->links() }}
                        </div>
                    @endif
                </div>
            @endif
        </div>
    </div>
</div>
@endsection
