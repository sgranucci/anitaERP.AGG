@php
    $registros = $registros ?? [];
    $totales = $totales ?? [];
    $conciliacion = $conciliacion ?? [];
    $titulo = $titulo ?? 'SICORE';
    $subtitulo = $subtitulo ?? '';
    $reservarFilaLogoExcel = ! empty($reservarFilaLogoExcel);
@endphp
<table>
    @if ($reservarFilaLogoExcel)
        <tr>
            <td colspan="9" style="height: 52px;"></td>
        </tr>
    @endif
    <tr>
        <td colspan="9"><strong style="font-size:16pt;">{{ $titulo }}</strong></td>
    </tr>
    <tr>
        <td colspan="9">Generado {{ date('d/m/Y H:i') }}</td>
    </tr>
    @if (trim($subtitulo) !== '')
        <tr>
            <td colspan="9">{{ $subtitulo }}</td>
        </tr>
    @endif
    <tr>
        <td colspan="9">Registros: {{ (int) ($totales['registros'] ?? count($registros)) }} — Importe total: {{ number_format((float) ($totales['importe'] ?? 0), 2, ',', '.') }}</td>
    </tr>
    @include('contable.sicore.partials.tabla_listado', [
        'registros' => $registros,
        'totales' => $totales,
        'conciliacion' => $conciliacion,
        'esExcel' => true,
    ])
</table>
