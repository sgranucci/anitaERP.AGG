@extends("theme.$theme.layout")
@section('titulo')
    Entregas de indumentaria
@endsection

@section('contenido')
@php $puedeVerEmpleado = can('actualizar-empleado-sueldos', false) || can('editar-empleado-sueldos', false); @endphp
<div class="row">
    <div class="col-lg-12">
        @include('includes.mensaje')
        <div class="card card-primary">
            <div class="card-header">
                <h3 class="card-title"><i class="fa fa-tshirt"></i> Reporte de entregas de indumentaria</h3>
                <div class="card-tools">
                    @include('includes.exportar-tabla-queryparams', ['ruta' => 'listar_entrega_prenda', 'queryparams' => $filtrosQuery ?? []])
                </div>
            </div>
            <div class="card-body">
                <form method="get" action="{{ route('entrega_prenda_reporte') }}" class="mb-3">
                    <input type="hidden" name="consultar" value="1">
                    <div class="form-row">
                        <div class="form-group col-md-2">
                            <label class="mb-1">Año</label>
                            <input type="number" name="anio" class="form-control form-control-sm" value="{{ $filtros['anio'] }}">
                        </div>
                        <div class="form-group col-md-2">
                            <label class="mb-1">Desde</label>
                            <input type="date" name="fecha_desde" class="form-control form-control-sm" value="{{ $filtros['fecha_desde'] }}">
                        </div>
                        <div class="form-group col-md-2">
                            <label class="mb-1">Hasta</label>
                            <input type="date" name="fecha_hasta" class="form-control form-control-sm" value="{{ $filtros['fecha_hasta'] }}">
                        </div>
                        <div class="form-group col-md-3">
                            <label class="mb-1">Agrupamiento</label>
                            <select name="agrupamiento_id" class="form-control form-control-sm">
                                <option value="">Todos</option>
                                @foreach ($agrupamientos as $a)
                                    <option value="{{ $a->id }}" {{ (int) $filtros['agrupamiento_id'] === (int) $a->id ? 'selected' : '' }}>{{ $a->descripcion }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="form-group col-md-3">
                            <label class="mb-1">Empresa</label>
                            <select name="empresa_id" class="form-control form-control-sm">
                                <option value="">Todas</option>
                                @foreach ($empresas as $e)
                                    <option value="{{ $e->id }}" {{ (int) $filtros['empresa_id'] === (int) $e->id ? 'selected' : '' }}>{{ $e->nombre }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group col-md-6">
                            <label class="mb-1">Buscar (empleado / legajo / prenda / SKU)</label>
                            <input type="text" name="filtro_valor" class="form-control form-control-sm" value="{{ $filtros['texto'] }}">
                        </div>
                        <div class="form-group col-md-6 align-self-end">
                            <button type="submit" class="btn btn-sm btn-primary"><i class="fa fa-search"></i> Consultar</button>
                            <a href="{{ route('entrega_prenda_reporte') }}" class="btn btn-sm btn-outline-secondary">Limpiar</a>
                        </div>
                    </div>
                </form>

                @if ($filtros['consultar'])
                    <div class="mb-2">
                        <span class="badge badge-info">Registros: {{ method_exists($datos, 'total') ? $datos->total() : $datos->count() }}</span>
                        <span class="badge badge-secondary">Total unidades: {{ rtrim(rtrim(number_format($totalCantidad, 2, ',', '.'), '0'), ',') }}</span>
                    </div>
                    @include('sueldos.entrega_prenda.partials.tabla_datos', ['enPantalla' => true, 'puede_ver_empleado' => $puedeVerEmpleado])
                    @if (method_exists($datos, 'links'))
                        {{ $datos->appends($filtrosQuery)->links() }}
                    @endif
                @else
                    <p class="text-muted">Defina los filtros y presione <strong>Consultar</strong>.</p>
                @endif
            </div>
        </div>
    </div>
</div>
@endsection
