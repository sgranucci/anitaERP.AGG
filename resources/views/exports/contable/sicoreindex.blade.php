@php
    $registros = $registros ?? [];
    $totales = $totales ?? [];
    $conciliacion = $conciliacion ?? [];
    $titulo = $titulo ?? 'SICORE';
    $subtitulo = $subtitulo ?? '';
    $reservarFilaLogoExcel = ! empty($reservarFilaLogoExcel);
    $ocultarRazonSocial = ! empty($ocultarRazonSocial);
    $colsMeta = $ocultarRazonSocial ? 6 : 7;
@endphp
<table>
    @if ($reservarFilaLogoExcel)
        <tr>
            <td colspan="{{ $colsMeta }}" style="height: 52px;"></td>
        </tr>
    @endif
    <tr>
        <td colspan="{{ $colsMeta }}"><strong style="font-size:16pt;">{{ $titulo }}</strong></td>
    </tr>
    <tr>
        <td colspan="{{ $colsMeta }}">Generado {{ date('d/m/Y H:i') }}</td>
    </tr>
    @if (trim($subtitulo) !== '')
        <tr>
            <td colspan="{{ $colsMeta }}">{{ $subtitulo }}</td>
        </tr>
    @endif
    <tr>
        <td colspan="{{ $colsMeta }}">Registros: {{ (int) ($totales['registros'] ?? count($registros)) }} — Importe total: {{ number_format((float) ($totales['importe'] ?? 0), 2, ',', '.') }}</td>
    </tr>
    @include('contable.sicore.partials.tabla_listado', [
        'registros' => $registros,
        'totales' => $totales,
        'conciliacion' => $conciliacion,
        'esExcel' => true,
        'ocultarRazonSocial' => $ocultarRazonSocial,
    ])
</table>
