@php
    $cliente = $reporte['cliente'] ?? null;
    $nombreCliente = $cliente->nombre ?? '';
    $docCliente = $cliente->numerodocumento ?? '';
    $generado = $reporte['generado'] ?? date('d/m/Y H:i');
    $periodos = $reporte['periodos'] ?? [];
@endphp
<table>
    @if (! empty($reservarFilaLogoExcel))
        <tr>
            <td colspan="5" style="height: 52px;"></td>
        </tr>
    @endif
    <tr>
        <td colspan="5"><strong style="font-size: 16pt;">Explicación matriz de riesgo UIF</strong></td>
    </tr>
    <tr>
        <td colspan="5">Generado {{ $generado }}</td>
    </tr>
    <tr>
        <td colspan="5">
            {{ $nombreCliente }}
            @if ($docCliente !== '')
                — Doc. {{ $docCliente }}
            @endif
            — ID {{ $cliente->id ?? '' }}
            — Períodos: {{ count($periodos) }}
        </td>
    </tr>
</table>

@include('uif.cliente_uif.partials.matriz_riesgo_explicacion_contenido', [
    'reporte' => $reporte,
    'esExcel' => true,
])
