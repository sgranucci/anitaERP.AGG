@extends("theme.$theme.layout")
@section('titulo')
    Importar remitos Anita
@endsection

@section('scripts')
<script src="{{ asset('assets/pages/scripts/ventas/remito_importar_anita/index.js') }}" type="text/javascript"></script>
@endsection

@section('contenido')
<div class="row">
    <div class="col-lg-12">
        @include('includes.mensaje')
        <div class="card card-info">
            <div class="card-header">
                <h3 class="card-title">Importar remitos desde Anita</h3>
                <div class="card-tools">
                    <a href="{{ route('importar_remito_anita') }}" class="btn btn-outline-secondary btn-sm" title="Limpiar filtros">
                        <i class="fa fa-eraser"></i> Limpiar
                    </a>
                </div>
            </div>

            <form method="get"
                  action="{{ route('importar_remito_anita') }}"
                  id="form-importar-remito-anita-consultar"
                  class="mb-0">
                <input type="hidden" name="consultar" value="1">
                <div class="card-body pb-2">
                    <p class="text-muted small mb-3">
                        Consulta remitos de Anita (<code>pendmae</code>) tipo <strong>REM R 1</strong>
                        por fecha del comprobante y reparto (transporte <code>penm_expreso</code>).
                        Luego puede importarlos al ERP: crea los faltantes y actualiza cabecera y líneas de los existentes
                        que aún no estén facturados.
                        El cliente interno DESPACHO no se importa.
                    </p>

                    <div class="form-group row">
                        <label for="fecha_entrega_desde" class="col-lg-2 control-label text-right pr-2 requerido">
                            Fecha desde
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
                            Fecha hasta
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
                        form="form-importar-remito-anita-consultar"
                        class="btn btn-primary btn-sm mr-2"
                        id="btn-consultar-remito-anita">
                    <i class="fa fa-search"></i> Consultar
                </button>

                @if ($puedeEjecutar)
                    <form method="post"
                          action="{{ route('ejecutar_importar_remito_anita') }}"
                          id="form-importar-remito-anita-ejecutar"
                          class="d-inline"
                          onsubmit="return confirm('Se importarán/actualizarán todos los remitos REM R 1 del filtro. ¿Continuar?');">
                        @csrf
                        <input type="hidden" name="fecha_entrega_desde" value="{{ $filtros['fecha_entrega_desde'] ?? date('Y-m-d') }}">
                        <input type="hidden" name="fecha_entrega_hasta" value="{{ $filtros['fecha_entrega_hasta'] ?? date('Y-m-d') }}">
                        <input type="hidden" name="filtro_reparto" value="{{ $filtros['filtro_reparto'] ?? '' }}">
                        <button type="submit" class="btn btn-success btn-sm" id="btn-importar-remito-anita">
                            <i class="fa fa-download"></i> Importar
                        </button>
                    </form>
                @endif
            </div>

            @if ($consultar)
                <div class="card-body pt-0">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <h5 class="mb-0">Vista previa</h5>
                        <span class="text-muted small">{{ count($filas) }} remito(s)</span>
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
                                            @elseif (($fila['estado_erp'] ?? '') === 'omitido_despacho')
                                                <span class="badge badge-info">DESPACHO: no importa</span>
                                            @elseif (($fila['estado_erp'] ?? '') === 'omitido_facturado')
                                                <span class="badge badge-secondary">Facturado: no se pisa</span>
                                            @else
                                                <span class="badge badge-success">Nuevo</span>
                                            @endif
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="9" class="text-center text-muted py-4">
                                            No hay remitos Anita REM R 1 para el filtro indicado.
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
    'overlayId' => 'importar-remito-anita-overlay',
    'tituloId' => 'importar-remito-anita-titulo',
    'subtituloId' => 'importar-remito-anita-subtitulo',
    'titulo' => 'Procesando…',
    'subtitulo' => 'Puede demorar según la cantidad de remitos. No cierre la página.',
])
@endsection
