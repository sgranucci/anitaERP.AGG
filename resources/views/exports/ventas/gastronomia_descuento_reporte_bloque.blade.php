@php
    $colspan = 7;
    $tituloBloque = trim((string) (($bloque['codigo'] ?? '').' — '.($bloque['nombre'] ?? '')));
@endphp
<table>
    @if (! empty($reservarFilaLogoExcel))
        <tr><td colspan="{{ $colspan }}" style="height: 52px;">&#160;</td></tr>
    @endif
    <tr>
        <td colspan="{{ $colspan }}">
            <strong style="font-size: 16pt;">{{ $tituloBloque !== '—' ? $tituloBloque : 'Descuento' }}</strong>
        </td>
    </tr>
    <tr>
        <td colspan="{{ $colspan }}" style="font-size: 10pt; color: #444;">
            Generado {{ date('d/m/Y H:i') }}
        </td>
    </tr>
    @if (! empty($periodo_texto))
        <tr>
            <td colspan="{{ $colspan }}" style="font-size: 10pt; color: #444;">
                Per&iacute;odo: {{ $periodo_texto }}
            </td>
        </tr>
    @endif
    @if (! empty($empresa_nombre))
        <tr>
            <td colspan="{{ $colspan }}" style="font-size: 10pt; color: #444;">
                Empresa: {{ $empresa_nombre }}
            </td>
        </tr>
    @endif
    <tr>
        <th>Artículo</th>
        <th>Descripción</th>
        <th>Unidades</th>
        <th>Costo unit.</th>
        <th>Costo total</th>
        <th>Precio vta.</th>
        <th>Total venta</th>
    </tr>
    @foreach ($bloque['filas'] ?? [] as $fila)
        <tr>
            <td>{{ $fila['sku'] ?? '' }}</td>
            <td>{{ $fila['descripcion'] ?? '' }}</td>
            <td>{{ $fila['unidades'] ?? 0 }}</td>
            <td>{{ $fila['costo_unitario'] ?? 0 }}</td>
            <td>{{ $fila['costo_total'] ?? 0 }}</td>
            <td>{{ $fila['precio_venta'] ?? 0 }}</td>
            <td>{{ $fila['total_venta'] ?? 0 }}</td>
        </tr>
    @endforeach
    <tr>
        <td colspan="2"><strong>Total final</strong></td>
        <td><strong>{{ $bloque['totales']['unidades'] ?? 0 }}</strong></td>
        <td></td>
        <td><strong>{{ $bloque['totales']['costo_total'] ?? 0 }}</strong></td>
        <td></td>
        <td><strong>{{ $bloque['totales']['total_venta'] ?? 0 }}</strong></td>
    </tr>
</table>
