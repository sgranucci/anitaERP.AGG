<table>
    <tr>
        <td colspan="8" style="font-size:16px;font-weight:bold;text-align:center;">{{ $titulo }}</td>
    </tr>
    <tr>
        <td colspan="8" style="font-size:10px;">Generado {{ date('d/m/Y H:i') }}</td>
    </tr>
    @if (!empty($subtitulo))
    <tr>
        <td colspan="8" style="font-size:10px;">{{ $subtitulo }}</td>
    </tr>
    @endif
    @if (!empty($totales))
    <tr>
        <td colspan="8" style="font-size:10px;">
            Registros: {{ $totales['total_registros'] ?? 0 }}
            | Baja: {{ $totales['total_baja'] ?? 0 }}
            | Activos: {{ $totales['total_activos'] ?? 0 }}
        </td>
    </tr>
    @endif
    <thead>
        <tr>
            <th>NPU</th>
            <th>Estado</th>
            <th>SKU</th>
            <th>Artículo</th>
            <th>Fecha alta</th>
            <th>Fecha baja</th>
            <th>Motivo baja</th>
            <th>Mov. stock</th>
        </tr>
    </thead>
    <tbody>
        @foreach ($filas as $fila)
            @php $art = $fila->articulos; @endphp
            <tr>
                <td>{{ $fila->numeroparte }}</td>
                <td>{{ \App\Support\Stock\ArticuloParteUnicaEstados::etiqueta($fila->estado) }}</td>
                <td>{{ $art->sku ?? '' }}</td>
                <td>{{ $art->descripcion ?? $art->nombre ?? '' }}</td>
                <td>{{ optional($fila->created_at)->format('d/m/Y H:i') }}</td>
                <td>{{ optional($fila->fecha_baja)->format('d/m/Y H:i') }}</td>
                <td>{{ $fila->motivo_baja ?? '' }}</td>
                <td>{{ $fila->movimientostock_id ?? '' }}</td>
            </tr>
        @endforeach
    </tbody>
</table>
