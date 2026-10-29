@php
    $conFoto = ($imprimefoto ?? '') === 'CON_FOTO';
    $medidasCols = $medidas_columnas ?? [];
    $esMovimientos = ! empty($es_movimientos);
    $conHerramientas = ! empty($con_herramientas);
    $puedeVerOt = $conHerramientas && can('editar-ordenes-de-trabajo', false);
    $puedeVerArticulo = $conHerramientas && can('editar-articulos', false);
    $puedeVerPedido = $conHerramientas && can('editar-pedidos', false);
@endphp
<div class="table-responsive">
<table class="table table-sm table-bordered table-hover mb-0" id="tabla-paginada">
    <thead style="background:#85C1E9;color:#17202A;">
        <tr>
            @if ($conFoto)
                <th>Foto</th>
            @endif
            <th>Línea</th>
            <th>Art.</th>
            <th>Descripción</th>
            @foreach ($medidasCols as $medida)
                <th class="text-right">{{ $medida }}</th>
            @endforeach
            <th class="text-right">PS</th>
            <th>Q M</th>
            <th class="text-right">N</th>
            <th class="text-right">TT</th>
            <th class="text-right">Precio</th>
            <th>Situación</th>
            <th>OT / lote</th>
            <th>Depósito</th>
            @if ($esMovimientos)
                <th>Módulo</th>
                <th>Pedido</th>
            @endif
            @if ($conHerramientas)
                <th>Herramientas</th>
            @endif
        </tr>
    </thead>
    <tbody>
        @forelse ($filas as $lote)
            @php
                $rojo = \App\Support\Stock\ReporteStockOtSituacionSupport::colorearRojoEnExcel(
                    (string) ($lote['situacion'] ?? ''),
                    ! empty($lote['en_produccion'])
                );
                $otId = (int) ($lote['ordentrabajo_id'] ?? 0);
                $articuloId = (int) ($lote['articulo_id'] ?? 0);
                $pedidoId = (int) ($lote['pedido'] ?? 0);
            @endphp
            <tr @if($rojo) class="text-danger" @endif>
                @include('stock.repstockot.partials.fila_celdas', [
                    'lote' => $lote,
                    'imprimefoto' => $imprimefoto ?? '',
                    'medidas_columnas' => $medidasCols,
                    'es_movimientos' => $esMovimientos,
                ])
                @if ($conHerramientas)
                    <td class="text-nowrap">
                        @if ($puedeVerOt && $otId > 0)
                            <a href="{{ route('editar_ordentrabajo', ['id' => $otId, 'origen' => 'modal_consulta', 'vista' => 'consulta']) }}"
                               class="btn-accion-tabla tooltipsC text-primary" target="_blank" rel="noopener"
                               title="Ver orden de trabajo">
                                <i class="fa fa-clipboard"></i>
                            </a>
                        @endif
                        @if ($puedeVerArticulo && $articuloId > 0)
                            <a href="{{ route('editar_articulo', ['id' => $articuloId, 'origen' => 'modal_consulta', 'vista' => 'consulta']) }}"
                               class="btn-accion-tabla tooltipsC" target="_blank" rel="noopener"
                               title="Ver artículo">
                                <i class="fa fa-edit"></i>
                            </a>
                        @endif
                        @if ($puedeVerPedido && $pedidoId > 0)
                            <a href="{{ route('editar_pedido', ['id' => $pedidoId, 'origen' => 'modal_consulta', 'vista' => 'consulta']) }}"
                               class="btn-accion-tabla tooltipsC" target="_blank" rel="noopener"
                               title="Ver pedido">
                                <i class="fa fa-file-text-o"></i>
                            </a>
                        @endif
                    </td>
                @endif
            </tr>
        @empty
            <tr>
                <td colspan="20" class="text-center text-muted">No hay pares con estos filtros.</td>
            </tr>
        @endforelse
    </tbody>
</table>
</div>
