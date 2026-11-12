<table>
    <tr>
        <td colspan="40"><strong style="font-size:16px;">{{ $titulo }}</strong></td>
    </tr>
    <tr>
        <td colspan="40">Generado {{ date('d/m/Y H:i') }}</td>
    </tr>
</table>

@if ($formato === 'gaming')
    @include('caja.flash_crown.reporte.partials.tabla_gaming', [
        'informe' => $informe,
        'modo_excel' => true,
    ])
@elseif ($formato === 'daily')
    @include('caja.flash_crown.reporte.partials.tabla_daily', [
        'informe' => $informe,
        'modo_excel' => true,
        'seccion' => $seccion ?? null,
    ])
@else
    @include('caja.flash_crown.reporte.partials.tabla_completo', [
        'informe' => $informe,
        'modo_excel' => true,
    ])
@endif
