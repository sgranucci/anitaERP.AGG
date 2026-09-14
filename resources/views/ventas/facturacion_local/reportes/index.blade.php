@extends("theme.$theme.layout")
@section('titulo')
    Reportes Facturación Local
@endsection
@section('contenido')
<div class="row">
    <div class="col-lg-12">
        @include('includes.mensaje')
        <div class="card card-info">
            <div class="card-header">
                <h3 class="card-title">Reportes Local</h3>
            </div>
            <form method="get" class="p-3">
                <input type="hidden" name="consultar" value="1">
                <div class="form-row align-items-end">
                    <div class="form-group col-md-3">
                        <label>Local</label>
                        <select name="local_id" class="form-control">
                            <option value="0">Todos</option>
                            @foreach ($locales as $loc)
                                <option value="{{ $loc->id }}" @if ($localId === (int) $loc->id) selected @endif>
                                    {{ $loc->codigo }} — {{ $loc->nombre }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="form-group col-md-2">
                        <label>Desde</label>
                        <input type="date" name="desde" class="form-control" value="{{ $desde }}">
                    </div>
                    <div class="form-group col-md-2">
                        <label>Hasta</label>
                        <input type="date" name="hasta" class="form-control" value="{{ $hasta }}">
                    </div>
                    <div class="form-group col-md-3">
                        <button class="btn btn-primary">Consultar</button>
                        @if ($consultar)
                            <a class="btn btn-outline-secondary" href="{{ route('listar_facturacion_local', ['formato' => 'PDF', 'local_id' => $localId, 'desde' => $desde, 'hasta' => $hasta]) }}">PDF</a>
                        @endif
                    </div>
                </div>
            </form>
            @if ($consultar && $emisiones)
            <div class="card-body table-responsive p-0">
                <table class="table table-striped table-bordered" id="tabla-paginada">
                    <thead style="background:#85C1E9;color:#17202A;">
                        <tr>
                            <th>ID</th>
                            <th>Fecha</th>
                            <th>Local</th>
                            <th>Venta</th>
                            <th>NC</th>
                            <th>Total</th>
                            <th>CAE</th>
                            <th>Regalo</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($emisiones as $e)
                        <tr>
                            <td>{{ $e->id }}</td>
                            <td>{{ optional($e->created_at)->format('d/m/Y H:i') }}</td>
                            <td>{{ $e->localVenta->codigo ?? '' }}</td>
                            <td>{{ $e->venta->codigo ?? '' }}</td>
                            <td>{{ $e->ventaNc->codigo ?? '' }}</td>
                            <td class="text-right">{{ number_format((float) ($e->venta->total ?? 0), 2, ',', '.') }}</td>
                            <td>{{ $e->venta->cae ?? '' }}</td>
                            <td>{{ $e->es_ticket_regalo ? 'Sí' : '' }}</td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="card-footer">{{ $emisiones->links() }}</div>
            @endif

            @if ($consultar && $vales && $vales->isNotEmpty())
            <div class="card-body">
                <h5>Vales en el período</h5>
                <table class="table table-sm table-bordered">
                    <thead style="background:#85C1E9;color:#17202A;">
                        <tr>
                            <th>ID</th>
                            <th>Cliente/Doc</th>
                            <th>Original</th>
                            <th>Saldo</th>
                            <th>Estado</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($vales as $v)
                        <tr>
                            <td>{{ $v->id }}</td>
                            <td>{{ $v->nombre ?: ($v->nro_documento ?: $v->cliente_id) }}</td>
                            <td class="text-right">{{ number_format($v->importe_original, 2, ',', '.') }}</td>
                            <td class="text-right">{{ number_format($v->saldo, 2, ',', '.') }}</td>
                            <td>{{ $v->estado }}</td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @endif
        </div>
    </div>
</div>
@endsection
