@extends("theme.$theme.layout")
@section('titulo')
    Consulta NPU — bajas
@endsection

@section('contenido')
<div class="row">
    <div class="col-lg-12">
        @include('includes.mensaje')
        <div class="card card-info">
            <div class="card-header">
                <h3 class="card-title">Consulta de n&uacute;meros de parte &uacute;nica (NPU)</h3>
                <div class="card-tools">
                    <a href="{{ route('reporte_baja_npu') }}" class="btn btn-outline-secondary btn-sm">
                        <i class="fa fa-eraser"></i> Limpiar
                    </a>
                </div>
            </div>
            <form method="get" action="{{ route('reporte_baja_npu') }}" class="mb-0">
                <div class="card-body pb-2">
                    <p class="text-muted small mb-3">
                        Lista NPUs activos o dados de baja (rotura / no funcional). Los dados de baja no pueden reutilizarse.
                    </p>
                    <div class="form-group row">
                        <label for="numeroparte" class="col-lg-2 control-label">NPU</label>
                        <div class="col-lg-3">
                            <input type="number" name="numeroparte" id="numeroparte" class="form-control" min="1"
                                value="{{ $filtros['numeroparte'] ?? '' }}" placeholder="N&uacute;mero exacto">
                        </div>
                        <label for="sku" class="col-lg-2 control-label">SKU art&iacute;culo</label>
                        <div class="col-lg-3">
                            <input type="text" name="sku" id="sku" class="form-control"
                                value="{{ $filtros['sku'] ?? '' }}" placeholder="Contiene…">
                        </div>
                    </div>
                    <div class="form-group row">
                        <label for="estado" class="col-lg-2 control-label">Estado</label>
                        <div class="col-lg-3">
                            <select name="estado" id="estado" class="form-control">
                                @foreach ($estados as $valor => $label)
                                    <option value="{{ $valor }}" @selected(($filtros['estado'] ?? 'B') === $valor)>{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                        <label for="fecha_desde" class="col-lg-2 control-label">Baja desde</label>
                        <div class="col-lg-2">
                            <input type="date" name="fecha_desde" id="fecha_desde" class="form-control"
                                value="{{ $filtros['fecha_desde'] ?? '' }}">
                        </div>
                        <label for="fecha_hasta" class="col-lg-1 control-label">hasta</label>
                        <div class="col-lg-2">
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
                            <span class="badge badge-danger mr-1">Baja: {{ $totales['total_baja'] ?? 0 }}</span>
                            <span class="badge badge-success">Activos: {{ $totales['total_activos'] ?? 0 }}</span>
                        </div>
                    @endif

                    @include('includes.exportar-tabla-queryparams', [
                        'ruta' => 'listar_reporte_baja_npu',
                        'queryparams' => $filtrosQuery ?? [],
                    ])

                    <div class="table-responsive">
                        @include('stock.parte_unica_baja_reporte.partials.tabla_datos', [
                            'filas' => $filas,
                            'puede_ver_articulo' => $puede_ver_articulo ?? false,
                            'puede_ver_movimiento' => $puede_ver_movimiento ?? false,
                        ])
                    </div>

                    @if ($filas instanceof \Illuminate\Pagination\LengthAwarePaginator)
                        <div class="mt-2">
                            Mostrando {{ $filas->firstItem() }}&ndash;{{ $filas->lastItem() }} de {{ $filas->total() }}
                            {{ $filas->appends($filtrosQuery ?? [])->links() }}
                        </div>
                    @endif
                </div>
            @endif
        </div>
    </div>
</div>
@endsection
