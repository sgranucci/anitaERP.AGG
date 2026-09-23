@extends("theme.$theme.layout")
@section('titulo')
    Importar pedidos Anita (Interforming)
@endsection

@section('scripts')
<script src="{{ asset('assets/pages/scripts/ventas/pedido/interforming/importar_anita.js') }}?v={{ @filemtime(public_path('assets/pages/scripts/ventas/pedido/interforming/importar_anita.js')) ?: time() }}" type="text/javascript"></script>
@endsection

@section('contenido')
<div class="row">
    <div class="col-lg-12">
        @include('includes.mensaje')
        <div class="card card-info">
            <div class="card-header">
                <h3 class="card-title">Importar pedidos desde Anita (Interforming)</h3>
                <div class="card-tools">
                    <a href="{{ route('pedido') }}" class="btn btn-outline-info btn-sm" title="Volver al listado">
                        <i class="fa fa-reply-all"></i> Pedidos
                    </a>
                    <a href="{{ route('importar_pedido_anita') }}" class="btn btn-outline-secondary btn-sm" title="Limpiar filtros">
                        <i class="fa fa-eraser"></i> Limpiar
                    </a>
                </div>
            </div>

            <form method="get"
                  action="{{ route('importar_pedido_anita') }}"
                  id="form-importar-pedido-anita-consultar"
                  class="mb-0">
                <input type="hidden" name="consultar" value="1">
                <div class="card-body pb-2">
                    <p class="text-muted small mb-3">
                        Consulta pedidos Anita (<code>pendmae</code>/<code>pendmov</code>) por
                        <strong>fecha de pedido</strong> (<code>penm_fecha</code>) y tipo PED/PEX.
                        No importa remitos REB/REX. Conserva el estado Anita en <code>estadopedido</code>.
                    </p>

                    <div class="form-group row">
                        <label for="fecha_desde" class="col-lg-2 control-label text-right pr-2 requerido">
                            Fecha desde
                        </label>
                        <div class="col-lg-3">
                            <input type="date"
                                   name="fecha_desde"
                                   id="fecha_desde"
                                   class="form-control"
                                   value="{{ $filtros['fecha_desde'] ?? date('Y-m-d') }}"
                                   required>
                        </div>
                        <label for="fecha_hasta" class="col-lg-2 control-label text-right pr-2 requerido">
                            Fecha hasta
                        </label>
                        <div class="col-lg-3">
                            <input type="date"
                                   name="fecha_hasta"
                                   id="fecha_hasta"
                                   class="form-control"
                                   value="{{ $filtros['fecha_hasta'] ?? date('Y-m-d') }}"
                                   required>
                        </div>
                    </div>

                    <div class="form-group row">
                        <label for="tipo" class="col-lg-2 control-label text-right pr-2">
                            Tipo
                        </label>
                        <div class="col-lg-3">
                            <select name="tipo" id="tipo" class="form-control">
                                <option value="TODOS" {{ ($filtros['tipo'] ?? 'TODOS') === 'TODOS' ? 'selected' : '' }}>Todos (PED + PEX)</option>
                                <option value="PED" {{ ($filtros['tipo'] ?? '') === 'PED' ? 'selected' : '' }}>PED</option>
                                <option value="PEX" {{ ($filtros['tipo'] ?? '') === 'PEX' ? 'selected' : '' }}>PEX</option>
                            </select>
                        </div>
                    </div>
                </div>
            </form>

            <div class="card-footer d-flex flex-wrap align-items-center">
                <button type="submit"
                        form="form-importar-pedido-anita-consultar"
                        class="btn btn-primary btn-sm mr-2"
                        id="btn-consultar-pedido-anita">
                    <i class="fa fa-search"></i> Consultar
                </button>

                @if ($puedeEjecutar)
                    <form method="post"
                          action="{{ route('ejecutar_importar_pedido_anita') }}"
                          id="form-importar-pedido-anita-ejecutar"
                          class="d-inline"
                          onsubmit="return confirm('Se importarán/actualizarán todos los pedidos del filtro. ¿Continuar?');">
                        @csrf
                        <input type="hidden" name="fecha_desde" value="{{ $filtros['fecha_desde'] ?? date('Y-m-d') }}">
                        <input type="hidden" name="fecha_hasta" value="{{ $filtros['fecha_hasta'] ?? date('Y-m-d') }}">
                        <input type="hidden" name="tipo" value="{{ $filtros['tipo'] ?? 'TODOS' }}">
                        <button type="submit" class="btn btn-success btn-sm" id="btn-importar-pedido-anita">
                            <i class="fa fa-download"></i> Ejecutar importación
                        </button>
                    </form>
                @endif
            </div>

            @if ($consultar)
                <div class="card-body pt-0">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <h5 class="mb-0">Vista previa</h5>
                        <span class="text-muted small">{{ count($filas) }} pedido(s)</span>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-sm table-bordered table-hover mb-0" id="tabla-paginada">
                            <thead style="background:#85C1E9;color:#17202A;">
                                <tr>
                                    <th>Código ERP</th>
                                    <th>Tipo</th>
                                    <th>Cliente</th>
                                    <th>Fecha</th>
                                    <th>Estado Anita</th>
                                    <th>Estado ERP</th>
                                    <th class="text-right">Cotización</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($filas as $fila)
                                    <tr>
                                        <td>{{ $fila['codigo'] }}</td>
                                        <td>{{ $fila['tipo'] ?? '' }}</td>
                                        <td>
                                            {{ $fila['codigo_cliente'] }}
                                            @if (($fila['nombre_cliente'] ?? '') !== '')
                                                — {{ $fila['nombre_cliente'] }}
                                            @endif
                                        </td>
                                        <td>{{ $fila['fecha'] }}</td>
                                        <td>
                                            {{ $fila['estado_anita'] }}
                                            @if (($fila['estado_anita_etiqueta'] ?? '') !== '')
                                                ({{ $fila['estado_anita_etiqueta'] }})
                                            @endif
                                        </td>
                                        <td>
                                            @if (($fila['estado_erp'] ?? '') === 'existe')
                                                <span class="badge badge-warning">Existe (actualizar)</span>
                                            @elseif (($fila['estado_erp'] ?? '') === 'omitido_facturado')
                                                <span class="badge badge-secondary">Facturado / anulado / con venta</span>
                                            @else
                                                <span class="badge badge-success">Nuevo</span>
                                            @endif
                                        </td>
                                        <td class="text-right">
                                            {{ number_format((float) ($fila['cotizacion'] ?? 0), 4, ',', '.') }}
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="7" class="text-center text-muted py-4">
                                            No hay pedidos Anita para el filtro indicado.
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            @endif
        </div>
    </div>
</div>

@include('includes.proceso_overlay_aviso', [
    'overlayId' => 'importar-pedido-anita-overlay',
    'tituloId' => 'importar-pedido-anita-titulo',
    'subtituloId' => 'importar-pedido-anita-subtitulo',
    'titulo' => 'Procesando…',
    'subtitulo' => 'Puede demorar según la cantidad de pedidos. No cierre la página.',
])
@endsection
