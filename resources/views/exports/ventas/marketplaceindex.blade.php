@php
    $colspan = 3;
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
            <td colspan="{{ $colspan }}"><strong>Marketplaces</strong></td>
        </tr>
        <tr>
            <td colspan="{{ $colspan }}">Generado {{ date('d/m/Y H:i') }}</td>
        </tr>
        <tr>
            <td colspan="{{ $colspan }}">{{ $subtitulo ?? 'Maestro de marketplaces de locales' }}</td>
        </tr>
    </tbody>
    @include('ventas.facturacion_local.marketplace.partials.tabla_datos', [
        'presentacion' => 'excel',
        'datas' => $datas,
    ])
</table>
