@extends("theme.$theme.layout")
@section('titulo')
    Propuesta de pagos
@endsection

@section('contenido')
@php
    use App\Support\Compras\PropuestaPagoListadoFiltros;
@endphp
<div class="row">
    <div class="col-lg-12">
        @include('includes.mensaje')
        <div class="card card-info">
            <div class="card-header">
                <h3 class="card-title">Propuestas de pagos</h3>
                <div class="card-tools">
                    @include('includes.compras.boton-manual-propuesta-pago')
                    @if (can('editar-configuracion-propuesta-pago', false))
                        <a href="{{ route('configuracion_propuesta_pago') }}" class="btn btn-outline-secondary btn-sm">
                            <i class="fa fa-cogs"></i> Config premium/light
                        </a>
                    @endif
                    <a href="{{ route('tesoreria_cockpit') }}" class="btn btn-outline-secondary btn-sm">
                        <i class="fa fa-tachometer-alt"></i> Cockpit
                    </a>
                    <a href="{{ route('cash_position') }}" class="btn btn-outline-secondary btn-sm">
                        <i class="fa fa-chart-line"></i> Cash
                    </a>
                    @if (can('crear-propuesta-pago', false))
                        <a href="{{ route('crear_propuesta_pago') }}" class="btn btn-outline-secondary btn-sm">
                            <i class="fa fa-fw fa-plus-circle"></i> Nueva propuesta
                        </a>
                    @endif
                </div>
            </div>
            <div class="card-body">
                @include('includes.exportar-tabla-queryparams', [
                    'ruta' => 'listar_propuesta_pago',
                    'queryparams' => $filtrosQuery ?? [],
                ])
                <form method="get" action="{{ route('propuesta_pago') }}" class="mb-3">
                    <div class="form-row">
                        <div class="form-group col-md-2">
                            <label class="small">Empresa</label>
                            <select name="empresa_id" class="form-control form-control-sm">
                                <option value="">Todas</option>
                                @foreach($empresa_query as $e)
                                    <option value="{{ $e->id }}" @selected((int)($filtros['empresa_id'] ?? 0) === (int)$e->id)>{{ $e->nombre }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="form-group col-md-2">
                            <label class="small">Estado</label>
                            <select name="estado" class="form-control form-control-sm">
                                <option value="">Todos</option>
                                @foreach(\App\Models\Compras\PropuestaPago::$enumEstado as $est)
                                    <option value="{{ $est['valor'] }}" @selected(($filtros['estado'] ?? '') === $est['valor'])>{{ $est['nombre'] }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="form-group col-md-2">
                            <label class="small">Desde</label>
                            <input type="date" name="fecha_desde" class="form-control form-control-sm" value="{{ $filtros['fecha_desde'] ?? '' }}">
                        </div>
                        <div class="form-group col-md-2">
                            <label class="small">Hasta</label>
                            <input type="date" name="fecha_hasta" class="form-control form-control-sm" value="{{ $filtros['fecha_hasta'] ?? '' }}">
                        </div>
                        <div class="form-group col-md-2">
                            <label class="small">Buscar</label>
                            <input type="text" name="filtro_valor" class="form-control form-control-sm" value="{{ $filtros['valor'] ?? '' }}">
                        </div>
                        <div class="form-group col-md-2 d-flex align-items-end">
                            <button type="submit" class="btn btn-primary btn-sm"><i class="fa fa-search"></i> Consultar</button>
                        </div>
                    </div>
                </form>
                <div class="table-responsive p-0">
                    <table class="table table-striped table-bordered table-hover" id="tabla-paginada">
                        <thead style="background:#85C1E9;color:#17202A;">
                            <tr>
                                <th>ID</th>
                                <th>Fecha</th>
                                <th>Empresa</th>
                                <th>Venc. desde/hasta</th>
                                <th class="text-right">Monto</th>
                                <th>Estado</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($coleccion as $fila)
                                <tr>
                                    <td>{{ $fila->id }}</td>
                                    <td>{{ optional($fila->fecha)->format('d/m/Y') }}</td>
                                    <td>{{ $fila->empresas->nombre ?? '' }}</td>
                                    <td>
                                        {{ optional($fila->fecha_vencimiento_desde)->format('d/m/Y') }}
                                        —
                                        {{ optional($fila->fecha_vencimiento_hasta)->format('d/m/Y') }}
                                    </td>
                                    <td class="text-right">{{ number_format((float)$fila->monto_total, 2, ',', '.') }}</td>
                                    <td>{{ $fila->estado }}</td>
                                    <td class="text-nowrap">
                                        @if (can('editar-propuesta-pago', false))
                                            <a href="{{ route('editar_propuesta_pago', $fila->id) }}" class="btn-accion-tabla tooltipsC" title="Editar">
                                                <i class="fa fa-edit"></i>
                                            </a>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="7" class="text-center text-muted">Sin propuestas</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
{{ $coleccion->appends($filtrosQuery ?? [])->links() }}
@endsection
