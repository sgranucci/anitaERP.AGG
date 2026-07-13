@php
    use App\Models\Stock\Recuento;
    use App\Support\Stock\RecuentoDetalleExportSupport;

    $colspan = 9;
    $detalleExport = $detalleExport
        ?? RecuentoDetalleExportSupport::agruparPorTipoArticulo($recuento->items ?? []);
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
                Ordenado por tipo de art&iacute;culo y SKU.
                Costo u/c: &uacute;ltima compra (Anita &rarr; ERP COM &rarr; PPP art&iacute;culo).
                L&iacute;neas: {{ $detalleExport['cantidad_lineas'] }}
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
        @forelse ($detalleExport['grupos'] as $grupo)
            <tr>
                <td colspan="{{ $colspan }}">
                    <strong>Tipo: {{ $grupo['tipo_nombre'] }} ({{ $grupo['cantidad_lineas'] }} l&iacute;nea{{ $grupo['cantidad_lineas'] === 1 ? '' : 's' }})</strong>
                </td>
            </tr>
            @foreach ($grupo['items'] as $linea)
                @php
                    $item = $linea['item'];
                    $dif = $linea['diferencia'];
                    $costoUc = $linea['costo_uc'];
                    $valorContado = $linea['valor_contado'];
                    $valorDif = $linea['valor_dif'];
                @endphp
                <tr>
                    <td>{{ optional($item->articulos)->sku }}</td>
                    <td>{{ $item->detalle ?: optional($item->articulos)->descripcion }}</td>
                    <td>{{ optional($item->unidadmedida)->abreviatura ?? optional($item->articulos?->unidadesdemedidas)->abreviatura }}</td>
                    <td>{{ (float) $item->saldo_sistema }}</td>
                    <td>{{ (float) $item->cantidad_contada }}</td>
                    <td>{{ $dif }}</td>
                    <td>
                        @if ($costoUc !== null)
                            {{ (float) $costoUc }}
                        @endif
                    </td>
                    <td>
                        @if ($valorContado !== null)
                            {{ (float) $valorContado }}
                        @endif
                    </td>
                    <td>
                        @if ($valorDif !== null)
                            {{ (float) $valorDif }}
                        @endif
                    </td>
                </tr>
            @endforeach
            <tr>
                <td colspan="7"><strong>Subtotal {{ $grupo['tipo_nombre'] }}</strong></td>
                <td><strong>{{ $grupo['subtotal_valor_contado'] }}</strong></td>
                <td><strong>{{ $grupo['subtotal_valor_dif'] }}</strong></td>
            </tr>
        @empty
            <tr>
                <td colspan="{{ $colspan }}">Sin l&iacute;neas de conteo.</td>
            </tr>
        @endforelse
        @if (($detalleExport['cantidad_lineas'] ?? 0) > 0)
            <tr>
                <td colspan="7"><strong>Total general (costo u/c)</strong></td>
                <td><strong>{{ $detalleExport['total_valor_contado'] }}</strong></td>
                <td><strong>{{ $detalleExport['total_valor_dif'] }}</strong></td>
            </tr>
        @endif
    </tbody>
</table>
