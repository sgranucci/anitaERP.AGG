@php
    $puedeCambiar = $puedeCambiarArticulo ?? false;
    $sku = $linea->articulos->sku ?? '';
    $articuloId = (int) ($linea->articulo_id ?? 0);
@endphp
@if ($puedeCambiar)
    <td class="celda-articulo-cumple align-middle">
        <input type="hidden" class="articulo_id" name="lineas[{{ $idx }}][articulo_id]" value="{{ $articuloId }}">
        <input type="hidden" class="articulo_id_original" value="{{ $articuloId }}">
        <div class="input-group input-group-sm">
            <div class="input-group-prepend">
                <button type="button" class="btn btn-outline-secondary btn-sm consultaarticulo btn-cambio-articulo-cumple" title="Cambiar art&iacute;culo (requiere permiso log&iacute;stica)">
                    <i class="fa fa-search"></i>
                </button>
            </div>
            <input type="text" class="form-control form-control-sm codigoarticulo" value="{{ $sku }}" readonly>
        </div>
        <small class="text-warning d-none aviso-articulo-cambiado"><i class="fa fa-exchange-alt"></i> Art. modificado</small>
    </td>
@else
    <td>{{ $sku }}</td>
@endif
