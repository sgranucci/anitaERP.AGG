@extends("theme.$theme.layout")
@section('titulo')
    Importar pedidos Anita
@endsection

@section('scripts')
<script src="{{ asset('assets/pages/scripts/ventas/pedido_importar_anita/index.js') }}" type="text/javascript"></script>
@endsection

@section('contenido')
<div class="row">
    <div class="col-lg-12">
        @include('includes.mensaje')
        <div class="card card-info">
            <div class="card-header">
                <h3 class="card-title">Importar pedidos desde Anita</h3>
                <div class="card-tools">
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
                        Consulta pedidos de Anita (<code>pendmae</code>) por fecha de entrega y reparto (transporte <code>penm_expreso</code>).
                        Luego puede importarlos al ERP: crea los faltantes y actualiza cabecera y líneas de los existentes.
                    </p>

                    <div class="form-group row">
                        <label for="fecha_entrega_desde" class="col-lg-2 control-label text-right pr-2 requerido">
                            Entrega desde
                        </label>
                        <div class="col-lg-3">
                            <input type="date"
                                   name="fecha_entrega_desde"
                                   id="fecha_entrega_desde"
                                   class="form-control"
                                   value="{{ $filtros['fecha_entrega_desde'] ?? date('Y-m-d') }}"
                                   required>
                        </div>
                        <label for="fecha_entrega_hasta" class="col-lg-2 control-label text-right pr-2 requerido">
                            Entrega hasta
                        </label>
                        <div class="col-lg-3">
                            <input type="date"
                                   name="fecha_entrega_hasta"
                                   id="fecha_entrega_hasta"
                                   class="form-control"
                                   value="{{ $filtros['fecha_entrega_hasta'] ?? date('Y-m-d') }}"
                                   required>
                        </div>
                    </div>

                    <div class="form-group row">
                        <label for="filtro_reparto" class="col-lg-2 control-label text-right pr-2">
                            Reparto (transporte)
                        </label>
                        <div class="col-lg-6">
                            <input type="text"
                                   name="filtro_reparto"
                                   id="filtro_reparto"
                                   class="form-control"
                                   value="{{ $filtros['filtro_reparto'] ?? '' }}"
                                   placeholder="Ej: 1,3,5 ó 10/20 (vacío = todos)"
                                   autocomplete="off"
                                   title="Coma = lista; barra / = rango">
                            <small class="form-text text-muted">
                                Lista con coma; rango con /. Vacío importa todos los repartos del rango de fechas.
                            </small>
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
                        <input type="hidden" name="fecha_entrega_desde" value="{{ $filtros['fecha_entrega_desde'] ?? date('Y-m-d') }}">
                        <input type="hidden" name="fecha_entrega_hasta" value="{{ $filtros['fecha_entrega_hasta'] ?? date('Y-m-d') }}">
                        <input type="hidden" name="filtro_reparto" value="{{ $filtros['filtro_reparto'] ?? '' }}">
                        <button type="submit" class="btn btn-success btn-sm" id="btn-importar-pedido-anita">
                            <i class="fa fa-download"></i> Importar
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
                                    <th>Suc.</th>
                                    <th>Nro</th>
                                    <th>Cliente</th>
                                    <th>Fecha</th>
                                    <th>Entrega</th>
                                    <th>Reparto</th>
                                    <th>Estado Anita</th>
                                    <th>ERP</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($filas as $fila)
                                    <tr>
                                        <td>{{ $fila['codigo'] }}</td>
                                        <td>{{ $fila['sucursal'] }}</td>
                                        <td>{{ $fila['nro'] }}</td>
                                        <td>
                                            {{ $fila['codigo_cliente'] }}
                                            @if (($fila['nombre_cliente'] ?? '') !== '')
                                                — {{ $fila['nombre_cliente'] }}
                                            @endif
                                        </td>
                                        <td>{{ $fila['fecha'] }}</td>
                                        <td>{{ $fila['fecha_entrega'] }}</td>
                                        <td>{{ $fila['reparto'] }}</td>
                                        <td>{{ $fila['estado_anita'] }}</td>
                                        <td>
                                            @if (($fila['estado_erp'] ?? '') === 'existe')
                                                <span class="badge badge-warning">Existe (actualizar)</span>
                                            @else
                                                <span class="badge badge-success">Nuevo</span>
                                            @endif
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="9" class="text-center text-muted py-4">
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
