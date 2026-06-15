<table>
    @if (!empty($reservarFilaLogoExcel))
        <tbody>
            <tr>
                <td colspan="13" style="height: 52px;">&#160;</td>
            </tr>
        </tbody>
    @endif
    <tbody>
        <tr>
            <td colspan="13">
                <h2 style="margin: 0; font-size: 18pt; font-weight: bold;">Listado canjes marketing</h2>
                @if (! empty($filtros['fecha_desde']) || ! empty($filtros['fecha_hasta']))
                    <div style="font-size: 10pt;">
                        Período: {{ $filtros['fecha_desde'] ?? '—' }}
                        @if (($filtros['fecha_hasta'] ?? '') !== ($filtros['fecha_desde'] ?? ''))
                            → {{ $filtros['fecha_hasta'] ?? '—' }}
                        @endif
                    </div>
                @endif
            </td>
        </tr>
    </tbody>
    <thead>
        <tr>
            <th>Fecha</th>
            <th>Empresa</th>
            <th>Id VIP</th>
            <th>Nombre</th>
            <th>Apellido</th>
            <th>Nickname</th>
            <th>Mozo</th>
            <th>Producto</th>
            <th>Cantidad</th>
            <th>CMV</th>
            <th>P. venta</th>
            <th>Sala</th>
            <th>SKU</th>
        </tr>
    </thead>
    <tbody>
        @foreach ($filas as $f)
            <tr>
                <td>{{ $f->fechacanje_fmt ?? '—' }}</td>
                <td>{{ $f->nombreempresa ?? '—' }}</td>
                <td>{{ $f->numeroid_vip ?? '—' }}</td>
                <td>{{ $f->nombre_vip ?? '—' }}</td>
                <td>{{ $f->apellido_vip ?? '—' }}</td>
                <td>{{ $f->nickname ?? '' }}</td>
                <td>{{ $f->mozo_etiqueta !== '' ? $f->mozo_etiqueta : '—' }}</td>
                <td>{{ $f->producto ?? '—' }}</td>
                <td>{{ number_format((float) ($f->cantidad ?? 0), 3, ',', '.') }}</td>
                <td>{{ number_format((float) ($f->cmv ?? 0), 2, ',', '.') }}</td>
                <td>{{ number_format((float) ($f->precio_venta ?? 0), 2, ',', '.') }}</td>
                <td>{{ $f->sala !== '' ? $f->sala : '—' }}</td>
                <td>{{ $f->sku ?? '—' }}</td>
            </tr>
        @endforeach
    </tbody>
    @if (! empty($totales))
        <tfoot>
            <tr>
                <td colspan="8"><strong>Totales</strong></td>
                <td>{{ number_format((float) ($totales['cantidad_total'] ?? 0), 3, ',', '.') }}</td>
                <td>{{ number_format((float) ($totales['cmv_total'] ?? 0), 2, ',', '.') }}</td>
                <td>{{ number_format((float) ($totales['precio_venta_total'] ?? 0), 2, ',', '.') }}</td>
                <td colspan="2"></td>
            </tr>
        </tfoot>
    @endif
</table>
