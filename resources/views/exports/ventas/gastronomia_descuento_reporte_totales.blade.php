@php
    use App\Support\Export\ExcelFormatoNumero;

    $formatoNumero = $formatoNumero ?? ExcelFormatoNumero::preferenciaGlobal();
    $fmtMonto = ExcelFormatoNumero::formateadorMonto($formatoNumero, 2);
@endphp
<table>
    @if (! empty($reservarFilaLogoExcel))
        <tr><td colspan="7" style="height: 52px;"></td></tr>
    @endif
    <tr><td colspan="7"></td></tr>
    <tr>
        <td></td>
        <td colspan="4" style="font-size: 18px; font-weight: bold; text-align: center;">
            {{ $titulo ?? 'Reporte descuentos gastronomía' }} — TOTALES
        </td>
        <td colspan="2"></td>
    </tr>
    <tr>
        <td></td>
        <td colspan="4" style="font-size: 12px; font-weight: bold; text-align: center;">
            MES: {{ $resultado['mes_etiqueta'] ?? '' }}
        </td>
        <td colspan="2"></td>
    </tr>
    <tr>
        <td></td>
        <td colspan="4" style="font-size: 12px; text-align: center;">
            {{ $empresa_nombre ?? '' }}
            @if (! empty($subtitulo))
                · {{ $subtitulo }}
            @endif
        </td>
        <td colspan="2"></td>
    </tr>
    <tr><td colspan="7"></td></tr>
    <tr>
        <td></td>
        <td><strong>CÓDIGO</strong></td>
        <td colspan="2"><strong>SECTOR</strong></td>
        <td><strong>COSTO</strong></td>
        <td colspan="2"></td>
    </tr>
    @foreach ($resultado['totales'] ?? [] as $fila)
        <tr>
            <td></td>
            <td>{{ $fila['codigo'] ?? '' }}</td>
            <td colspan="2">{{ $fila['sector'] ?? '' }}</td>
            <td>{{ $fmtMonto($fila['costo_total'] ?? 0) }}</td>
            <td colspan="2"></td>
        </tr>
    @endforeach
    <tr>
        <td></td>
        <td></td>
        <td colspan="2"></td>
        <td><strong>{{ $fmtMonto($resultado['gran_total_costo'] ?? 0) }}</strong></td>
        <td colspan="2"></td>
    </tr>
</table>
