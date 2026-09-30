@php
    $colspan = 12;
@endphp
<table>
    @if (!empty($reservarFilaLogoExcel))
        <tbody>
            <tr>
                <td colspan="{{ $colspan }}" style="height: 52px;">&#160;</td>
            </tr>
        </tbody>
    @endif
    <tbody>
        <tr>
            <td colspan="{{ $colspan }}"><strong>Historial de devoluciones</strong></td>
        </tr>
        <tr>
            <td colspan="{{ $colspan }}">Generado {{ date('d/m/Y H:i') }}</td>
        </tr>
        <tr>
            <td colspan="{{ $colspan }}">{{ $subtitulo ?? '' }}</td>
        </tr>
    </tbody>
    @include('ventas.facturacion_local.historial_devolucion.partials.tabla_datos', [
        'presentacion' => 'excel',
        'datas' => $datas,
        'totales' => $totales ?? [],
    ])
</table>
