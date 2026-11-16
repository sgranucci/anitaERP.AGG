@extends("theme.$theme.layout")
@section('titulo')
    Cashflow importaciones
@endsection

@section('contenido')
<div class="row">
    <div class="col-lg-12">
        <div class="card card-info">
            <div class="card-header">
                <h3 class="card-title">Cashflow / simulador / nomenclatura</h3>
                <div class="card-tools">
                    <a href="{{ route('importacion') }}" class="btn btn-outline-info btn-sm"><i class="fa fa-reply-all"></i> Volver</a>
                </div>
            </div>
            <div class="card-body">
                <form method="get" class="form-inline mb-3" data-enter-campos="1">
                    @include('includes.listado.filtro_empresa_asignada_inline', [
                        'empresa_query' => $empresa_query,
                        'empresa_id' => $filtros['empresa_id'] ?? null,
                        'permite_todas' => true,
                    ])
                    <div class="form-group mx-2">
                        <label class="mr-1">Desde</label>
                        <input type="date" name="desde" class="form-control form-control-sm" value="{{ $filtros['desde'] ?? '' }}">
                    </div>
                    <div class="form-group mx-2">
                        <label class="mr-1">Hasta</label>
                        <input type="date" name="hasta" class="form-control form-control-sm" value="{{ $filtros['hasta'] ?? '' }}">
                    </div>
                    <button type="submit" class="btn btn-primary btn-sm">Consultar</button>
                </form>

                <h5>Proyección de gastos</h5>
                <table class="table table-sm table-bordered" id="tabla-paginada">
                    <thead style="background:#85C1E9;color:#17202A;">
                        <tr>
                            <th>Fecha</th>
                            <th>Carpeta</th>
                            <th>Concepto</th>
                            <th>Naturaleza</th>
                            <th class="text-right">Importe</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($filas as $fila)
                            <tr>
                                <td>{{ $fila->fecha }}</td>
                                <td>
                                    <a class="text-primary" href="{{ route('editar_importacion', $fila->importacion_id) }}">{{ $fila->codigo }}</a>
                                </td>
                                <td>{{ $fila->concepto }}</td>
                                <td>{{ $fila->naturaleza }}</td>
                                <td class="text-right">{{ number_format((float)$fila->importe, 2, ',', '.') }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="text-center text-muted">Sin proyecciones en el rango.</td></tr>
                        @endforelse
                    </tbody>
                </table>

                <hr>
                <h5>Simulador landed cost (API JSON)</h5>
                <p class="small text-muted">POST/GET <code>{{ url('compras/importacion/simular') }}</code> con <code>fob</code>, <code>gastos[]</code>, <code>partidas[]</code>.</p>
                <pre class="bg-light p-2 small">{{ json_encode($anitaBridge, JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE) }}</pre>

                <h5 class="mt-3">Nomenclatura arancelaria</h5>
                <table class="table table-sm table-bordered">
                    <thead style="background:#85C1E9;color:#17202A;">
                        <tr><th>Código</th><th>Descripción</th><th>Alic. derecho</th></tr>
                    </thead>
                    <tbody>
                        @forelse ($nomenclaturas as $nom)
                            <tr>
                                <td>{{ $nom->codigo }}</td>
                                <td>{{ $nom->descripcion }}</td>
                                <td>{{ $nom->alicuota_derecho }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="3" class="text-muted text-center">Sin nomenclaturas cargadas (ABM futuro / seed por cliente).</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection
