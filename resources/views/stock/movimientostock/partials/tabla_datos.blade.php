@php
    use App\Support\Stock\MovimientoStockFerliSupport;

    $puedeEditar = $puede_editar ?? can('editar-movimientos-de-stock', false);
    $puedeBorrar = $puede_borrar ?? can('borrar-movimientos-de-stock', false);
    $puedeListar = $puede_listar ?? can('listar-movimientos-de-stock', false);
    $mostrarAcciones = $mostrar_acciones ?? true;
    $esFerli = MovimientoStockFerliSupport::esCalzadosFerli();
@endphp
@foreach ($datas as $fila)
    @php
        /** @var \App\Support\Stock\MovimientoStockListadoFila $fila */
        $estadoLabel = $fila->etiquetaEstadoListado();
    @endphp
    <tr data-entry-id="{{ $fila->pkId }}" data-fila-tipo="{{ $fila->filaTipo }}">
        <td>
            {{ $fila->pkId }}
            @if ($fila->esTransferencia())
                <br><span class="badge badge-info">Transferencia</span>
            @else
                <br><span class="badge badge-light border">Movimiento</span>
            @endif
        </td>
        <td>{{ $fila->fecha ? date('d/m/Y', strtotime($fila->fecha)) : '' }}</td>
        <td><b>{{ $fila->tipoNombre }}</b></td>
        <td><small>{{ $fila->codigoListado }}</small></td>
        @if ($esFerli)
            <td><small>{{ $fila->marcaNombre ?? '' }}</small></td>
        @endif
        <td><small>{{ $fila->loteListado }}</small></td>
        <td><small>{{ $fila->etiquetaOrigen() }}</small></td>
        <td><small>{{ $fila->etiquetaDestino() }}</small></td>
        <td><small>{{ $fila->nombreEmpresa }}</small></td>
        <td class="text-right">{{ number_format($fila->totalCantidad, 2, ',', '.') }}</td>
        <td class="text-center">{{ $fila->itemsCount > 0 ? $fila->itemsCount : '' }}</td>
        <td>
            @if ($fila->esTransferencia())
                <span class="badge badge-secondary">{{ $estadoLabel }}</span>
            @else
                <small>{{ $estadoLabel }}</small>
            @endif
        </td>
        @if ($mostrarAcciones)
            <td class="text-nowrap">
                @if ($fila->esTransferencia())
                    @if ($puedeListar)
                        @include('stock.movimientostock.partials.boton_imprimir_transferencia_com_pdf', [
                            'transferenciaId' => $fila->pkId,
                            'modo' => 'tabla',
                        ])
                        <a href="{{ route('consultar_transferencia_movimientostock', ['id' => $fila->pkId]) }}" class="btn-accion-tabla tooltipsC" title="Consultar transferencia" target="_blank" rel="noopener">
                            <i class="fa fa-eye"></i>
                        </a>
                    @endif
                    @if ($puedeEditar && $fila->movSalidaId)
                        <a href="{{ route('editar_movimientostock', ['id' => $fila->movSalidaId]) }}" class="btn-accion-tabla tooltipsC" title="Editar egreso ({{ $fila->movSalidaId }})">
                            <i class="fa fa-sign-out-alt text-warning"></i>
                        </a>
                    @endif
                    @if ($puedeEditar && $fila->movEntradaId)
                        <a href="{{ route('editar_movimientostock', ['id' => $fila->movEntradaId]) }}" class="btn-accion-tabla tooltipsC" title="Editar ingreso ({{ $fila->movEntradaId }})">
                            <i class="fa fa-sign-in-alt text-success"></i>
                        </a>
                    @endif
                @else
                    @if ($puedeEditar && $fila->movimiento)
                        <a href="{{ route('editar_movimientostock', ['id' => $fila->movimiento->id]) }}" class="btn-accion-tabla tooltipsC" title="Editar este registro">
                            <i class="fa fa-edit"></i>
                        </a>
                    @endif
                    @if ($puedeBorrar && $fila->movimiento)
                        <form action="{{ route('eliminar_movimientostock', ['id' => $fila->movimiento->id]) }}" class="d-inline form-eliminar" method="POST">
                            @csrf @method('delete')
                            <button type="submit" class="btn-accion-tabla eliminar tooltipsC" title="Eliminar este registro">
                                <i class="fa fa-times-circle text-danger"></i>
                            </button>
                        </form>
                    @endif
                    @if ($puedeListar && $fila->movimiento)
                        @include('stock.movimientostock.partials.boton_imprimir_com_pdf', [
                            'movimientoStockId' => $fila->movimiento->id,
                            'modo' => 'tabla',
                        ])
                        <a href="{{ route('editar_movimientostock', ['id' => $fila->movimiento->id, 'origen' => 'modal_consulta', 'vista' => 'consulta']) }}" class="btn-accion-tabla tooltipsC" title="Consultar movimiento" target="_blank" rel="noopener">
                            <i class="fa fa-eye"></i>
                        </a>
                    @endif
                @endif
            </td>
        @endif
    </tr>
@endforeach
