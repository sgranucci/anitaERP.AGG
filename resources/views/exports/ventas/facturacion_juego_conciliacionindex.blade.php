@php
    $filas = $filas ?? ($resultado['filas'] ?? []);
    $subtitulo = (string) ($resultado['subtitulo'] ?? '');
    $resumen = $resultado['resumen'] ?? [];
    $formatoNumero = $formatoNumero ?? '#,##0.00';
@endphp
<table>
    @if (! empty($reservarFilaLogoExcel))
        <tr>
            <td colspan="12" style="height: 52px;"></td>
        </tr>
    @endif
    <tr>
        <td colspan="12"><strong style="font-size:16pt;">Conciliación facturación juego</strong></td>
    </tr>
    <tr>
        <td colspan="12">Generado {{ date('d/m/Y H:i') }}</td>
    </tr>
    <tr>
        <td colspan="12">
            {{ $subtitulo }}
            — Filas: {{ count($filas) }}
            — Flash {{ number_format((float) ($resumen['total_flash'] ?? 0), 2, '.', '') }}
            / Ventas {{ number_format((float) ($resumen['total_ventas'] ?? 0), 2, '.', '') }}
            / ERP {{ number_format((float) ($resumen['total_erp'] ?? 0), 2, '.', '') }}
            / Anita {{ number_format((float) ($resumen['total_anita'] ?? 0), 2, '.', '') }}
        </td>
    </tr>
    <thead>
        <tr>
            <th>Fecha</th>
            <th>Sala</th>
            <th>Rubro</th>
            <th>Cuenta</th>
            <th>Flash</th>
            <th>Ventas</th>
            <th>Contab. ERP</th>
            <th>Contab. Anita</th>
            <th>Dif. Ventas</th>
            <th>Dif. ERP</th>
            <th>Dif. Anita</th>
            <th>Estado</th>
        </tr>
    </thead>
    <tbody>
        @foreach ($filas as $fila)
            <tr>
                <td>{{ $fila['fecha_fmt'] ?? $fila['fecha'] }}</td>
                <td>{{ $fila['sala_codigo'] }}</td>
                <td>{{ $fila['rubro_label'] ?? $fila['rubro'] }}</td>
                <td>{{ $fila['cuenta_codigo'] ?: '' }}</td>
                <td>{{ number_format((float) $fila['flash'], 2, '.', '') }}</td>
                <td>{{ number_format((float) $fila['ventas'], 2, '.', '') }}</td>
                <td>{{ number_format((float) $fila['erp'], 2, '.', '') }}</td>
                <td>{{ number_format((float) $fila['anita'], 2, '.', '') }}</td>
                <td>{{ number_format((float) $fila['dif_ventas'], 2, '.', '') }}</td>
                <td>{{ number_format((float) $fila['dif_erp'], 2, '.', '') }}</td>
                <td>{{ number_format((float) $fila['dif_anita'], 2, '.', '') }}</td>
                <td>{{ $fila['estado'] }}</td>
            </tr>
        @endforeach
    </tbody>
</table>
