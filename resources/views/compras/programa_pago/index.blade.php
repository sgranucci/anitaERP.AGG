@extends("theme.$theme.layout")
@section('titulo')
    Programa de pagos
@endsection

@section('contenido')
<div class="row">
    <div class="col-lg-12">
        @include('includes.mensaje')
        <div class="card card-info">
            <div class="card-header">
                <h3 class="card-title">Programa de pagos (cashflow)</h3>
                <div class="card-tools">
                    @if (can('crear-programa-pago', false))
                        <a href="{{ route('crear_programa_pago') }}" class="btn btn-outline-secondary btn-sm">
                            <i class="fa fa-fw fa-plus-circle"></i> Nuevo programa
                        </a>
                    @endif
                </div>
            </div>
            <div class="card-body">
                @include('includes.exportar-tabla-queryparams', [
                    'ruta' => 'listar_programa_pago',
                    'queryparams' => $filtrosQuery ?? [],
                ])
                <form method="get" action="{{ route('programa_pago') }}" class="mb-3">
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
                                @foreach(\App\Models\Compras\ProgramaPago::$enumEstado as $est)
                                    <option value="{{ $est['valor'] }}" @selected(($filtros['estado'] ?? '') === $est['valor'])>{{ $est['nombre'] }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="form-group col-md-3">
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
                                <th>Id</th>
                                <th>Título</th>
                                <th>Empresa</th>
                                <th>Fecha base</th>
                                <th>Desde</th>
                                <th>Meses</th>
                                <th>Proveedores</th>
                                <th>Estado</th>
                                <th style="width:110px;">Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($coleccion as $item)
                                <tr>
                                    <td>{{ $item->id }}</td>
                                    <td>{{ $item->titulo ?: '—' }}</td>
                                    <td>{{ $item->empresas->nombre ?? '' }}</td>
                                    <td>{{ optional($item->fecha_base)->format('d/m/Y') }}</td>
                                    <td>{{ $item->anio_mes_inicio }}</td>
                                    <td>{{ $item->cantidad_meses }}</td>
                                    <td>{{ $item->lineas_count ?? 0 }}</td>
                                    <td>{{ $item->estado }}</td>
                                    <td class="text-nowrap">
                                        @if (can('editar-programa-pago', false))
                                            <a href="{{ route('editar_programa_pago', $item->id) }}" class="btn-accion-tabla tooltipsC" title="Editar">
                                                <i class="fa fa-edit"></i>
                                            </a>
                                        @endif
                                        @if (can('borrar-programa-pago', false) && $item->estado === 'BORRADOR')
                                            <form action="{{ route('eliminar_programa_pago', $item->id) }}" method="POST" class="d-inline"
                                                  onsubmit="return confirm('¿Eliminar el programa #{{ $item->id }}?');">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="btn-accion-tabla tooltipsC border-0 bg-transparent p-0" title="Eliminar">
                                                    <i class="fa fa-times-circle text-danger"></i>
                                                </button>
                                            </form>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="9" class="text-center text-muted">Sin programas. Cree uno nuevo para armar el cashflow del mes.</td>
                                </tr>
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
