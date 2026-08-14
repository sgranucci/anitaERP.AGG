@php
    $tituloReporte = $titulo ?? 'Informe gerente gastronomía';
    $subtituloReporte = $subtitulo ?? '';
@endphp
<table>
    @if (!empty($reservarFilaLogoExcel))
        <tr>
            <td colspan="8" style="height: 52px;"></td>
        </tr>
    @endif
    <tr>
        <td colspan="8"><strong style="font-size: 16pt;">{{ $tituloReporte }}</strong></td>
    </tr>
    <tr>
        <td colspan="8">Generado {{ date('d/m/Y H:i') }}</td>
    </tr>
    @if ($subtituloReporte !== '')
        <tr>
            <td colspan="8">{{ $subtituloReporte }}</td>
        </tr>
    @endif
    @include('ventas.gastronomia.informe_gerente.partials.tabla_export_datos', [
        'informe' => $informe ?? [],
    ])
</table>
