@php
    $p = $parametros ?? [];
    $verdad = $p['verdad'] ?? [];
    $formatoExcel = \App\Support\Export\ExcelFormatoNumero::normalizar(
        $excel_formato_numero ?? \App\Support\Export\ExcelFormatoNumero::preferenciaGlobal()
    );
    // auto: número crudo (la máscara la pone WithColumnFormatting); ar/intl: texto ya formateado.
    $fmt = \App\Support\Export\ExcelFormatoNumero::formateadorMonto($formatoExcel, 2);
    $subtitulo = 'Empresas: '.implode(', ', (array) ($p['empresa_ids'] ?? []))
        .' | '.($p['fecha_desde'] ?? '').' a '.($p['fecha_hasta'] ?? '')
        .' | base '.($p['base_saldo'] ?? '')
        .' | tolerancia '.number_format((float) ($p['tolerancia'] ?? 0), 2, ',', '.')
        .' | verdad del período: '.($verdad['etiqueta'] ?? '')
        .($solo_diferencias ? ' | solo diferencias' : '');
@endphp
<table>
    @if (!empty($reservarFilaLogoExcel))
        <tr><td></td><td></td><td></td><td></td><td></td><td></td><td></td><td></td></tr>
    @endif
    <tr>
        <td>Paridad anitaERP vs Anita — {{ $reporte->nombre ?? '' }}</td>
        <td></td><td></td><td></td><td></td><td></td><td></td><td></td>
    </tr>
    <tr>
        <td>Generado {{ now()->format('d/m/Y H:i') }}</td>
        <td></td><td></td><td></td><td></td><td></td><td></td><td></td>
    </tr>
    <tr>
        <td>{{ $subtitulo }}</td>
        <td></td><td></td><td></td><td></td><td></td><td></td><td></td>
    </tr>
    <tr>
        <td>Línea</td>
        <td>Rubro</td>
        <td>Informe</td>
        <td>Asientos ERP</td>
        <td>Anita</td>
        <td>Dif. motor</td>
        <td>Dif. Anita</td>
        <td>%</td>
    </tr>
    @foreach ($filas as $fila)
        <tr>
            <td>{{ $fila['codigo'] }}</td>
            <td>{{ str_repeat('    ', max(0, ((int) $fila['nivel']) - 1)).$fila['nombre'] }}</td>
            <td>{{ $fila['impreso'] !== null ? $fmt($fila['impreso']) : '' }}</td>
            <td>{{ $fmt($fila['erp']) }}</td>
            <td>{{ $fmt($fila['anita']) }}</td>
            <td>{{ empty($fila['cuadra_motor']) ? $fmt($fila['diferencia_motor']) : '' }}</td>
            <td>{{ empty($fila['cuadra']) ? $fmt($fila['diferencia']) : '' }}</td>
            <td>{{ (empty($fila['cuadra']) && $fila['diferencia_pct'] !== null) ? $fmt($fila['diferencia_pct']) : '' }}</td>
        </tr>
        @foreach ($fila['cuentas'] ?? [] as $cuenta)
            <tr>
                <td></td>
                <td>{{ str_repeat('    ', (int) $fila['nivel']) }}Cuenta {{ $cuenta['codigo_fmt'] }}</td>
                <td></td>
                <td>{{ $fmt($cuenta['erp']) }}</td>
                <td>{{ $fmt($cuenta['anita']) }}</td>
                <td></td>
                <td>{{ $fmt($cuenta['diferencia']) }}</td>
                <td></td>
            </tr>
        @endforeach
    @endforeach
    @if (!empty($resultado['cuentas_fuera_plan']))
        <tr><td></td><td></td><td></td><td></td><td></td><td></td><td></td><td></td></tr>
        <tr>
            <td>Cuentas con movimiento en Anita que no existen en el plan ERP</td>
            <td></td><td></td><td></td><td></td><td></td><td></td><td></td>
        </tr>
        @foreach ($resultado['cuentas_fuera_plan'] as $cuenta)
            <tr>
                <td>{{ $cuenta['codigo_fmt'] }}</td>
                <td>Sin cuenta imputable en el plan ERP</td>
                <td></td>
                <td></td>
                <td>{{ $fmt($cuenta['anita']) }}</td>
                <td></td><td></td><td></td>
            </tr>
        @endforeach
    @endif
</table>
