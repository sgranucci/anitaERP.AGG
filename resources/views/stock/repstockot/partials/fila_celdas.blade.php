@php
    $conFoto = ($imprimefoto ?? '') === 'CON_FOTO';
    $medidasCols = $medidas_columnas ?? [];
    $presentacion = \App\Support\Stock\CurvaModuloPresentacionSupport::presentarDesdeLista($lote['medidas'] ?? []);
    $cantPorMedida = $presentacion['medidas'];
    $ps = (float) $presentacion['pares_modulo'];
    $nModulos = (int) $presentacion['modulos'];
    $tt = (float) ($lote['total_pares'] ?? $presentacion['total']);
    $preciosCols = $precios_columnas ?? ['PRECIO'];
    $precioPorEtiqueta = [];
    foreach ($lote['precios_fabrica'] ?? [] as $precioLista) {
        if (! is_array($precioLista)) {
            continue;
        }
        $precioPorEtiqueta[(string) ($precioLista['etiqueta'] ?? '')] = (float) ($precioLista['precio'] ?? 0);
    }
    if ($nModulos < 1) {
        $nModulos = 1;
    }
    $precio = (float) ($lote['precio'] ?? 0);
    $deposito = trim((string) ($lote['deposito_codigo'] ?? ''));
    if ($deposito === '') {
        $deposito = trim((string) ($lote['deposito_nombre'] ?? ''));
    }
    $fmt = static function (float $cant): string {
        return abs($cant - round($cant)) > 0.001
            ? number_format($cant, 2, ',', '.')
            : number_format($cant, 0, ',', '.');
    };
@endphp
@if ($conFoto)
    <td></td>
@endif
<td>{{ $lote['nombrelinea'] ?? '' }}</td>
<td>{{ $lote['sku_excel'] ?? $lote['sku'] ?? '' }}</td>
<td>{{ $lote['descripcion_excel'] ?? '' }}</td>
@foreach ($medidasCols as $medida)
    @php $cant = (float) ($cantPorMedida[(int) $medida] ?? 0); @endphp
    @if (abs($cant) > 0.0001)
        <td align="right">{{ $fmt($cant) }}</td>
    @else
        <td></td>
    @endif
@endforeach
<td align="right">{{ $fmt($ps) }}</td>
<td>X</td>
<td align="right">{{ $nModulos }}</td>
<td align="right">{{ $fmt($tt) }}</td>
@foreach ($preciosCols as $etiquetaPrecio)
    @php
        $precioCol = $etiquetaPrecio === 'PRECIO'
            ? $precio
            : (float) ($precioPorEtiqueta[$etiquetaPrecio] ?? 0);
    @endphp
    <td align="right">
        @if ($precioCol > 0)
            $ {{ number_format($precioCol, 0, ',', '.') }}
        @endif
    </td>
@endforeach
<td>{{ $lote['situacion'] ?? '' }}</td>
<td>{{ $lote['lote'] ?? '' }}</td>
<td>{{ $deposito }}</td>
@if (! empty($es_movimientos))
    <td>{{ $lote['modulo_nombre'] ?? '' }}</td>
    <td>
        @if ((int) ($lote['pedido'] ?? 0) > 0)
            {{ (int) $lote['pedido'] }}
        @endif
    </td>
@endif
