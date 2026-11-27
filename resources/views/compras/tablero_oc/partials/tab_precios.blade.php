@php
    $precios = $tablero['precios'] ?? null;
    $articulos = $tablero['articulos'] ?? [];
    $fmtNum = static fn ($v, $dec = 2) => number_format((float) $v, $dec, ',', '.');
    $queryArticulo = $filtrosQuery;
    unset($queryArticulo['articulo_id']);
@endphp

<div class="card card-outline card-primary mb-3">
    <div class="card-body py-2">
        <div class="form-row align-items-end">
            <div class="col-md-6 mb-1">
                <label for="buscar_articulo" class="mb-0 small">Buscar art&iacute;culo por c&oacute;digo (SKU) o descripci&oacute;n</label>
                <input type="text" name="buscar_articulo" id="buscar_articulo" form="form-tablero-compras"
                    class="form-control form-control-sm" autocomplete="off"
                    placeholder="Ej.: TOMATE o 3048"
                    value="{{ $filtros['buscar_articulo'] }}">
            </div>
            <div class="col-auto mb-1">
                <button type="submit" form="form-tablero-compras" class="btn btn-primary btn-sm" id="tablero-buscar-articulo">
                    <i class="fa fa-search"></i> Buscar
                </button>
            </div>
        </div>
        <small class="text-muted">Lista los art&iacute;culos comprados en el per&iacute;odo (y con los filtros de arriba) que coinciden; elija uno para ver su evoluci&oacute;n.</small>
    </div>
</div>

@if ($precios !== null)
    <div class="alert alert-info py-2">
        <strong>{{ $precios['sku'] }} &mdash; {{ $precios['descripcion'] }}</strong>
        <a href="{{ route('consultar_tablero_compras', $queryArticulo + ['tab' => 'precios']) }}" class="ml-2 small">
            <i class="fa fa-times"></i> Quitar art&iacute;culo
        </a>
    </div>

    <div class="row">
        <div class="col-lg-8 mb-3">
            <div class="card card-outline card-info h-100 mb-0">
                <div class="card-header"><span class="tc-card-titulo">Precio unitario en $ por proveedor (promedio mensual)</span></div>
                <div class="card-body"><div class="tc-grafico tc-alto"><canvas id="tc-precios"></canvas></div></div>
            </div>
        </div>
        <div class="col-lg-4 mb-3">
            <div class="card card-outline card-info h-100 mb-0">
                <div class="card-header"><span class="tc-card-titulo">Cantidad comprada por mes</span></div>
                <div class="card-body"><div class="tc-grafico tc-alto"><canvas id="tc-precios-cantidad"></canvas></div></div>
            </div>
        </div>
    </div>

    <div class="card card-outline card-info mb-0">
        <div class="card-header"><span class="tc-card-titulo">Historial de compras del art&iacute;culo</span></div>
        <div class="card-body p-0">
            <div class="table-responsive" style="max-height: 360px; overflow-y: auto;">
                <table class="table table-sm table-bordered mb-0 tc-tabla">
                    <thead>
                        <tr>
                            <th>Fecha</th>
                            <th>OC</th>
                            <th>Proveedor</th>
                            <th class="text-right">Cantidad</th>
                            <th>Mon.</th>
                            <th class="text-right">Precio (moneda OC)</th>
                            <th class="text-right">Precio $</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($precios['filas'] as $fila)
                            <tr>
                                <td>{{ \Carbon\Carbon::parse($fila['fecha'])->format('d/m/Y') }}</td>
                                <td>
                                    @if ($puede_ver_ordencompra)
                                        <a href="{{ route('editar_ordencompra', ['id' => $fila['oc_id'], 'origen' => 'modal_consulta', 'vista' => 'consulta']) }}"
                                            class="text-primary" target="_blank" rel="noopener">{{ $fila['numero'] }}</a>
                                    @else
                                        {{ $fila['numero'] }}
                                    @endif
                                </td>
                                <td>{{ $fila['proveedor'] }}</td>
                                <td class="text-right">{{ $fmtNum($fila['cantidad'], 2) }}</td>
                                <td>{{ $fila['moneda'] }}</td>
                                <td class="text-right">{{ $fmtNum($fila['precio'], 2) }}</td>
                                <td class="text-right">{{ $fmtNum($fila['precio_pesos'], 2) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
@elseif (count($articulos) > 0)
    <div class="card card-outline card-info mb-0">
        <div class="card-header"><span class="tc-card-titulo">Art&iacute;culos comprados{{ $filtros['buscar_articulo'] !== '' ? ' que coinciden con «'.$filtros['buscar_articulo'].'»' : ' (40 más comprados)' }}</span></div>
        <div class="card-body p-0">
            <div class="table-responsive" style="max-height: 420px; overflow-y: auto;">
                <table class="table table-sm table-bordered table-hover mb-0 tc-tabla">
                    <thead>
                        <tr>
                            <th>SKU</th>
                            <th>Art&iacute;culo</th>
                            <th class="text-right">L&iacute;neas</th>
                            <th class="text-right">Cantidad</th>
                            <th class="text-right">Monto $</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($articulos as $a)
                            <tr>
                                <td>{{ $a['sku'] }}</td>
                                <td>{{ $a['descripcion'] }}</td>
                                <td class="text-right">{{ number_format($a['lineas'], 0, ',', '.') }}</td>
                                <td class="text-right">{{ $fmtNum($a['cantidad'], 2) }}</td>
                                <td class="text-right">{{ number_format($a['monto'], 0, ',', '.') }}</td>
                                <td>
                                    <a href="{{ route('consultar_tablero_compras', $queryArticulo + ['tab' => 'precios', 'articulo_id' => $a['id']]) }}"
                                        class="btn btn-outline-primary btn-sm py-0">
                                        <i class="fa fa-line-chart"></i> Ver evoluci&oacute;n
                                    </a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
@else
    <p class="text-muted mb-0">No hay art&iacute;culos comprados que coincidan con los filtros.</p>
@endif
