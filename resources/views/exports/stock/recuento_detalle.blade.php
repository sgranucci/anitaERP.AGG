@php
    use App\Models\Stock\Recuento;

    $colspan = 9;
    $totalValorContado = 0.0;
    $totalValorDif = 0.0;
@endphp
<table>
    @if (!empty($reservarFilaLogoExcel))
        <tbody>
            <tr>
                <td colspan="{{ $colspan }}" style="height: 52px;">&#160;</td>
            </tr>
        </tbody>
    @endif
    <tbody>
        <tr>
            <td colspan="{{ $colspan }}">
                <strong>Recuento {{ $recuento->codigo }}</strong>
            </td>
        </tr>
        <tr>
            <td colspan="{{ $colspan }}">
                Fecha: {{ optional($recuento->fecha)->format('d/m/Y') }}
                | Estado: {{ Recuento::etiquetaEstado($recuento->estado) }}
                | Dep&oacute;sito: {{ optional($recuento->deposito)->etiqueta() }}
                | Usuario: {{ optional($recuento->usuario)->nombre }}
                | Generado: {{ date('d/m/Y H:i') }}
            </td>
        </tr>
        <tr>
            <td colspan="{{ $colspan }}">
                @if (! empty($recuento->comentario))
                    Comentario: {{ $recuento->comentario }} |
                @endif
                Costo u/c: &uacute;ltima compra (Anita &rarr; ERP COM &rarr; PPP art&iacute;culo).
                L&iacute;neas: {{ $recuento->items->count() }}
            </td>
        </tr>
    </tbody>
    <thead>
        <tr>
            <th>SKU</th>
            <th>Descripci&oacute;n</th>
            <th>UM</th>
            <th>Saldo sistema</th>
            <th>Contado</th>
            <th>Diferencia a ajustar</th>
            <th>Costo u/c</th>
            <th>Valor contado</th>
            <th>Valor dif.</th>
        </tr>
    </thead>
    <tbody>
        @foreach ($recuento->items as $item)
            @php
                $dif = $item->diferencia();
                $costoUc = $item->precio_ultima_compra ?? null;
                $contado = (float) $item->cantidad_contada;
                $valorContado = ($costoUc !== null) ? $contado * (float) $costoUc : null;
                $valorDif = ($costoUc !== null && abs($dif) > 1e-9) ? $dif * (float) $costoUc : null;
                if ($valorContado !== null) {
                    $totalValorContado += $valorContado;
                }
                if ($valorDif !== null) {
                    $totalValorDif += $valorDif;
                }
            @endphp
            <tr>
                <td>{{ optional($item->articulos)->sku }}</td>
                <td>{{ $item->detalle ?: optional($item->articulos)->descripcion }}</td>
                <td>{{ optional($item->unidadmedida)->abreviatura ?? optional($item->articulos?->unidadesdemedidas)->abreviatura }}</td>
                <td>{{ (float) $item->saldo_sistema }}</td>
                <td>{{ $contado }}</td>
                <td>{{ $dif }}</td>
                <td>@if ($costoUc !== null){{ (float) $costoUc }}@endif</td>
                <td>@if ($valorContado !== null){{ (float) $valorContado }}@endif</td>
                <td>@if ($valorDif !== null){{ (float) $valorDif }}@endif</td>
            </tr>
        @endforeach
        <tr>
            <td colspan="7"><strong>Totales (costo u/c)</strong></td>
            <td><strong>{{ $totalValorContado }}</strong></td>
            <td><strong>{{ $totalValorDif }}</strong></td>
        </tr>
    </tbody>
</table>
