@if ($formato === 'gaming')
    {{-- Una sola tabla: título + generado + datos (PhpSpreadsheet no mezcla bien 2 <table>) --}}
    @include('caja.flash_crown.reporte.partials.tabla_gaming', [
        'informe' => $informe,
        'modo_excel' => true,
        'titulo_excel' => $titulo,
        'formatoNumero' => $formatoNumero ?? \App\Support\Export\ExcelFormatoNumero::AUTO,
    ])
@elseif ($formato === 'daily')
    <table>
        <tr>
            <td colspan="40"><strong style="font-size:16px;">{{ $titulo }}</strong></td>
        </tr>
        <tr>
            <td colspan="40">Generado {{ date('d/m/Y H:i') }}</td>
        </tr>
    </table>
    @include('caja.flash_crown.reporte.partials.tabla_daily', [
        'informe' => $informe,
        'modo_excel' => true,
        'seccion' => $seccion ?? null,
    ])
@else
    <table>
        <tr>
            <td colspan="40"><strong style="font-size:16px;">{{ $titulo }}</strong></td>
        </tr>
        <tr>
            <td colspan="40">Generado {{ date('d/m/Y H:i') }}</td>
        </tr>
    </table>
    @include('caja.flash_crown.reporte.partials.tabla_completo', [
        'informe' => $informe,
        'modo_excel' => true,
    ])
@endif
