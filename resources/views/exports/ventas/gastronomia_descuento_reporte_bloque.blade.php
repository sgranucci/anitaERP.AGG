@php
    use App\Support\Ventas\GastronomiaDescuentoReporteTipoArticuloSupport;

    $colspan = 7;
    $tituloReporte = trim((string) ($titulo ?? 'Reporte descuentos gastronomía'));
    $tituloBloque = trim((string) (($bloque['codigo'] ?? '').' — '.($bloque['nombre'] ?? '')));
    $grupos = $bloque['grupos'] ?? null;
    if ($grupos === null) {
        $agrupado = GastronomiaDescuentoReporteTipoArticuloSupport::agruparFilas($bloque['filas'] ?? []);
        $grupos = $agrupado['grupos'];
    }
@endphp
<table>
    @if (! empty($reservarFilaLogoExcel))
        <tr><td colspan="{{ $colspan }}" style="height: 52px;">&#160;</td></tr>
    @endif
    <tr>
        <td colspan="{{ $colspan }}">
            <strong style="font-size: 16pt;">{{ $tituloReporte !== '' ? $tituloReporte : 'Reporte descuentos gastronomía' }}</strong>
        </td>
    </tr>
    <tr>
        <td colspan="{{ $colspan }}" style="font-size: 10pt; color: #444;">
            Generado {{ date('d/m/Y H:i') }}
        </td>
    </tr>
    @if ($tituloBloque !== '' && $tituloBloque !== '—')
        <tr>
            <td colspan="{{ $colspan }}" style="font-size: 11pt; font-weight: bold; color: #17202A;">
                {{ $tituloBloque }}
            </td>
        </tr>
    @endif
    @if (! empty($subtitulo))
        <tr>
            <td colspan="{{ $colspan }}" style="font-size: 10pt; color: #444;">
                {{ $subtitulo }}
            </td>
        </tr>
    @elseif (! empty($periodo_texto) || ! empty($empresa_nombre))
        <tr>
            <td colspan="{{ $colspan }}" style="font-size: 10pt; color: #444;">
                @if (! empty($empresa_nombre))
                    Empresa: {{ $empresa_nombre }}
                @endif
                @if (! empty($periodo_texto))
                    @if (! empty($empresa_nombre))
                        &middot;
                    @endif
                    Per&iacute;odo: {{ $periodo_texto }}
                @endif
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
    @foreach ($grupos as $grupo)
        <tr>
            <td colspan="{{ $colspan }}" style="font-weight: bold; background-color: #D5E8F5;">
                Tipo: {{ $grupo['tipo_nombre'] }}
                ({{ $grupo['cantidad_lineas'] }} l&iacute;nea{{ $grupo['cantidad_lineas'] === 1 ? '' : 's' }})
            </td>
        </tr>
        @foreach ($grupo['filas'] as $fila)
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
            <td colspan="2"><strong>Total parcial {{ $grupo['tipo_nombre'] }}</strong></td>
            <td><strong>{{ $grupo['subtotal_unidades'] ?? 0 }}</strong></td>
            <td></td>
            <td><strong>{{ $grupo['subtotal_costo_total'] ?? 0 }}</strong></td>
            <td></td>
            <td><strong>{{ $grupo['subtotal_total_venta'] ?? 0 }}</strong></td>
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
