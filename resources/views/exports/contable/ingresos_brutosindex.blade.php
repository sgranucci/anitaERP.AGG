@php
    $registros = $registros ?? [];
    $totales = $totales ?? [];
    $conciliacion = $conciliacion ?? [];
    $titulo = $titulo ?? 'Ingresos Brutos';
    $subtitulo = $subtitulo ?? '';
    $reservarFilaLogoExcel = ! empty($reservarFilaLogoExcel);
@endphp
<table>
    @if ($reservarFilaLogoExcel)
        <tr>
            <td colspan="7" style="height: 52px;"></td>
        </tr>
    @endif
    <tr>
        <td colspan="7"><strong style="font-size:16pt;">{{ $titulo }}</strong></td>
    </tr>
    <tr>
        <td colspan="7">Generado {{ date('d/m/Y H:i') }}</td>
    </tr>
    @if (trim($subtitulo) !== '')
        <tr>
            <td colspan="7">{{ $subtitulo }}</td>
        </tr>
    @endif
    <tr>
        <td colspan="7">Registros: {{ (int) ($totales['registros'] ?? count($registros)) }} — Importe total: {{ number_format((float) ($totales['importe'] ?? 0), 2, ',', '.') }}</td>
    </tr>
    @include('contable.ingresos_brutos.partials.tabla_listado', [
        'registros' => $registros,
        'totales' => $totales,
        'conciliacion' => $conciliacion,
        'esExcel' => true,
    ])
</table>
