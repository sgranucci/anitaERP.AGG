@php
    $colspan = 18;
@endphp
<table>
    @if (! empty($reservarFilaLogoExcel))
        <tr><td colspan="{{ $colspan }}" style="height: 52px;"></td></tr>
    @endif
    <tr><td colspan="{{ $colspan }}"><strong>{{ $titulo }}</strong></td></tr>
    <tr><td colspan="{{ $colspan }}">Generado {{ date('d/m/Y H:i') }}</td></tr>
    @if (trim((string) $subtitulo) !== '')
        <tr><td colspan="{{ $colspan }}">{{ $subtitulo }}</td></tr>
    @endif
    @if (! empty($totales))
        <tr>
            <td colspan="{{ $colspan }}">
                Vencidos: {{ (int) ($totales['vencidos'] ?? 0) }} ·
                Vencen en 30 días: {{ (int) ($totales['vencen_30'] ?? 0) }} ·
                Entre 31 y 60: {{ (int) ($totales['vencen_60'] ?? 0) }} ·
                Sin vigencia: {{ (int) ($totales['sin_vigencia'] ?? 0) }} ·
                Tope: {{ number_format((float) ($totales['monto_tope'] ?? 0), 2, ',', '.') }} ·
                Recibido: {{ number_format((float) ($totales['monto_recibido'] ?? 0), 2, ',', '.') }} ·
                Facturado: {{ number_format((float) ($totales['monto_facturado'] ?? 0), 2, ',', '.') }} ·
                Consumido: {{ number_format((float) ($totales['monto_consumido'] ?? 0), 2, ',', '.') }} ·
                Disponible: {{ number_format((float) ($totales['monto_disponible'] ?? 0), 2, ',', '.') }}
            </td>
        </tr>
    @endif
    @if (count($filas) > 0)
        <tr><td colspan="{{ $colspan }}">{{ count($filas) }} contrato(s)</td></tr>
    @endif
</table>

@include('compras.contrato_vencimiento_reporte.partials.tabla_datos', [
    'filas' => $filas,
    'puede_ver_ordencompra' => false,
    'para_excel' => true,
])
