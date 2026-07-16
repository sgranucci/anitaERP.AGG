@extends("theme.$theme.layout")
@section('titulo')
    Pago a proveedores
@endsection

@section('contenido')
<div class="row">
    <div class="col-lg-12">
        @include('includes.mensaje')
        <div class="card card-danger">
            <div class="card-header">
                <h3 class="card-title">Órdenes de pago</h3>
                <div class="card-tools">
                    @can('crear-pagoproveedor')
                        <a href="{{ route('crear_pagoproveedor') }}" class="btn btn-success btn-sm">
                            <i class="fa fa-plus"></i> Nuevo
                        </a>
                    @endcan
                </div>
            </div>
            <div class="card-body">
                <form id="form-filtros-pagoproveedor" method="get" action="{{ route('pagoproveedor') }}" class="mb-3">
                    <div class="form-row">
                        <div class="col-md-2">
                            <label>Empresa</label>
                            <select name="empresa_id" class="form-control form-control-sm">
                                <option value="">Todas</option>
                                @foreach($empresa_query as $e)
                                    <option value="{{ $e->id }}" @selected(($filtros['empresa_id'] ?? '') == $e->id)>{{ $e->nombre }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label>Nro.</label>
                            <input type="text" name="numero" class="form-control form-control-sm" value="{{ $filtros['numero'] ?? '' }}">
                        </div>
                        <div class="col-md-2">
                            <label>Estado</label>
                            <select name="estado" class="form-control form-control-sm">
                                <option value="">Todos</option>
                                @foreach(\App\Models\Compras\Pagoproveedor::$enumEstado as $est)
                                    <option value="{{ $est['valor'] }}" @selected(($filtros['estado'] ?? '') === $est['valor'])>{{ $est['nombre'] }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label>Desde</label>
                            <input type="date" name="fecha_desde" class="form-control form-control-sm" value="{{ $filtros['fecha_desde'] ?? '' }}">
                        </div>
                        <div class="col-md-2">
                            <label>Hasta</label>
                            <input type="date" name="fecha_hasta" class="form-control form-control-sm" value="{{ $filtros['fecha_hasta'] ?? '' }}">
                        </div>
                        <div class="col-md-2 d-flex align-items-end">
                            <button type="submit" class="btn btn-primary btn-sm">Consultar</button>
                        </div>
                    </div>
                </form>

                <div class="table-responsive">
                    <table id="tabla-paginada" class="table table-bordered table-striped table-sm">
                        <thead style="background-color:#85C1E9;color:#17202A;">
                            <tr>
                                <th>Fecha</th>
                                <th>OP</th>
                                <th>Empresa</th>
                                <th>Proveedor</th>
                                <th class="text-right">Monto</th>
                                <th>Estado</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($coleccion as $fila)
                                <tr>
                                    <td>{{ optional($fila->fecha)->format('d/m/Y') }}</td>
                                    <td>{{ $fila->etiquetaComprobante() }}</td>
                                    <td>{{ $fila->empresas->nombre ?? '' }}</td>
                                    <td>{{ $fila->proveedores->nombre ?? '' }}</td>
                                    <td class="text-right">{{ number_format((float)$fila->monto, 2, ',', '.') }} {{ $fila->monedas->abreviatura ?? '' }}</td>
                                    <td>{{ $fila->estado }}</td>
                                    <td class="text-nowrap">
                                        @can('editar-pagoproveedor')
                                            <a class="btn btn-info btn-xs" href="{{ route('editar_pagoproveedor', $fila->id) }}"><i class="fa fa-pencil"></i></a>
                                        @endcan
                                        <a class="btn btn-secondary btn-xs" target="_blank" rel="noopener" href="{{ route('imprimir_pagoproveedor', $fila->id) }}"><i class="fa fa-print"></i></a>
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="7" class="text-center text-muted">Sin órdenes de pago</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                {{ $coleccion->appends($filtrosQuery ?? [])->links() }}
            </div>
        </div>
    </div>
</div>
@endsection
